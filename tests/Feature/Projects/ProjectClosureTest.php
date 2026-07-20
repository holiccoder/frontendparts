<?php

namespace Tests\Feature\Projects;

use App\Models\Component;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Auto-add closure + removal cascade (SPEC §6.1): adding a composite inserts
 * its full descendant closure as deduplicated dependencies; removing a direct
 * pick prunes dependencies no remaining direct pick needs, with a user
 * notice; shared children stay.
 */
class ProjectClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_adding_composite_adds_full_descendant_closure()
    {
        // Section → Block → Element composition chain.
        [$section, $block, $element] = $this->chain('Hero', 'Card', 'Button');

        $this->add($section)
            ->assertCreated()
            ->assertJsonPath('components_count', 3);

        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $section->id,
            'is_dependency' => false,
        ]);
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $block->id,
            'is_dependency' => true,
        ]);
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $element->id,
            'is_dependency' => true,
        ]);

        $this->assertSame(3, $this->project->components()->count());
    }

    public function test_shared_children_deduplicated()
    {
        // Two sections share the same card child (SPEC §2.2 shared children).
        [$card, $x, $z] = [$this->leaf('Card'), $this->leaf('Badge'), $this->leaf('Icon')];
        $sectionA = $this->composite('Hero A', [$card, $x]);
        $sectionB = $this->composite('Hero B', [$card, $z]);

        $this->add($sectionA)->assertCreated()->assertJsonPath('components_count', 3);
        $this->add($sectionB)->assertCreated()->assertJsonPath('components_count', 5);

        // The shared child exists exactly once and stays a dependency.
        $this->assertSame(1, DB::table('project_components')
            ->where('project_id', $this->project->id)
            ->where('component_id', $card->id)
            ->count());
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $card->id,
            'is_dependency' => true,
        ]);

        $this->assertSame(5, $this->project->components()->count());
    }

    public function test_removal_prunes_orphaned_dependencies()
    {
        [$section, $block, $element] = $this->chain('Hero', 'Card', 'Button');

        $this->add($section)->assertCreated();
        $this->assertSame(3, $this->project->components()->count());

        $this->remove($section)->assertOk();

        // The direct pick and its whole orphaned closure are gone.
        $this->assertSame(0, $this->project->components()->count());
        $this->assertDatabaseMissing('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $block->id,
        ]);
        $this->assertDatabaseMissing('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $element->id,
        ]);

        // A dependency cannot be removed directly (404) — it follows the cascade.
        $this->add($section)->assertCreated();

        $this->actingAs($this->user)
            ->deleteJson("/dashboard/projects/{$this->project->id}/components/{$block->id}")
            ->assertNotFound();
    }

    public function test_dependencies_used_elsewhere_are_kept()
    {
        [$card, $x, $z] = [$this->leaf('Card'), $this->leaf('Badge'), $this->leaf('Icon')];
        $sectionA = $this->composite('Hero A', [$card, $x]);
        $sectionB = $this->composite('Hero B', [$card, $z]);

        $this->add($sectionA)->assertCreated();
        $this->add($sectionB)->assertCreated();

        $this->remove($sectionA)->assertOk();

        // The exclusive child of A is pruned…
        $this->assertDatabaseMissing('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $x->id,
        ]);

        // …but the shared child and B's own closure stay.
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $sectionB->id,
            'is_dependency' => false,
        ]);
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $card->id,
            'is_dependency' => true,
        ]);
        $this->assertDatabaseHas('project_components', [
            'project_id' => $this->project->id,
            'component_id' => $z->id,
            'is_dependency' => true,
        ]);

        $this->assertSame(3, $this->project->components()->count());
    }

    public function test_prune_notice_returned_in_response()
    {
        $card = $this->leaf('Card');
        $button = $this->leaf('Button');
        $hero = $this->composite('Hero', [$card, $button]);

        $this->add($hero)->assertCreated();

        $response = $this->remove($hero)->assertOk()->assertJsonCount(2, 'pruned');

        $notice = $response->json('notice');

        $this->assertStringContainsString('Removed Hero', $notice);
        $this->assertStringContainsString('2 unused dependencies', $notice);
        $this->assertStringContainsString('Card', $notice);
        $this->assertStringContainsString('Button', $notice);

        // Singular grammar for a single pruned dependency.
        $icon = $this->leaf('Icon');
        $banner = $this->composite('Banner', [$icon]);

        $this->add($banner)->assertCreated();

        $response = $this->remove($banner)->assertOk()->assertJsonCount(1, 'pruned');

        $this->assertStringContainsString('1 unused dependency', $response->json('notice'));
        $this->assertStringContainsString('Icon', $response->json('notice'));

        // Removing a primitive reports no pruned dependencies.
        $this->add($card)->assertCreated();

        $response = $this->remove($card)->assertOk()->assertJsonCount(0, 'pruned');

        $this->assertSame('Removed Card.', $response->json('notice'));
    }

    /**
     * @return array{Component, Component, Component} section → block → element
     */
    private function chain(string $sectionName, string $blockName, string $elementName): array
    {
        $element = $this->leaf($elementName);
        $block = $this->composite($blockName, [$element]);
        $section = $this->composite($sectionName, [$block]);

        return [$section, $block, $element];
    }

    /**
     * @param  list<Component>  $children
     */
    private function composite(string $name, array $children): Component
    {
        $parent = $this->leaf($name);

        foreach ($children as $index => $child) {
            DB::table('component_children')->insert([
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'slot' => 'default',
                'sort_order' => $index,
            ]);
        }

        return $parent;
    }

    private function leaf(string $name): Component
    {
        return Component::factory()->free()->create(['name' => $name]);
    }

    private function add(Component $component): TestResponse
    {
        return $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/components", ['component_id' => $component->id]);
    }

    private function remove(Component $component): TestResponse
    {
        return $this->actingAs($this->user)
            ->deleteJson("/dashboard/projects/{$this->project->id}/components/{$component->id}");
    }
}
