<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entitlement resolution (SPEC §7.1 feature matrix, §7.3 order states,
 * §8.7 settings-driven limits): the effective plan comes from the user's
 * latest order; Active / PastDue-grace / Cancelled-until-ends_at entitle,
 * everything else falls back to Free.
 */
class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_entitlements()
    {
        $service = app(EntitlementService::class);

        // Guests resolve to a null-safe Free entitlement.
        $guest = $service->for(null);

        $this->assertSame(OrderPlan::Free, $guest->plan());
        $this->assertFalse($guest->isPaid());
        $this->assertFalse($guest->hasFullLibrary());
        $this->assertFalse($guest->canScaffold());
        $this->assertSame(1, $guest->projectLimit());

        // A registered user with no orders is Free too.
        $user = User::factory()->create();
        $entitlement = $service->for($user);

        $this->assertSame(OrderPlan::Free, $entitlement->plan());
        $this->assertFalse($entitlement->isPaid());
        $this->assertFalse($entitlement->hasFullLibrary());
        $this->assertFalse($entitlement->canScaffold());
        $this->assertSame(1, $entitlement->projectLimit());
    }

    public function test_starter_full_library_no_scaffolding()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Starter, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertFalse($entitlement->canScaffold());
        $this->assertSame(3, $entitlement->projectLimit());
    }

    public function test_pro_full_library_with_scaffolding()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
        $this->assertNull($entitlement->projectLimit());
    }

    public function test_cancelled_but_not_ended_keeps_access()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(20),
        ]);

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
    }

    public function test_expired_loses_paid_access()
    {
        $service = app(EntitlementService::class);

        $expired = User::factory()->create();
        Order::factory()->create([
            'user_id' => $expired->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Expired,
            'ends_at' => now()->subDay(),
        ]);

        $entitlement = $service->for($expired);

        $this->assertSame(OrderPlan::Free, $entitlement->plan());
        $this->assertFalse($entitlement->isPaid());
        $this->assertFalse($entitlement->hasFullLibrary());
        $this->assertFalse($entitlement->canScaffold());
        $this->assertSame(1, $entitlement->projectLimit());

        // Cancelled with ends_at already past is Free as well.
        $lapsed = User::factory()->create();
        Order::factory()->create([
            'user_id' => $lapsed->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(OrderPlan::Free, $service->for($lapsed)->plan());

        // A pending (unpaid) order never entitles.
        $pending = User::factory()->create();
        Order::factory()->create([
            'user_id' => $pending->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertSame(OrderPlan::Free, $service->for($pending)->plan());
    }

    public function test_refunded_loses_paid_access()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Refunded,
        ]);

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Free, $entitlement->plan());
        $this->assertFalse($entitlement->isPaid());
        $this->assertFalse($entitlement->hasFullLibrary());
        $this->assertFalse($entitlement->canScaffold());
    }

    public function test_lifetime_never_expires()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'starts_at' => now()->subYear(),
            'ends_at' => null,
        ]);

        $this->assertTrue(app(EntitlementService::class)->for($user)->hasFullLibrary());

        // ends_at = null means there is no expiry to pass.
        $this->travel(10)->years();

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
    }

    public function test_project_limits_read_from_settings()
    {
        $settings = app(Settings::class);

        $user = User::factory()->create();

        $this->assertSame(1, app(EntitlementService::class)->for($user)->projectLimit());
        $this->assertSame(3, app(EntitlementService::class)->for($this->subscriber(OrderPlan::Starter))->projectLimit());
        $this->assertNull(app(EntitlementService::class)->for($this->subscriber(OrderPlan::Pro))->projectLimit());

        // Admin re-tunes the limits — reflected without a deploy.
        $settings->set('plans.project_limit.free', 2);
        $settings->set('plans.project_limit.starter', 5);
        $settings->set('plans.project_limit.pro', 20);

        $this->assertSame(2, app(EntitlementService::class)->for($user)->projectLimit());
        $this->assertSame(5, app(EntitlementService::class)->for($this->subscriber(OrderPlan::Starter))->projectLimit());
        $this->assertSame(20, app(EntitlementService::class)->for($this->subscriber(OrderPlan::Pro))->projectLimit());

        // PastDue keeps full access during dunning grace.
        $pastDue = $this->subscriber(OrderPlan::Pro, OrderStatus::PastDue);
        $entitlement = app(EntitlementService::class)->for($pastDue);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
    }

    private function subscriber(OrderPlan $plan, OrderStatus $status = OrderStatus::Active): User
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => $status,
        ]);

        return $user;
    }
}
