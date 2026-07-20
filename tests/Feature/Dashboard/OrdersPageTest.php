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
 * Orders page (SPEC §15.4, CSR zone): the user's orders newest-first with
 * Paddle receipt/invoice URLs, the derived license state and renewal or
 * expiry dates (lifetime licenses never expire).
 */
class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_listed_with_receipt_urls()
    {
        config(['cashier.sandbox' => true]);

        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Pending,
            'paddle_transaction_id' => null,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'paddle_transaction_id' => 'txn_01jtestexample0001',
        ]);

        // Newest first: the active order (created last) leads the list.
        $this->actingAs($user)
            ->get('/dashboard/orders')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/orders')
                ->has('orders', 2)
                ->where('orders.0.plan', 'pro')
                ->where('orders.0.status', 'active')
                ->where('orders.0.receipt_url', 'https://sandbox-vendors.paddle.com/transactions-v2/txn_01jtestexample0001')
                ->where('orders.1.plan', 'starter')
                ->where('orders.1.status', 'pending')
                ->where('orders.1.receipt_url', null)
            );
    }

    public function test_license_state_and_renewal_dates()
    {
        $user = User::factory()->create();

        $renewsAt = now()->addMonth()->startOfSecond();
        $validUntil = now()->addDays(20)->startOfSecond();

        // Creation order — the page lists them in reverse (newest first).
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'amount' => 9,
            'starts_at' => now()->startOfSecond(),
            'ends_at' => $renewsAt,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::PastDue,
            'billing_period' => BillingPeriod::Monthly,
            'ends_at' => now()->addWeek()->startOfSecond(),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Cancelled,
            'billing_period' => BillingPeriod::Yearly,
            'ends_at' => $validUntil,
            'cancelled_at' => now()->subDay()->startOfSecond(),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Cancelled,
            'billing_period' => BillingPeriod::Monthly,
            'ends_at' => now()->subDays(5)->startOfSecond(),
            'cancelled_at' => now()->subDays(10)->startOfSecond(),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Refunded,
            'billing_period' => BillingPeriod::Yearly,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'ends_at' => null,
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/dashboard/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/orders')
                ->has('orders', 7)
                // Pending — payment still confirming.
                ->where('orders.0.license_state', 'pending')
                ->where('orders.0.ends_at', null)
                // Lifetime active — never expires, no renewal date.
                ->where('orders.1.license_state', 'active')
                ->where('orders.1.is_lifetime', true)
                ->where('orders.1.ends_at', null)
                // Refunded.
                ->where('orders.2.license_state', 'refunded')
                // Cancelled past the paid term — access expired.
                ->where('orders.3.license_state', 'expired')
                // Cancelled inside the paid term — valid until ends_at.
                ->where('orders.4.license_state', 'cancelled_valid_until')
                ->where('orders.4.ends_at', $validUntil->toIso8601String())
                // Past due — dunning grace still entitles (§7.3).
                ->where('orders.5.license_state', 'past_due')
                // Active subscription — renews at ends_at.
                ->where('orders.6.license_state', 'active')
                ->where('orders.6.is_lifetime', false)
                ->where('orders.6.amount', '9.00')
                ->where('orders.6.ends_at', $renewsAt->toIso8601String())
            );
    }

    public function test_only_own_orders()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);
        Order::factory()->create([
            'user_id' => $other->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->get('/dashboard/orders')->assertRedirect('/login');

        $this->actingAs($user)
            ->get('/dashboard/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/orders')
                ->has('orders', 2)
                ->where('orders.0.plan', 'starter')
                ->where('orders.1.plan', 'starter')
            );
    }
}
