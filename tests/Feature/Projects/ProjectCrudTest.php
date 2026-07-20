<?php

namespace Tests\Feature\Projects;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Component;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Project CRUD + per-plan limits (SPEC §6.1, §7.1, §8.7): create / rename /
 * delete with owner-only access; Free 1 / Starter 3 / Pro unlimited are
 * settings-driven, so admins retune them at runtime without a deploy.
 */
class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_rename_delete()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/dashboard/projects', ['name' => 'Marketing site']);

        $project = $user->projects()->sole();

        $response->assertRedirect(route('dashboard.projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'user_id' => $user->id,
            'name' => 'Marketing site',
        ]);

        $this->actingAs($user)
            ->patch("/dashboard/projects/{$project->id}", ['name' => 'Renamed project'])
            ->assertRedirect();

        $this->assertSame('Renamed project', $project->fresh()->name);

        // Deleting the project cascades its component set.
        $component = Component::factory()->free()->create();
        $project->components()->attach($component->id, ['is_dependency' => false]);

        $this->actingAs($user)
            ->delete("/dashboard/projects/{$project->id}")
            ->assertRedirect(route('dashboard.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_components', ['project_id' => $project->id]);
    }

    public function test_owner_only_access()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $component = Component::factory()->free()->create();
        $project->components()->attach($component->id, ['is_dependency' => false]);

        // Guests are redirected to login (HTML) or 401 (JSON).
        $this->get("/dashboard/projects/{$project->id}")->assertRedirect('/login');
        $this->postJson("/dashboard/projects/{$project->id}/components", ['component_id' => $component->id])->assertUnauthorized();

        $other = User::factory()->create();

        $this->actingAs($other)->get("/dashboard/projects/{$project->id}")->assertForbidden();
        $this->actingAs($other)->patchJson("/dashboard/projects/{$project->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/dashboard/projects/{$project->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/dashboard/projects/{$project->id}/components", ['component_id' => $component->id])->assertForbidden();
        $this->actingAs($other)->deleteJson("/dashboard/projects/{$project->id}/components/{$component->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/dashboard/projects/{$project->id}/export")->assertForbidden();

        $this->assertNotSame('Hijacked', $project->fresh()->name);

        // The list only ever shows the user's own projects.
        $this->actingAs($other)
            ->get('/dashboard/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/index')
                ->has('projects', 0)
            );
    }

    public function test_free_user_blocked_at_second_project()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/dashboard/projects', ['name' => 'One'])
            ->assertCreated();

        // Free plan: 1 project (settings default).
        $this->actingAs($user)
            ->postJson('/dashboard/projects', ['name' => 'Two'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'project_limit_reached')
            ->assertJsonPath('upgrade.pricing_url', '/pricing');

        $this->assertSame(1, $user->projects()->count());

        // Inertia form posts surface the upgrade message as a form error.
        $this->actingAs($user)
            ->post('/dashboard/projects', ['name' => 'Two'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $user->projects()->count());
    }

    public function test_starter_blocked_at_fourth()
    {
        $starter = $this->subscriber(OrderPlan::Starter);

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->actingAs($starter)
                ->postJson('/dashboard/projects', ['name' => $name])
                ->assertCreated();
        }

        $this->actingAs($starter)
            ->postJson('/dashboard/projects', ['name' => 'Four'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'project_limit_reached');

        $this->assertSame(3, $starter->projects()->count());
    }

    public function test_pro_unlimited()
    {
        $pro = $this->subscriber(OrderPlan::Pro);

        foreach (range(1, 6) as $n) {
            $this->actingAs($pro)
                ->postJson('/dashboard/projects', ['name' => "Project {$n}"])
                ->assertCreated();
        }

        $this->assertSame(6, $pro->projects()->count());
    }

    public function test_limits_change_via_settings_without_deploy()
    {
        $settings = app(Settings::class);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/dashboard/projects', ['name' => 'One'])->assertCreated();
        $this->actingAs($user)->postJson('/dashboard/projects', ['name' => 'Two'])->assertUnprocessable();

        // Admin retunes the Free limit in the panel — applies immediately.
        $settings->set('plans.project_limit.free', 2);

        $this->actingAs($user)->postJson('/dashboard/projects', ['name' => 'Two'])->assertCreated();
        $this->actingAs($user)->postJson('/dashboard/projects', ['name' => 'Three'])->assertUnprocessable();

        // Tightening again takes effect with no code change either.
        $settings->set('plans.project_limit.free', 1);

        $this->actingAs($user)->postJson('/dashboard/projects', ['name' => 'Three'])->assertUnprocessable();

        // The Starter limit is retuned independently.
        $starter = $this->subscriber(OrderPlan::Starter);

        $settings->set('plans.project_limit.starter', 1);

        $this->actingAs($starter)->postJson('/dashboard/projects', ['name' => 'One'])->assertCreated();
        $this->actingAs($starter)->postJson('/dashboard/projects', ['name' => 'Two'])->assertUnprocessable();
    }

    private function subscriber(OrderPlan $plan): User
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => OrderStatus::Active,
        ]);

        return $user;
    }
}
