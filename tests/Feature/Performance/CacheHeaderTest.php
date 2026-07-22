<?php

namespace Tests\Feature\Performance;

use App\Models\Category;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_artifacts_long_cache(): void
    {
        Storage::fake('previews');

        $component = Component::factory()->published()->create([
            'slug' => 'elements/demo-01',
            'version' => '1.0.0',
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);

        Storage::disk('previews')->put('elements/demo-01/1.0.0/react.html', '<html><body>preview</body></html>');

        $response = $this->get('/previews/elements/demo-01/1.0.0/react.html');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_component_pages_send_sensible_cache_headers(): void
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        Component::factory()->published()->free()->create([
            'slug' => 'elements/hero-01',
            'usage_category_id' => $usage->id,
        ]);

        $response = $this->get('/components/hero/hero-01');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('s-maxage=3600', $cacheControl);
    }

    public function test_catalog_index_sends_sensible_cache_headers(): void
    {
        $response = $this->get('/components');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('s-maxage=3600', $cacheControl);
    }

    public function test_taxonomy_pages_send_sensible_cache_headers(): void
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $industry = Category::factory()->industry()->create(['slug' => 'saas-software']);

        $usageResponse = $this->get('/components/hero');
        $usageResponse->assertOk();

        $cacheControl = (string) $usageResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);

        $industryResponse = $this->get('/industries');
        $industryResponse->assertOk();

        $cacheControl = (string) $industryResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);

        $industryDetailResponse = $this->get('/industries/saas-software');
        $industryDetailResponse->assertOk();

        $cacheControl = (string) $industryDetailResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }
}
