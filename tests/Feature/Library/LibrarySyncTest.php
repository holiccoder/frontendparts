<?php

namespace Tests\Feature\Library;

use App\Enums\ComponentStatus;
use App\Jobs\BuildComponentPreview;
use App\Models\Component;
use App\Services\Library\LibrarySync;
use App\Services\Library\SyncResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

class LibrarySyncTest extends TestCase
{
    use BuildsLibraryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLibraryFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownLibraryFixtures();
        parent::tearDown();
    }

    private function sync(): SyncResult
    {
        return app(LibrarySync::class)->run();
    }

    public function test_upserts_new_and_updated_components()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $this->sync();

        $this->assertDatabaseCount('components', 1);
        $this->assertSame('Pricing Section 01', Component::query()->sole()->name);

        $this->libraryComponent('sections/pricing-section-01', annotation: ['name' => 'Pricing Section Deluxe', 'version' => '2.0.0']);

        $result = $this->sync();

        $this->assertFalse($result->hasErrors());
        $this->assertDatabaseCount('components', 1);

        $component = Component::query()->sole();
        $this->assertSame('Pricing Section Deluxe', $component->name);
        $this->assertSame('2.0.0', $component->version);
    }

    public function test_rebuild_queued_for_changed_component_and_dependents()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('elements/button-01');
        $this->libraryComponent('sections/pricing-section-01', imports: ['../../elements/button-01']);
        $this->libraryComponent('sections/hero-section-01');

        $this->sync();

        $button = Component::query()->where('slug', 'elements/button-01')->sole();
        $section = Component::query()->where('slug', 'sections/pricing-section-01')->sole();
        $hero = Component::query()->where('slug', 'sections/hero-section-01')->sole();

        Queue::fake();

        $this->libraryComponent('elements/button-01', data: ['heading' => 'Changed sample data']);
        $this->sync();

        Queue::assertPushed(BuildComponentPreview::class, 2);
        Queue::assertPushed(BuildComponentPreview::class, fn (BuildComponentPreview $job): bool => $job->componentId === $button->id && $job->frameworks === ['react', 'vue']);
        Queue::assertPushed(BuildComponentPreview::class, fn (BuildComponentPreview $job): bool => $job->componentId === $section->id);
        Queue::assertNotPushed(BuildComponentPreview::class, fn (BuildComponentPreview $job): bool => $job->componentId === $hero->id);
    }

    public function test_unchanged_component_not_rebuilt()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $this->sync();

        Queue::fake();

        $result = $this->sync();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->upserted);
        Queue::assertNothingPushed();
    }

    public function test_draft_status_preserved_on_resync()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $this->sync();

        $component = Component::query()->sole();
        $this->assertSame(ComponentStatus::Draft, $component->status);

        $this->libraryComponent('sections/pricing-section-01', annotation: ['name' => 'Renamed Section']);
        $this->sync();

        $this->assertSame(ComponentStatus::Draft, $component->fresh()->status);

        $component->update(['status' => ComponentStatus::Published]);
        $this->libraryComponent('sections/pricing-section-01', annotation: ['name' => 'Renamed Again']);
        $this->sync();

        $this->assertSame(ComponentStatus::Published, $component->fresh()->status);
    }

    public function test_command_succeeds_on_valid_library()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $this->artisan('library:sync')
            ->expectsOutputToContain('Scanned 1, upserted 1, failed 0.')
            ->assertExitCode(0);
    }

    public function test_command_resyncs_search_index_after_upserts()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $this->artisan('library:sync')
            ->expectsOutputToContain('Search index re-synced for the component catalog.')
            ->assertExitCode(0);
    }

    public function test_command_exits_nonzero_when_component_has_errors()
    {
        $this->libraryComponent('sections/pricing-section-01', frameworks: ['react']);

        $this->artisan('library:sync')->assertExitCode(1);

        $this->assertDatabaseCount('components', 0);
    }
}
