<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_only()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->count(3)->published()->create(['usage_category_id' => $usage->id]);
        Component::factory()->count(2)->draft()->create(['usage_category_id' => $usage->id]);

        $this->get('/components')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/index')
                ->has('components.data', 3)
                ->where('components.meta.total', 3)
            );
    }

    public function test_filter_by_industry_multi()
    {
        $usage = Category::factory()->usage()->create();

        $saas = Category::factory()->industry()->create(['slug' => 'saas-software', 'name' => 'SaaS & Software']);
        $education = Category::factory()->industry()->create(['slug' => 'education', 'name' => 'Education']);
        $fintech = Category::factory()->industry()->create(['slug' => 'fintech-finance', 'name' => 'Fintech & Finance']);

        $inSaas = Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Saas hero']);
        $inSaas->industries()->attach($saas);

        $inEducation = Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Education hero']);
        $inEducation->industries()->attach($education);

        $inFintech = Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Fintech hero']);
        $inFintech->industries()->attach($fintech);

        $this->get('/components?industry[]=saas-software')
            ->assertInertia(fn (Assert $page) => $page
                ->has('components.data', 1)
                ->where('components.data.0.name', 'Saas hero')
            );

        $this->get('/components?industry[]=saas-software&industry[]=education')
            ->assertInertia(fn (Assert $page) => $page
                ->has('components.data', 2)
                ->where('active.industry', ['saas-software', 'education'])
            );
    }

    public function test_filter_by_usage_level_access()
    {
        $hero = Category::factory()->usage()->create(['slug' => 'hero']);
        $pricing = Category::factory()->usage()->create(['slug' => 'pricing']);

        Component::factory()->published()->section()->free()->create(['usage_category_id' => $hero->id, 'name' => 'Hero section']);
        Component::factory()->published()->block()->paid()->create(['usage_category_id' => $hero->id, 'name' => 'Hero block']);
        Component::factory()->published()->section()->free()->create(['usage_category_id' => $pricing->id, 'name' => 'Pricing section']);

        $this->get('/components?usage=hero')
            ->assertInertia(fn (Assert $page) => $page->has('components.data', 2));

        $this->get('/components?level=section')
            ->assertInertia(fn (Assert $page) => $page->has('components.data', 2));

        $this->get('/components?access=free')
            ->assertInertia(fn (Assert $page) => $page->has('components.data', 2));

        $this->get('/components?usage=hero&level=section&access=free')
            ->assertInertia(fn (Assert $page) => $page
                ->has('components.data', 1)
                ->where('components.data.0.name', 'Hero section')
                ->where('active.usage', 'hero')
                ->where('active.level', 'section')
                ->where('active.access', 'free')
            );
    }

    public function test_search_matches_name_and_tags()
    {
        $usage = Category::factory()->usage()->create();

        Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Glassmorphic banner']);

        $tagged = Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Plain section']);
        $tagged->tags()->attach(Tag::factory()->create(['name' => 'aurora-gradient', 'slug' => 'aurora-gradient']));

        Component::factory()->published()->create(['usage_category_id' => $usage->id, 'name' => 'Unrelated thing']);

        $this->get('/components?q=glassmorphic')
            ->assertInertia(fn (Assert $page) => $page
                ->has('components.data', 1)
                ->where('components.data.0.name', 'Glassmorphic banner')
            );

        $this->get('/components?q=aurora')
            ->assertInertia(fn (Assert $page) => $page
                ->has('components.data', 1)
                ->where('components.data.0.name', 'Plain section')
            );
    }

    public function test_empty_category_hidden()
    {
        $hero = Category::factory()->usage()->create(['slug' => 'hero']);
        $footer = Category::factory()->usage()->create(['slug' => 'footer']);

        // Hero holds 5 published components → visible in the usage filter list.
        $saas = Category::factory()->industry()->create(['slug' => 'saas-software']);
        $saas->components()->attach(
            Component::factory()->count(3)->published()->create(['usage_category_id' => $hero->id])
        );

        // Below the 3-component threshold → hidden from filter lists (SPEC §4.3).
        $education = Category::factory()->industry()->create(['slug' => 'education']);
        $education->components()->attach(
            Component::factory()->count(2)->published()->create(['usage_category_id' => $hero->id])
        );

        Component::factory()->published()->create(['usage_category_id' => $footer->id]);

        $this->get('/components')
            ->assertInertia(fn (Assert $page) => $page
                ->has('filters.industries', 1)
                ->where('filters.industries.0.slug', 'saas-software')
                ->has('filters.usages', 1)
                ->where('filters.usages.0.slug', 'hero')
            );
    }
}
