<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BillingPeriod;
use App\Enums\ComponentEventType;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard overview (SPEC §15.4, CSR zone): plan status per effective plan
 * state (free / active / cancelled-still-valid), projects with plan-limit
 * usage, recent downloads from component events and the new-drops shelf.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_overview_props_per_plan_state()
    {
        // Free: upgrade CTA, settings-driven project limit, no license summary.
        $free = User::factory()->create();
        Project::factory()->for($free)->named('Side project')->create();

        $this->actingAs($free)
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('plan.name', 'free')
                ->where('plan.is_paid', false)
                ->where('plan.has_full_library', false)
                ->where('plan.can_scaffold', false)
                ->where('plan.license', null)
                ->where('plan.cta.kind', 'upgrade')
                ->where('plan.cta.url', route('pricing'))
                ->where('projects.total', 1)
                ->where('projects.limit', 1)
                ->has('projects.items', 1)
                ->where('projects.items.0.name', 'Side project')
                ->where('projects.items.0.components_count', 0)
                ->has('recentDownloads', 0)
            );

        // Active Starter: entitled, manage CTA, license summary with renewal.
        $starter = User::factory()->create();
        $renewsAt = now()->addYear()->startOfSecond();

        Order::factory()->create([
            'user_id' => $starter->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'starts_at' => now()->startOfSecond(),
            'ends_at' => $renewsAt,
        ]);

        $this->actingAs($starter)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('plan.name', 'starter')
                ->where('plan.is_paid', true)
                ->where('plan.has_full_library', true)
                ->where('plan.can_scaffold', false)
                ->where('plan.license.state', 'active')
                ->where('plan.license.status', 'active')
                ->where('plan.license.billing_period', 'yearly')
                ->where('plan.license.ends_at', $renewsAt->toIso8601String())
                ->where('plan.cta.kind', 'manage')
                ->where('plan.cta.url', route('dashboard.orders.index'))
                ->where('projects.limit', 3)
            );

        // Cancelled but still inside the paid term: still entitled, renew CTA.
        $cancelled = User::factory()->create();

        Order::factory()->create([
            'user_id' => $cancelled->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Cancelled,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => now()->subDays(10)->startOfSecond(),
            'ends_at' => now()->addDays(20)->startOfSecond(),
            'cancelled_at' => now()->subDay()->startOfSecond(),
        ]);

        $this->actingAs($cancelled)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('plan.name', 'starter')
                ->where('plan.is_paid', true)
                ->where('plan.license.state', 'cancelled_valid_until')
                ->where('plan.license.status', 'cancelled')
                ->where('plan.cta.kind', 'renew')
                ->where('plan.cta.url', route('pricing'))
            );
    }

    public function test_recent_downloads_are_own_events_newest_first()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $first = Component::factory()->create(['name' => 'Hero section']);
        $second = Component::factory()->create(['name' => 'Pricing block']);

        ComponentEvent::factory()->create([
            'component_id' => $first->id,
            'user_id' => $user->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(2)->startOfSecond(),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $second->id,
            'user_id' => $user->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDay()->startOfSecond(),
        ]);
        // Not downloads, and not the user's own events — both excluded.
        ComponentEvent::factory()->create([
            'component_id' => $second->id,
            'user_id' => $user->id,
            'type' => ComponentEventType::View,
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $first->id,
            'user_id' => $other->id,
            'type' => ComponentEventType::Download,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('recentDownloads', 2)
                ->where('recentDownloads.0.component.name', 'Pricing block')
                ->where('recentDownloads.0.component.url', $second->publicUrl())
                ->where('recentDownloads.1.component.name', 'Hero section')
            );
    }

    public function test_new_drops_section()
    {
        $user = User::factory()->create();

        Component::factory()->published()->create([
            'name' => 'Old drop',
            'created_at' => now()->subDays(3),
        ]);
        Component::factory()->published()->create([
            'name' => 'Fresh drop',
            'created_at' => now()->subDay(),
        ]);
        // Draft and in-review components never surface as drops.
        Component::factory()->draft()->create(['name' => 'Draft drop']);
        Component::factory()->inReview()->create(['name' => 'Review drop']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('newDrops', 2)
                ->where('newDrops.0.name', 'Fresh drop')
                ->where('newDrops.1.name', 'Old drop')
            );
    }
}
