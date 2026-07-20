<?php

namespace Tests\Feature\Projects;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Component;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard project pages (SPEC §15.4, CSR zone): `/dashboard/projects` lists
 * the user's projects with plan-limit usage; `/dashboard/projects/{id}` shows
 * the component set with direct picks vs auto-added dependencies clearly
 * marked, plus the pack-zip export placeholder (2.5).
 */
class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_detail_render_with_props()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->named('Marketing site')->create();

        $this->actingAs($user)
            ->get('/dashboard/projects')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/index')
                ->has('projects', 1)
                ->where('projects.0.name', 'Marketing site')
                ->where('projects.0.components_count', 0)
                ->where('projects.0.url', route('dashboard.projects.show', $project))
                ->where('limits.plan', 'free')
                ->where('limits.limit', 1)
                ->where('limits.used', 1)
            );

        $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}")
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/show')
                ->where('project.id', $project->id)
                ->where('project.name', 'Marketing site')
                ->has('components', 0)
                ->where('export.url', route('dashboard.projects.export', $project))
                ->where('export.available', false)
            );
    }

    public function test_detail_marks_dependencies()
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);
        $project = Project::factory()->for($user)->named('Marketing site')->create();

        // Paid composite with a paid child — a full-library plan adds anything.
        $child = Component::factory()->paid()->create(['name' => 'Card', 'slug' => 'blocks/card-01']);
        $parent = Component::factory()->paid()->create(['name' => 'Hero', 'slug' => 'sections/hero-01']);

        DB::table('component_children')->insert([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'slot' => 'default',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->postJson("/dashboard/projects/{$project->id}/components", ['component_id' => $parent->id])
            ->assertCreated();

        // Direct picks first, dependencies after — each row marked.
        $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/show')
                ->has('components', 2)
                ->where('components.0.name', 'Hero')
                ->where('components.0.slug', 'sections/hero-01')
                ->where('components.0.basename', 'hero-01')
                ->where('components.0.is_dependency', false)
                ->where('components.1.name', 'Card')
                ->where('components.1.is_dependency', true)
            );
    }

    public function test_other_users_project_403()
    {
        $project = Project::factory()->create();

        $this->get("/dashboard/projects/{$project->id}")->assertRedirect('/login');
        $this->get('/dashboard/projects')->assertRedirect('/login');

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get("/dashboard/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_export_stub_501_until_pack_zip_ships()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson("/dashboard/projects/{$project->id}/export")
            ->assertStatus(501)
            ->assertJsonPath('error', 'export_not_implemented');

        $this->actingAs($user)
            ->post("/dashboard/projects/{$project->id}/export")
            ->assertSessionHasErrors('export');
    }
}
