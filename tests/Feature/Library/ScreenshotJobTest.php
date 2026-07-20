<?php

namespace Tests\Feature\Library;

use App\Jobs\BuildComponentPreview;
use App\Jobs\CaptureComponentScreenshots;
use App\Models\Component;
use App\Services\Library\PreviewScreenshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;

class ScreenshotJobTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    /**
     * Integration: real build + real headless browser. Skips unless npm AND
     * a usable browser stack (puppeteer module or Chrome/Chromium binary)
     * are available.
     */
    public function test_three_viewport_screenshots_generated()
    {
        $this->skipUnlessNpmAvailable();

        $screenshotter = app(PreviewScreenshotter::class);

        if (! $screenshotter->available()) {
            $this->markTestSkipped('No headless browser stack available (no puppeteer module, no Chrome/Chromium binary).');
        }

        Storage::fake('previews');

        $this->syncRealLibrary();

        $composite = $this->componentBySlug('sections/title-showcase-01');

        $this->runBuildJob($composite);

        app()->call([(new CaptureComponentScreenshots($composite->id, ['react', 'vue'])), 'handle']);

        $disk = Storage::disk('previews');

        foreach (['react', 'vue'] as $framework) {
            foreach ([375, 768, 1280] as $width) {
                $path = "sections/title-showcase-01/1.0.0/shots/{$framework}-{$width}.png";

                $this->assertTrue($disk->exists($path), "screenshot missing at {$path}");
                $this->assertGreaterThan(500, strlen((string) $disk->get($path)), "screenshot at {$path} looks empty");
            }
        }

        $this->assertTrue($composite->fresh()->canPublish());
    }

    public function test_publish_blocked_when_screenshots_missing()
    {
        Storage::fake('previews');

        $component = Component::factory()->create([
            'slug' => 'elements/demo-01',
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);

        $disk = Storage::disk('previews');

        $this->assertFalse($component->canPublish(), 'must be false while artifacts are missing');

        $disk->put('elements/demo-01/1.0.0/react.html', '<html>react</html>');
        $disk->put('elements/demo-01/1.0.0/vue.html', '<html>vue</html>');

        $this->assertFalse($component->fresh()->canPublish(), 'must be false while screenshots are missing');

        foreach (['react', 'vue'] as $framework) {
            foreach ([375, 768] as $width) {
                $disk->put("elements/demo-01/1.0.0/shots/{$framework}-{$width}.png", 'png');
            }
        }

        $this->assertFalse($component->fresh()->canPublish(), 'must be false until all 3 widths exist for both frameworks');

        $disk->put('elements/demo-01/1.0.0/shots/react-1280.png', 'png');
        $disk->put('elements/demo-01/1.0.0/shots/vue-1280.png', 'png');

        $this->assertTrue($component->fresh()->canPublish());

        $component->fresh()->update(['preview_paths' => ['react' => 'elements/demo-01/1.0.0/react.html']]);

        $this->assertFalse($component->fresh()->canPublish(), 'must be false without both-framework previews');
    }

    public function test_failed_build_recorded()
    {
        Storage::fake('previews');

        $component = Component::factory()->create(['slug' => 'elements/ghost-01']);

        app()->call([(new BuildComponentPreview($component->id, ['react', 'vue'])), 'handle']);

        foreach (['react', 'vue'] as $framework) {
            $this->assertDatabaseHas('preview_build_failures', [
                'component_id' => $component->id,
                'framework' => $framework,
            ]);
        }

        $failure = $component->previewBuildFailures()->where('framework', 'react')->sole();

        $this->assertStringContainsString('missing', $failure->error);
        $this->assertNull($component->fresh()->preview_built_at);
    }
}
