<?php

namespace Tests\Feature\Billing;

use App\Enums\ComponentEventType;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Component;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gating enforcement (SPEC §7.1, FR-7.6, §5.4 blur-gate): free users are
 * limited to the free component subset; paid downloads and paid modal
 * payloads require a full-library entitlement (Starter/Pro) and answer 403
 * with an upgrade payload otherwise. Blur-gate hits are recorded as
 * component events for the B2 upgrade trigger (SPEC §16.2).
 */
class GatingTest extends TestCase
{
    use RefreshDatabase;

    private Category $usage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usage = Category::factory()->usage()->create(['slug' => 'hero']);
    }

    public function test_free_user_paid_download_403()
    {
        $this->paidComponent();

        // An authenticated user without an entitled order is Free.
        $this->actingAs(User::factory()->create())
            ->getJson('/components/hero/paid-01/download')
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required');

        // No download event is recorded for a blocked request.
        $this->assertDatabaseMissing('component_events', [
            'type' => ComponentEventType::Download->value,
        ]);
    }

    public function test_starter_downloads_any_component()
    {
        $paid = $this->paidComponent();

        $starter = User::factory()->create();
        Order::factory()->create([
            'user_id' => $starter->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($starter)
            ->get('/components/hero/paid-01/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $this->assertDatabaseHas('component_events', [
            'component_id' => $paid->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => $starter->id,
        ]);
    }

    public function test_free_user_downloads_free_component()
    {
        $free = Component::factory()->published()->free()->create([
            'slug' => 'elements/free-01',
            'usage_category_id' => $this->usage->id,
        ]);

        // Guest…
        $this->get('/components/hero/free-01/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        // …and authenticated Free user alike.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/components/hero/free-01/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $this->assertDatabaseHas('component_events', [
            'component_id' => $free->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('component_events', [
            'component_id' => $free->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_free_user_adds_only_free_components_to_project()
    {
        $free = Component::factory()->published()->free()->create([
            'slug' => 'elements/free-01',
            'usage_category_id' => $this->usage->id,
        ]);
        $paid = $this->paidComponent();

        // An authenticated user without an entitled order is Free.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Free components attach (with their closure)…
        $this->actingAs($user)
            ->postJson("/dashboard/projects/{$project->id}/components", ['component_id' => $free->id])
            ->assertCreated();

        $this->assertDatabaseHas('project_components', [
            'project_id' => $project->id,
            'component_id' => $free->id,
            'is_dependency' => false,
        ]);

        // …but a paid component answers the 403 upgrade payload.
        $this->actingAs($user)
            ->postJson("/dashboard/projects/{$project->id}/components", ['component_id' => $paid->id])
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required')
            ->assertJsonPath('upgrade.pricing_url', '/pricing');

        $this->assertDatabaseMissing('project_components', [
            'project_id' => $project->id,
            'component_id' => $paid->id,
        ]);

        // A full-library plan adds paid components too.
        $starter = User::factory()->create();
        Order::factory()->create([
            'user_id' => $starter->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);
        $starterProject = Project::factory()->for($starter)->create();

        $this->actingAs($starter)
            ->postJson("/dashboard/projects/{$starterProject->id}/components", ['component_id' => $paid->id])
            ->assertCreated();

        $this->assertDatabaseHas('project_components', [
            'project_id' => $starterProject->id,
            'component_id' => $paid->id,
            'is_dependency' => false,
        ]);
    }

    public function test_403_payload_contains_plan_comparison_cta()
    {
        $this->paidComponent();

        $this->getJson('/components/hero/paid-01/download')
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/components/hero/paid-01/download')
            ->assertForbidden()
            ->assertJsonStructure([
                'error',
                'upgrade' => ['cta', 'pricing_url'],
            ])
            ->assertJsonPath('upgrade.pricing_url', '/pricing');
    }

    public function test_paid_component_modal_entitled_false_for_free_user()
    {
        $this->paidComponent();

        Component::factory()->published()->free()->create([
            'slug' => 'elements/free-01',
            'usage_category_id' => $this->usage->id,
        ]);

        // Guest: paid modal payload is gated…
        $this->getJson('/api/components/hero/paid-01')
            ->assertOk()
            ->assertJsonPath('access', 'paid')
            ->assertJsonPath('entitled', false);

        // …so is an authenticated user without a paid plan…
        $this->actingAs(User::factory()->create())
            ->getJson('/api/components/hero/paid-01')
            ->assertOk()
            ->assertJsonPath('entitled', false);

        // …while a full-library plan is entitled.
        $pro = User::factory()->create();
        Order::factory()->create([
            'user_id' => $pro->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($pro)
            ->getJson('/api/components/hero/paid-01')
            ->assertOk()
            ->assertJsonPath('entitled', true);

        // Free components are always entitled, even for guests.
        $this->getJson('/api/components/hero/free-01')
            ->assertOk()
            ->assertJsonPath('access', 'free')
            ->assertJsonPath('entitled', true);
    }

    public function test_pro_blur_gate_event_recorded_for_b2_trigger()
    {
        $paid = $this->paidComponent();

        // Guests trip the blur gate (user_id nullable)…
        $this->getJson('/api/components/hero/paid-01')->assertOk();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $paid->id,
            'type' => ComponentEventType::GateHit->value,
            'user_id' => null,
        ]);

        // …and so does a non-entitled authenticated user…
        $freeUser = User::factory()->create();

        $this->actingAs($freeUser)->getJson('/api/components/hero/paid-01')->assertOk();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $paid->id,
            'type' => ComponentEventType::GateHit->value,
            'user_id' => $freeUser->id,
        ]);

        // …while an entitled user never records a gate hit.
        $pro = User::factory()->create();
        Order::factory()->create([
            'user_id' => $pro->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($pro)->getJson('/api/components/hero/paid-01')->assertOk();

        $this->assertDatabaseMissing('component_events', [
            'component_id' => $paid->id,
            'type' => ComponentEventType::GateHit->value,
            'user_id' => $pro->id,
        ]);

        $this->assertSame(2, $paid->events()->where('type', ComponentEventType::GateHit)->count());
    }

    private function paidComponent(): Component
    {
        return Component::factory()->published()->paid()->create([
            'slug' => 'elements/paid-01',
            'usage_category_id' => $this->usage->id,
        ]);
    }
}
