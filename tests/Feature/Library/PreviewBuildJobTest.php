<?php

namespace Tests\Feature\Library;

use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;

/**
 * Runs the REAL BuildComponentPreview job against the REAL example
 * component (elements/section-title-01). Skips when npm is unavailable.
 */
class PreviewBuildJobTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    private Component $component;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessNpmAvailable();

        Storage::fake('previews');

        $this->syncRealLibrary();

        $this->component = $this->componentBySlug('elements/section-title-01');

        $this->runBuildJob($this->component);
    }

    public function test_html_artifact_written_for_both_frameworks()
    {
        $disk = Storage::disk('previews');

        $fresh = $this->component->fresh();

        $this->assertNotNull($fresh->preview_built_at);
        $this->assertDatabaseCount('preview_build_failures', 0);

        foreach (['react', 'vue'] as $framework) {
            $path = $fresh->previewPath($framework);

            $this->assertNotNull($path, "preview_paths.{$framework} missing");
            $this->assertTrue($disk->exists($path), "artifact missing at {$path}");
            $this->assertGreaterThan(1000, strlen((string) $disk->get($path)));
        }
    }

    public function test_artifact_is_self_contained_no_external_scripts()
    {
        $disk = Storage::disk('previews');

        foreach (['react', 'vue'] as $framework) {
            $html = (string) $disk->get($this->component->fresh()->previewPath($framework));

            $this->assertStringNotContainsString('<script src=', $html, "{$framework}: external script found");
            $this->assertStringNotContainsString('<link rel="stylesheet"', $html, "{$framework}: external stylesheet found");
            $this->assertStringContainsString('<script type="module">', $html, "{$framework}: inline module script missing");
            $this->assertStringContainsString('<style>', $html, "{$framework}: inline css missing");
        }
    }

    public function test_versioned_path_scheme()
    {
        $fresh = $this->component->fresh();

        $this->assertSame('elements/section-title-01/1.0.0/react.html', $fresh->previewPath('react'));
        $this->assertSame('elements/section-title-01/1.0.0/vue.html', $fresh->previewPath('vue'));
    }
}
