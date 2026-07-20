<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_component_and_taxonomy_urls()
    {
        $this->seed(CategorySeeder::class);

        $usage = Category::query()->where('slug', 'hero')->firstOrFail();
        $industry = Category::query()->where('slug', 'saas-software')->firstOrFail();

        $component = Component::factory()->published()->create([
            'slug' => 'elements/seo-01',
            'usage_category_id' => $usage->id,
        ]);
        $industry->components()->attach($component);

        // A draft must never leak into the sitemap.
        Component::factory()->draft()->create([
            'slug' => 'elements/draft-01',
            'usage_category_id' => $usage->id,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('components.index').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('industries.index').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('components.usage', ['usage' => 'hero']).'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('industries.show', ['industry' => 'saas-software']).'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('components.show', ['usage' => 'hero', 'slug' => 'seo-01']).'</loc>', $content);
        $this->assertStringNotContainsString('draft-01', $content);
    }

    public function test_robots_disallows_private_zones()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();

        foreach (['/dashboard', '/checkout', '/settings', '/admin'] as $zone) {
            $this->assertStringContainsString("Disallow: {$zone}", $content);
        }

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $content);
    }

    public function test_titles_unique_per_component_page()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/one-01',
            'name' => 'Alpha One',
            'usage_category_id' => $usage->id,
        ]);

        Component::factory()->published()->create([
            'slug' => 'elements/two-01',
            'name' => 'Beta Two',
            'usage_category_id' => $usage->id,
        ]);

        $titles = [];

        foreach (['one-01', 'two-01'] as $basename) {
            $response = $this->get("/components/hero/{$basename}");
            $response->assertOk();
            $titles[] = $response->viewData('page')['props']['meta']['title'];
        }

        $this->assertCount(2, array_unique($titles));
        $this->assertStringContainsString('Alpha One', $titles[0]);
        $this->assertStringContainsString('Beta Two', $titles[1]);
    }
}
