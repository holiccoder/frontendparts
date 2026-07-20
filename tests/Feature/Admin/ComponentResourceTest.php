<?php

namespace Tests\Feature\Admin;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Resources\Components\Pages\ListComponents;
use App\Filament\Resources\Components\Pages\ViewComponent;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Component;
use App\Models\User;
use App\Services\Catalog\CompositionTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ComponentResourceTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_CHECKLIST = [
        'viewports' => true,
        'visual_parity' => true,
        'data_separated' => true,
        'license_clean' => true,
        'accessibility' => true,
    ];

    public function test_admin_lists_and_filters_components()
    {
        $admin = Admin::factory()->create();

        $pricing = Category::factory()->usage()->create(['name' => 'Pricing']);
        $hero = Category::factory()->usage()->create(['name' => 'Hero']);

        $draft = Component::factory()->draft()->free()->section()->create([
            'usage_category_id' => $pricing->id,
        ]);
        $inReview = Component::factory()->inReview()->paid()->block()->create([
            'usage_category_id' => $hero->id,
        ]);
        $published = Component::factory()->published()->free()->element()->create([
            'usage_category_id' => $pricing->id,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListComponents::class)
            ->assertCanSeeTableRecords([$draft, $inReview, $published])
            ->filterTable('status', ComponentStatus::InReview->value)
            ->assertCanSeeTableRecords([$inReview])
            ->assertCanNotSeeTableRecords([$draft, $published])
            ->resetTableFilters()
            ->filterTable('access_level', AccessLevel::Paid->value)
            ->assertCanSeeTableRecords([$inReview])
            ->assertCanNotSeeTableRecords([$draft, $published])
            ->resetTableFilters()
            ->filterTable('level', ComponentLevel::Section->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$inReview, $published])
            ->resetTableFilters()
            ->filterTable('usage_category_id', $pricing->id)
            ->assertCanSeeTableRecords([$draft, $published])
            ->assertCanNotSeeTableRecords([$inReview])
            ->resetTableFilters()
            ->searchTable($inReview->name)
            ->assertCanSeeTableRecords([$inReview])
            ->assertCanNotSeeTableRecords([$draft, $published]);
    }

    public function test_publish_action_requires_qa_checklist_and_green_build()
    {
        Storage::fake('previews');

        $admin = Admin::factory()->create();

        $component = Component::factory()->inReview()->create([
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);

        $this->actingAs($admin, 'admin');

        // Missing checklist items → validation blocks the action.
        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->callAction('publish', data: [
                'viewports' => true,
                'visual_parity' => false,
                'data_separated' => true,
                'license_clean' => true,
                'accessibility' => true,
            ])
            ->assertHasActionErrors(['visual_parity']);

        $this->assertSame(ComponentStatus::InReview, $component->fresh()->status);

        // Full checklist but no built previews/screenshots → blocked by canPublish().
        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->callAction('publish', data: self::FULL_CHECKLIST)
            ->assertHasNoActionErrors()
            ->assertNotified('Cannot publish');

        $component->refresh();

        $this->assertSame(ComponentStatus::InReview, $component->status, 'must stay in_review while artifacts are missing');
        $this->assertNull($component->qa_checklist);

        // Fake the full preview artifact set (both frameworks + 3 screenshot widths).
        $disk = Storage::disk('previews');
        $disk->put('elements/demo-01/1.0.0/react.html', '<html>react</html>');
        $disk->put('elements/demo-01/1.0.0/vue.html', '<html>vue</html>');

        foreach (['react', 'vue'] as $framework) {
            foreach ([375, 768, 1280] as $width) {
                $disk->put("elements/demo-01/1.0.0/shots/{$framework}-{$width}.png", 'png');
            }
        }

        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->callAction('publish', data: self::FULL_CHECKLIST)
            ->assertHasNoActionErrors();

        $component->refresh();

        $this->assertSame(ComponentStatus::Published, $component->status);
        $this->assertSame(self::FULL_CHECKLIST, $component->qa_checklist);
    }

    public function test_reject_action_sets_draft_with_reason()
    {
        $admin = Admin::factory()->create();

        $component = Component::factory()->inReview()->create();

        $this->actingAs($admin, 'admin');

        // Reason is required.
        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason']);

        $this->assertSame(ComponentStatus::InReview, $component->fresh()->status);

        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->callAction('reject', data: ['reason' => 'Mobile layout broken at 375px.'])
            ->assertHasNoActionErrors();

        $component->refresh();

        $this->assertSame(ComponentStatus::Draft, $component->status);
        $this->assertSame('Mobile layout broken at 375px.', $component->review_note);
    }

    public function test_tree_visualization_payload_matches_graph()
    {
        $admin = Admin::factory()->create();

        $parent = Component::factory()->section()->create(['name' => 'Pricing Section']);
        $childA = Component::factory()->block()->create(['name' => 'Pricing Card']);
        $childB = Component::factory()->element()->create(['name' => 'Button']);

        DB::table('component_children')->insert([
            ['parent_id' => $parent->id, 'child_id' => $childA->id, 'slot' => 'default', 'sort_order' => 0],
            ['parent_id' => $parent->id, 'child_id' => $childB->id, 'slot' => 'header', 'sort_order' => 1],
            ['parent_id' => $parent->id, 'child_id' => $childB->id, 'slot' => 'footer', 'sort_order' => 2],
        ]);

        $this->actingAs($admin, 'admin');

        // The view page renders the read-only tree with child names + instance count.
        Livewire::test(ViewComponent::class, ['record' => $parent->id])
            ->assertOk()
            ->assertSee('Pricing Card')
            ->assertSee('Button')
            ->assertSee('×2');

        // The payload mirrors the component_children graph (shared child collapses to one node with instances = 2).
        $tree = app(CompositionTree::class)->for($parent);

        $this->assertSame($parent->slug, $tree['slug']);
        $this->assertCount(2, $tree['children']);

        $bySlug = collect($tree['children'])->keyBy('slug');

        $this->assertSame(1, $bySlug[$childA->slug]['instances']);
        $this->assertSame(2, $bySlug[$childB->slug]['instances'], 'two pivot rows for the same child must collapse into one node with instances = 2');
    }

    public function test_non_admin_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(ComponentResource::getUrl('index'))
            ->assertRedirect('/admin/login');

        $component = Component::factory()->create();

        $this->actingAs($user)
            ->get(ComponentResource::getUrl('view', ['record' => $component]))
            ->assertRedirect('/admin/login');
    }
}
