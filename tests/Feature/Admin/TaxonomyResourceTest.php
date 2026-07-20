<?php

namespace Tests\Feature\Admin;

use App\Enums\CategoryType;
use App\Filament\Pages\Sources;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Component;
use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaxonomyResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_crud()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'type' => CategoryType::Usage->value,
                'zone' => 'Conversion',
                'name' => 'Exit Intent',
                'slug' => 'exit-intent',
                'sort_order' => 40,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'exit-intent')->sole();

        $this->assertSame(CategoryType::Usage, $category->type);
        $this->assertSame('Conversion', $category->zone);

        // Slug is unique per type.
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'type' => CategoryType::Usage->value,
                'zone' => 'Conversion',
                'name' => 'Exit Intent Duplicate',
                'slug' => 'exit-intent',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $other = Category::factory()->industry()->create();

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$category, $other])
            ->filterTable('type', CategoryType::Industry->value)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$category]);

        // Type is locked after create; zone stays editable.
        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->fillForm([
                'zone' => 'Retention',
                'name' => 'Exit Intent Modal',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();

        $this->assertSame('Retention', $category->zone);
        $this->assertSame('Exit Intent Modal', $category->name);
        $this->assertSame(CategoryType::Usage, $category->type, 'type must not change on edit');
    }

    public function test_tag_crud()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateTag::class)
            ->fillForm([
                'name' => 'Gradient',
                'slug' => 'gradient',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tag = Tag::query()->where('slug', 'gradient')->sole();

        Livewire::test(CreateTag::class)
            ->fillForm([
                'name' => 'Gradient Again',
                'slug' => 'gradient',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::test(ListTags::class)
            ->assertCanSeeTableRecords([$tag]);

        Livewire::test(EditTag::class, ['record' => $tag->id])
            ->fillForm([
                'name' => 'Gradient Mesh',
                'slug' => 'gradient-mesh',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Gradient Mesh',
            'slug' => 'gradient-mesh',
        ]);
    }

    public function test_source_page_lists_distinct_citations_with_counts()
    {
        $admin = Admin::factory()->create();

        Component::factory()->count(2)->create([
            'source_name' => 'Dribbble',
            'source_url' => 'https://dribbble.com',
        ]);
        Component::factory()->create([
            'source_name' => 'Awwwards',
            'source_url' => 'https://awwwards.com',
        ]);
        Component::factory()->create([
            'source_name' => null,
            'source_url' => null,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(Sources::class)
            ->assertOk()
            ->assertSee('Dribbble')
            ->assertSee('https://dribbble.com')
            ->assertSee('Awwwards');

        $sources = Livewire::test(Sources::class)->instance()->sources();

        $this->assertCount(2, $sources, 'null-source components must not appear');

        $dribbble = $sources->firstWhere('source_name', 'Dribbble');

        $this->assertSame(2, (int) $dribbble->components_count);
        $this->assertNotNull($dribbble->latest_added_at);
    }

    public function test_navigation_groups_registered()
    {
        $groups = collect(Filament::getPanel('admin')->getNavigationGroups())
            ->map(fn (NavigationGroup|string $group): ?string => $group instanceof NavigationGroup ? $group->getLabel() : $group)
            ->all();

        $this->assertContains('Library', $groups);
        $this->assertContains('Manage', $groups);
        $this->assertContains('System', $groups);
    }
}
