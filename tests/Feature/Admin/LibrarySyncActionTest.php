<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Resources\Components\Pages\ListComponents;
use App\Models\Admin;
use App\Models\LibrarySyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

class LibrarySyncActionTest extends TestCase
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

    public function test_admin_can_trigger_sync_from_panel()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ListComponents::class)
            ->callAction('runSync')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('components', ['slug' => 'sections/pricing-section-01']);
    }

    public function test_sync_run_logged_with_stats()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01');
        $this->libraryComponent('elements/button-01', frameworks: ['react']);

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ListComponents::class)->callAction('runSync');

        $run = LibrarySyncRun::query()->sole();

        $this->assertSame(2, $run->scanned);
        $this->assertSame(1, $run->upserted);
        $this->assertArrayHasKey('elements/button-01', $run->errors);
        $this->assertStringContainsString('Missing vue twin', implode(' ', $run->errors['elements/button-01']));
    }

    public function test_non_admin_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(ComponentResource::getUrl('index'))
            ->assertRedirect('/admin/login');

        $this->assertDatabaseCount('library_sync_runs', 0);
    }
}
