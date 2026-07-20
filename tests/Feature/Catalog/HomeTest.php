<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_ssr_200()
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home'));
    }

    public function test_props_contain_featured_components_and_industries()
    {
        $this->seed(CategorySeeder::class);

        $usage = Category::query()->where('slug', 'hero')->firstOrFail();
        $industry = Category::query()->where('slug', 'saas-software')->firstOrFail();

        Component::factory()->count(8)->published()->create(['usage_category_id' => $usage->id]);
        $industry->components()->attach(
            Component::factory()->count(3)->published()->create(['usage_category_id' => $usage->id])
        );

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('featuredComponents', 6)
            ->has('featuredComponents.0.url')
            ->has('featuredComponents.0.usage')
            ->has('industries', 1)
            ->where('industries.0.slug', 'saas-software')
            ->where('industries.0.components_count', 3)
            ->has('pricing')
            ->has('latestComponents', 6)
            ->has('posts')
            ->has('meta')
        );
    }

    public function test_industries_below_3_components_excluded()
    {
        $this->seed(CategorySeeder::class);

        $usage = Category::query()->where('slug', 'hero')->firstOrFail();

        Category::query()->where('slug', 'saas-software')->firstOrFail()
            ->components()->attach(
                Component::factory()->count(3)->published()->create(['usage_category_id' => $usage->id])
            );

        Category::query()->where('slug', 'education')->firstOrFail()
            ->components()->attach(
                Component::factory()->count(2)->published()->create(['usage_category_id' => $usage->id])
            );

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('industries', 1)
            ->where('industries.0.slug', 'saas-software')
        );
    }
}
