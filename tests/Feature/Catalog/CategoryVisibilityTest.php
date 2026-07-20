<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_hidden_below_3_components()
    {
        $category = Category::factory()->industry()->create();
        $category->components()->attach(
            Component::factory()->count(2)->published()->create()
        );

        $this->assertFalse(Category::visible()->whereKey($category->id)->exists());
    }

    public function test_visible_at_3_components()
    {
        $category = Category::factory()->industry()->create();
        $category->components()->attach(
            Component::factory()->count(3)->published()->create()
        );

        $this->assertTrue(Category::visible()->whereKey($category->id)->exists());
    }

    public function test_visible_via_usage_category_at_3_components()
    {
        $category = Category::factory()->usage('Navigation')->create();
        Component::factory()->count(3)->published()->create([
            'usage_category_id' => $category->id,
        ]);

        $this->assertTrue(Category::visible()->whereKey($category->id)->exists());
    }

    public function test_draft_components_do_not_count_toward_visibility()
    {
        $category = Category::factory()->industry()->create();
        $category->components()->attach(Component::factory()->count(2)->published()->create());
        $category->components()->attach(Component::factory()->draft()->create());

        $this->assertFalse(Category::visible()->whereKey($category->id)->exists());
    }
}
