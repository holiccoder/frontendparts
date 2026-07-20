<?php

namespace Tests\Feature\Catalog;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Models\Category;
use App\Models\Component;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_and_access_and_status_enum_casts()
    {
        $component = Component::factory()->create([
            'level' => ComponentLevel::Section,
            'access_level' => AccessLevel::Paid,
            'status' => ComponentStatus::InReview,
            'deps' => ['lucide'],
        ])->fresh();

        $this->assertSame(ComponentLevel::Section, $component->level);
        $this->assertSame(AccessLevel::Paid, $component->access_level);
        $this->assertSame(ComponentStatus::InReview, $component->status);
        $this->assertSame(['lucide'], $component->deps);
    }

    public function test_industries_and_tags_many_to_many()
    {
        $component = Component::factory()->create();
        $industries = Category::factory()->count(2)->industry()->create();
        $tags = Tag::factory()->count(3)->create();

        $component->industries()->attach($industries);
        $component->tags()->attach($tags);

        $this->assertCount(2, $component->industries);
        $this->assertCount(3, $component->tags);
        $this->assertInstanceOf(Category::class, $component->industries->first());
        $this->assertInstanceOf(Tag::class, $component->tags->first());

        $this->assertTrue($industries->first()->components->contains($component));
        $this->assertTrue($tags->first()->components->contains($component));
    }

    public function test_children_and_parents_relationships()
    {
        $parent = Component::factory()->section()->create();
        $header = Component::factory()->block()->create();
        $button = Component::factory()->element()->create();

        $parent->children()->attach($header->id, ['slot' => 'header', 'sort_order' => 1]);
        $parent->children()->attach($button->id, ['slot' => null, 'sort_order' => 0]);

        $children = $parent->children;

        $this->assertCount(2, $children);
        $this->assertSame($button->id, $children->first()->id);
        $this->assertSame($header->id, $children->last()->id);
        $this->assertSame('header', $children->last()->pivot->slot);
        $this->assertSame(1, (int) $children->last()->pivot->sort_order);

        $this->assertCount(1, $header->parents);
        $this->assertTrue($header->parents->contains($parent));
        $this->assertTrue($button->parents->contains($parent));
    }

    public function test_published_scope()
    {
        Component::factory()->count(2)->published()->create();
        Component::factory()->draft()->create();
        Component::factory()->inReview()->create();

        $published = Component::published()->get();

        $this->assertCount(2, $published);
        $this->assertTrue($published->every(
            fn (Component $component): bool => $component->status === ComponentStatus::Published
        ));
    }

    public function test_free_scope()
    {
        Component::factory()->count(2)->free()->create();
        Component::factory()->count(3)->paid()->create();

        $free = Component::free()->get();

        $this->assertCount(2, $free);
        $this->assertTrue($free->every(
            fn (Component $component): bool => $component->access_level === AccessLevel::Free
        ));
    }
}
