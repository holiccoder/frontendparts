<?php

namespace Tests\Feature\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentStatus;
use App\Jobs\BuildComponentPreview;
use App\Jobs\CaptureComponentScreenshots;
use App\Models\Component;
use App\Services\Library\ComponentScanner;
use App\Services\Library\LibrarySync;
use App\Services\Library\ParsedComponent;
use App\Services\Library\PreviewScreenshotter;
use App\Services\Library\SyncResult;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;

/**
 * Seed-content gate (task 1.12.1): the real component library under
 * library/{react,vue} must sync cleanly into the catalog with at least 20
 * components, twin implementations for every slug, and public pages that
 * render for every published component. The default suite never runs real
 * builds; the full-pipeline integration test is guarded by FP_RUN_SLOW.
 */
class SeedContentTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    /**
     * Scan the real library via the default config paths (no fixture
     * overrides — this test intentionally exercises the authored library).
     *
     * @return array{0: array<string, ParsedComponent>, 1: array<string, ParsedComponent>}
     */
    private function scanRealLibrary(): array
    {
        $scanner = new ComponentScanner;

        return [
            $scanner->scan((string) config('library.react_path'), 'react'),
            $scanner->scan((string) config('library.vue_path'), 'vue'),
        ];
    }

    /**
     * Seed the real taxonomy and sync the real library with the queue faked
     * so no preview builds execute in the default suite.
     */
    private function syncRealLibraryWithFakeQueue(): SyncResult
    {
        $this->seed(CategorySeeder::class);

        Queue::fake();

        return app(LibrarySync::class)->run();
    }

    public function test_sync_publishes_at_least_20_components()
    {
        [$react, $vue] = $this->scanRealLibrary();

        $slugs = array_values(array_unique([...array_keys($react), ...array_keys($vue)]));

        $this->assertGreaterThanOrEqual(20, count($slugs), 'Expected at least 20 authored components in the real library');

        $result = $this->syncRealLibraryWithFakeQueue();

        $this->assertFalse($result->hasErrors(), 'library:sync failed: '.json_encode($result->failures()));
        $this->assertGreaterThanOrEqual(20, $result->upserted);
        $this->assertGreaterThanOrEqual(20, Component::query()->count());
        Queue::assertPushed(BuildComponentPreview::class);
    }

    public function test_every_component_has_both_framework_twins()
    {
        [$react, $vue] = $this->scanRealLibrary();

        $reactSlugs = array_keys($react);
        $vueSlugs = array_keys($vue);
        sort($reactSlugs);
        sort($vueSlugs);

        $this->assertSame($reactSlugs, $vueSlugs, 'React and Vue library trees must contain identical slugs');
    }

    public function test_at_least_10_free_components()
    {
        $result = $this->syncRealLibraryWithFakeQueue();

        $this->assertFalse($result->hasErrors(), 'library:sync failed: '.json_encode($result->failures()));

        $this->assertGreaterThanOrEqual(
            10,
            Component::query()->where('access_level', AccessLevel::Free)->count(),
        );
    }

    public function test_catalog_pages_render_for_all_published()
    {
        $result = $this->syncRealLibraryWithFakeQueue();

        $this->assertFalse($result->hasErrors(), 'library:sync failed: '.json_encode($result->failures()));

        Component::query()->update(['status' => ComponentStatus::Published]);

        $components = Component::query()->with('usageCategory')->get();

        $this->assertGreaterThanOrEqual(20, $components->count());

        foreach ($components as $component) {
            $this->get($component->publicUrl())
                ->assertOk("component page failed for {$component->slug} at {$component->publicUrl()}");
        }

        $this->get('/components')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/index')
                ->where('components.meta.total', $components->count())
            );
    }

    /**
     * Integration: real sync (no queue fake — jobs land on the database
     * queue), then a real preview build + real screenshots for one
     * component. Guarded: only runs with FP_RUN_SLOW=1.
     */
    public function test_full_pipeline_real_builds()
    {
        if (! env('FP_RUN_SLOW')) {
            $this->markTestSkipped('Set FP_RUN_SLOW=1 to run the real build pipeline integration test.');
        }

        $this->skipUnlessNpmAvailable();

        if (! app(PreviewScreenshotter::class)->available()) {
            $this->markTestSkipped('No headless browser stack available (no puppeteer module, no Chrome/Chromium binary).');
        }

        Storage::fake('previews');
        config()->set('queue.default', 'database');

        $this->seed(CategorySeeder::class);

        $result = app(LibrarySync::class)->run();

        $this->assertFalse($result->hasErrors(), 'library:sync failed: '.json_encode($result->failures()));

        $component = $this->componentBySlug('elements/button-01');

        app()->call([(new BuildComponentPreview($component->id, ['react', 'vue'])), 'handle']);
        app()->call([(new CaptureComponentScreenshots($component->id, ['react', 'vue'])), 'handle']);

        $this->assertTrue($component->fresh()->canPublish());
    }
}
