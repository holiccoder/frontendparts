<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard overview (CSR zone): plan status per effective plan state
 * (free / active / cancelled-still-valid) plus the orders summary.
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
        // Free: upgrade CTA, no license summary, empty orders summary.
        $free = User::factory()->create();

        $this->actingAs($free)
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('plan.name', 'free')
                ->where('plan.is_paid', false)
                ->where('plan.license', null)
                ->where('plan.cta.kind', 'upgrade')
                ->where('plan.cta.url', route('pricing'))
                ->where('orders.total', 0)
                ->has('orders.items', 0)
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
                ->where('plan.license.state', 'active')
                ->where('plan.license.status', 'active')
                ->where('plan.license.billing_period', 'yearly')
                ->where('plan.license.ends_at', $renewsAt->toIso8601String())
                ->where('plan.cta.kind', 'manage')
                ->where('plan.cta.url', route('dashboard.orders.index'))
                ->where('orders.total', 1)
                ->has('orders.items', 1)
                ->where('orders.items.0.plan', 'starter')
                ->where('orders.items.0.status', 'active')
                ->where('orders.index_url', route('dashboard.orders.index'))
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
}
