<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entitlement resolution (order state machine): the effective plan comes
 * from the user's latest order; Active / PastDue-grace /
 * Cancelled-until-ends_at entitle, everything else falls back to Free.
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

        // A registered user with no orders is Free too.
        $user = User::factory()->create();
        $entitlement = $service->for($user);

        $this->assertSame(OrderPlan::Free, $entitlement->plan());
        $this->assertFalse($entitlement->isPaid());
    }

    public function test_paid_order_entitles()
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
    }

    public function test_latest_order_wins()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Expired,
            'created_at' => now()->subYear(),
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'created_at' => now()->subMonth(),
        ]);

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
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

        // PastDue keeps access during dunning grace.
        $pastDue = $this->subscriber(OrderPlan::Pro, OrderStatus::PastDue);
        $entitlement = $service->for($pastDue);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
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

        $this->assertTrue(app(EntitlementService::class)->for($user)->isPaid());

        // ends_at = null means there is no expiry to pass.
        $this->travel(10)->years();

        $entitlement = app(EntitlementService::class)->for($user);

        $this->assertSame(OrderPlan::Pro, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
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
