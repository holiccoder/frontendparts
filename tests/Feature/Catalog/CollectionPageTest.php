<?php

namespace Tests\Feature\Catalog;

use App\Models\Collection;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_collections_only()
    {
        Collection::factory()->published()->create([
            'name' => 'Restaurant Landing Kit',
            'slug' => 'restaurant-landing-kit',
        ]);
        Collection::factory()->draft()->create(['slug' => 'internal-wip']);

        $this->get('/collections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/index')
                ->has('collections', 1)
                ->where('collections.0.slug', 'restaurant-landing-kit')
                ->where('collections.0.name', 'Restaurant Landing Kit')
                ->where('collections.0.url', route('collections.show', ['slug' => 'restaurant-landing-kit']))
                ->where('collections.0.components_count', 0)
                ->where('meta.canonical', route('collections.index'))
            );
    }

    public function test_show_lists_components_in_pivot_order()
    {
        $collection = Collection::factory()->published()->create([
            'name' => 'SaaS Starter',
            'slug' => 'saas-starter',
        ]);

        $first = Component::factory()->published()->create();
        $second = Component::factory()->published()->create();
        $draft = Component::factory()->draft()->create();

        // Attach out of id order: pivot sort_order, not id, drives the grid.
        $collection->components()->attach($second->id, ['sort_order' => 1]);
        $collection->components()->attach($first->id, ['sort_order' => 2]);
        $collection->components()->attach($draft->id, ['sort_order' => 0]);

        $this->get('/collections/saas-starter')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/show')
                ->where('collection.slug', 'saas-starter')
                ->where('collection.name', 'SaaS Starter')
                ->where('meta.canonical', route('collections.show', ['slug' => 'saas-starter']))
                // The draft member never leaks into the public bundle.
                ->has('components.data', 2)
                ->where('components.data.0.id', $second->id)
                ->where('components.data.1.id', $first->id)
            );
    }

    public function test_show_uses_seo_overrides_when_set()
    {
        Collection::factory()->published()->create([
            'slug' => 'restaurant-landing-kit',
            'meta_title' => 'Restaurant landing kit — hero, menu & reservation sections',
            'meta_description' => 'Every section a restaurant landing page needs.',
        ]);

        $this->get('/collections/restaurant-landing-kit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.title', 'Restaurant landing kit — hero, menu & reservation sections')
                ->where('meta.description', 'Every section a restaurant landing page needs.')
            );
    }

    public function test_draft_collection_404s_and_unknown_slug_404s()
    {
        Collection::factory()->draft()->create(['slug' => 'hidden-kit']);

        $this->get('/collections/hidden-kit')->assertNotFound();
        $this->get('/collections/does-not-exist')->assertNotFound();
    }

    public function test_sitemap_contains_collection_urls()
    {
        Collection::factory()->published()->create(['slug' => 'restaurant-landing-kit']);
        Collection::factory()->draft()->create(['slug' => 'hidden-kit']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('<loc>'.route('collections.index').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('collections.show', ['slug' => 'restaurant-landing-kit']).'</loc>', $content);
        $this->assertStringNotContainsString('hidden-kit', $content);
    }
}
