<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentStatus;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreviewServingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('previews');
    }

    public function test_serves_200_with_cache_and_csp_headers()
    {
        $component = $this->publishedComponent();

        Storage::disk('previews')->put('elements/demo-01/1.0.0/react.html', '<html><body>preview</body></html>');

        $response = $this->get('/previews/elements/demo-01/1.0.0/react.html');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', 'sandbox allow-scripts');

        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);

        $this->assertSame('<html><body>preview</body></html>', $response->getContent());
    }

    public function test_404_for_draft_component()
    {
        $component = $this->publishedComponent();
        $component->update(['status' => ComponentStatus::Draft]);

        Storage::disk('previews')->put('elements/demo-01/1.0.0/react.html', '<html>preview</html>');

        $this->get('/previews/elements/demo-01/1.0.0/react.html')->assertNotFound();
    }

    public function test_404_for_missing_version()
    {
        $component = $this->publishedComponent();

        Storage::disk('previews')->put('elements/demo-01/1.0.0/react.html', '<html>preview</html>');

        $this->get('/previews/elements/demo-01/9.9.9/react.html')->assertNotFound();
        $this->get('/previews/elements/demo-01/1.0.0/vue.html')->assertNotFound();
    }

    private function publishedComponent(): Component
    {
        return Component::factory()->published()->create([
            'slug' => 'elements/demo-01',
            'version' => '1.0.0',
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);
    }
}
