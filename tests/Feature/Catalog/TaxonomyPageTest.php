<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaxonomyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_page_200_for_seeded_slug()
    {
        $this->seed(CategorySeeder::class);

        $hero = Category::query()->where('slug', 'hero')->firstOrFail();
        Component::factory()->count(2)->published()->create(['usage_category_id' => $hero->id]);

        $this->get('/components/hero')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/usage')
                ->where('usage.slug', 'hero')
                ->where('usage.name', 'Hero')
                ->has('components.data', 2)
            );
    }

    public function test_industry_index_and_detail_200()
    {
        $this->seed(CategorySeeder::class);

        $usage = Category::query()->where('slug', 'hero')->firstOrFail();
        $saas = Category::query()->where('slug', 'saas-software')->firstOrFail();
        $saas->components()->attach(
            Component::factory()->count(2)->published()->create(['usage_category_id' => $usage->id])
        );

        $this->get('/industries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('industries/index')
                ->has('industries', 12)
            );

        $this->get('/industries/saas-software')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('industries/show')
                ->where('industry.slug', 'saas-software')
                ->has('components.data', 2)
            );
    }

    public function test_unknown_slug_404()
    {
        $this->get('/components/does-not-exist')->assertNotFound();
        $this->get('/industries/does-not-exist')->assertNotFound();
    }

    public function test_curated_props_present()
    {
        $this->seed(CategorySeeder::class);

        $hero = Category::query()->where('slug', 'hero')->firstOrFail();
        Component::factory()->count(4)->published()->create(['usage_category_id' => $hero->id]);

        $pricing = Category::query()->where('slug', 'pricing')->firstOrFail();
        Component::factory()->count(3)->published()->create(['usage_category_id' => $pricing->id]);

        $this->get('/components/hero')->assertInertia(fn (Assert $page) => $page
            ->where('usage.description', config('catalog_copy.usage.hero'))
            ->has('relatedUsages', 1)
            ->where('relatedUsages.0.slug', 'pricing')
        );

        $this->get('/industries/saas-software')->assertInertia(fn (Assert $page) => $page
            ->where('industry.description', config('catalog_copy.industries.saas-software'))
        );

        $this->get('/industries')->assertInertia(fn (Assert $page) => $page
            ->where('industries.0.slug', 'saas-software')
            ->where('industries.0.description', config('catalog_copy.industries.saas-software'))
            ->has('industries.0.components_count')
        );
    }
}
