<?php

namespace Tests\Feature\Affiliate;

use App\Enums\BillingPeriod;
use App\Enums\CommissionStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\User;
use App\Services\Affiliates\CommissionService;
use App\Services\Billing\PaddleGateway;
use App\Services\Billing\RefundService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The commission engine (SPEC §17.2): paid orders earn a pending commission
 * at the configured rate; renewals within the recurring window keep earning
 * (lifetime is one-time); refunds void unpaid commissions; self-referral is
 * banned; the daily command flips pending → payable after the refund window
 * + holding period.
 */
class CommissionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_creates_pending_commission_at_configured_rate()
    {
        Notification::fake();

        $affiliate = Affiliate::factory()->create();
        $buyer = User::factory()->create();

        $referral = AffiliateReferral::factory()->converted($buyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        // Default knob: 30% of the net amount (SPEC §17.2).
        $order = $this->activate($this->order($buyer, '108.00'));

        $commission = AffiliateCommission::query()->where('order_id', $order->id)->sole();

        $this->assertSame($affiliate->id, $commission->affiliate_id);
        $this->assertSame($referral->id, $commission->referral_id);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertSame('32.40', $commission->amount);
        $this->assertSame('USD', $commission->currency);
        $this->assertNull($commission->payable_at);
        $this->assertNull($commission->voided_reason);

        // The rate is settings-driven — repricing takes effect without a deploy.
        app(Settings::class)->set('affiliate.commission_rate', 25);

        $second = $this->activate($this->order($buyer, '200.00'));

        $this->assertSame('50.00', AffiliateCommission::query()
            ->where('order_id', $second->id)
            ->sole()
            ->amount);
    }

    public function test_lifetime_one_time_vs_subscription_renewals_within_12_months()
    {
        Notification::fake();

        $affiliate = Affiliate::factory()->create();

        // Lifetime: one commission, ever — nothing recurs on a lifetime plan.
        $lifetimeBuyer = User::factory()->create();

        AffiliateReferral::factory()->converted($lifetimeBuyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        $lifetime = $this->activate($this->order($lifetimeBuyer, '299.00', BillingPeriod::Lifetime));

        $this->assertSame('89.70', AffiliateCommission::query()
            ->where('order_id', $lifetime->id)
            ->sole()
            ->amount);

        // Repeat activations / replays never double up (one commission per order).
        app(CommissionService::class)->attributePaidOrder($lifetime->refresh());
        app(CommissionService::class)->attributePaidOrder($lifetime->refresh());

        $this->assertSame(1, AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereHas('order', fn ($query) => $query->where('user_id', $lifetimeBuyer->id))
            ->count());

        // Subscription: renewals — new order rows per period, the domestic
        // lifecycle (SPEC §7.5) — keep earning while the recurring window
        // (first 12 months from the original conversion) is open.
        $subscriber = User::factory()->create();

        AffiliateReferral::factory()->converted($subscriber)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        $first = $this->activate($this->order($subscriber, '15.00', BillingPeriod::Monthly));

        $this->assertSame(1, AffiliateCommission::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $subscriber->id))
            ->count());

        // Month 6 — inside the window: the renewal earns again.
        $this->travel(6)->months();

        $second = $this->activate($this->order($subscriber, '15.00', BillingPeriod::Monthly));

        $this->assertSame(2, AffiliateCommission::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $subscriber->id))
            ->count());

        $renewal = AffiliateCommission::query()->where('order_id', $second->id)->sole();

        $this->assertSame($affiliate->id, $renewal->affiliate_id);
        $this->assertSame('4.50', $renewal->amount);

        // Month 13 — past 12 months from the original conversion: no more.
        $this->travel(7)->months();

        $third = $this->activate($this->order($subscriber, '15.00', BillingPeriod::Monthly));

        $this->assertSame(2, AffiliateCommission::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $subscriber->id))
            ->count());
        $this->assertNull(AffiliateCommission::query()->where('order_id', $third->id)->first());

        $this->assertTrue($first->refresh()->status === OrderStatus::Active);
    }

    public function test_refund_voids_commission()
    {
        Notification::fake();

        $affiliate = Affiliate::factory()->create();
        $buyer = User::factory()->create();

        AffiliateReferral::factory()->converted($buyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        $order = $this->activate($this->order($buyer, '108.00'), [
            'paddle_transaction_id' => 'txn_refundable_1',
        ]);

        $commission = AffiliateCommission::query()->where('order_id', $order->id)->sole();

        // The real refund flow (RefundService → provider gateway → Refunded
        // state) voids the pending commission through the shared seam.
        $this->mock(PaddleGateway::class, fn ($mock) => $mock
            ->shouldReceive('refund')
            ->once()
            ->with('txn_refundable_1', 'Customer requested a refund'));

        app(RefundService::class)->refund($order->refresh());

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
        $this->assertSame(CommissionStatus::Voided, $commission->refresh()->status);
        $this->assertSame('refunded', $commission->voided_reason);

        // A payable commission is still pre-payout — voided too.
        $payableOrder = $this->activate($this->order($buyer, '108.00'));
        $payable = AffiliateCommission::query()->where('order_id', $payableOrder->id)->sole();
        $payable->update(['status' => CommissionStatus::Payable, 'payable_at' => now()]);

        $payableOrder->update(['status' => OrderStatus::Refunded]);

        $this->assertSame(CommissionStatus::Voided, $payable->refresh()->status);

        // A paid commission is past payout — untouched (clawbacks after
        // payout are a manual, terms-backed process, SPEC §17.7).
        $paidOrder = $this->activate($this->order($buyer, '108.00'));
        $paid = AffiliateCommission::query()->where('order_id', $paidOrder->id)->sole();
        $paid->update(['status' => CommissionStatus::Paid, 'payable_at' => now()]);

        $paidOrder->update(['status' => OrderStatus::Refunded]);

        $this->assertSame(CommissionStatus::Paid, $paid->refresh()->status);
    }

    public function test_self_referral_blocked()
    {
        Notification::fake();

        // Same user account buying through their own link (SPEC §17.2).
        $affiliate = Affiliate::factory()->create();

        $ownOrder = $this->order($affiliate->user, '108.00', referralCode: $affiliate->code);

        $this->activate($ownOrder);

        $this->assertSame(0, AffiliateCommission::count());

        // Same email address on a different account (case-insensitive) is
        // banned too.
        $buyer = User::factory()->create(['email' => 'twin@example.com']);
        $twin = Affiliate::factory()->create();

        $twin->user()->update(['email' => 'TWIN@example.com']);

        $twinOrder = $this->order($buyer, '108.00', referralCode: $twin->code);

        $this->activate($twinOrder);

        $this->assertSame(0, AffiliateCommission::count());
    }

    public function test_becomes_payable_after_refund_window_and_holding()
    {
        Notification::fake();

        // Default knobs: 14-day refund window + 30-day holding = 44 days.
        $commission = AffiliateCommission::factory()->create([
            'status' => CommissionStatus::Pending,
        ]);

        $this->travel(43)->days();

        $this->artisan('affiliates:mark-payable')->assertSuccessful();

        $this->assertSame(CommissionStatus::Pending, $commission->refresh()->status);

        $this->travel(2)->days();

        $this->artisan('affiliates:mark-payable')
            ->expectsOutputToContain('1 commission(s) marked payable')
            ->assertSuccessful();

        $this->assertSame(CommissionStatus::Payable, $commission->refresh()->status);
        $this->assertNotNull($commission->payable_at);

        // Both knobs are settings-driven (SPEC §8.7): 7 + 10 = 17 days.
        $settings = app(Settings::class);
        $settings->set('billing.refund_window_days', 7);
        $settings->set('affiliate.holding_days', 10);

        $custom = AffiliateCommission::factory()->create([
            'status' => CommissionStatus::Pending,
        ]);

        $this->travel(16)->days();

        $this->artisan('affiliates:mark-payable')->assertSuccessful();

        $this->assertSame(CommissionStatus::Pending, $custom->refresh()->status);

        $this->travel(2)->days();

        $this->artisan('affiliates:mark-payable')->assertSuccessful();

        $this->assertSame(CommissionStatus::Payable, $custom->refresh()->status);

        // Payable/paid/voided commissions are never re-flipped.
        $this->assertSame(CommissionStatus::Payable, $commission->refresh()->status);
    }

    /**
     * A payable-state-ready Pending order for the buyer, activation pending.
     */
    private function order(
        User $buyer,
        string $amount,
        BillingPeriod $period = BillingPeriod::Yearly,
        ?string $referralCode = null,
    ): Order {
        return Order::factory()->create([
            'user_id' => $buyer->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => $period,
            'amount' => $amount,
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'referral_code' => $referralCode,
        ]);
    }

    /**
     * Activate the order through the shared state-machine seam — the same
     * transition the Paddle webhook, domestic notify and admin activations
     * converge on — then return it refreshed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function activate(Order $order, array $attributes = []): Order
    {
        $order->update(array_merge([
            'status' => OrderStatus::Active,
            'starts_at' => now(),
        ], $attributes));

        return $order->refresh();
    }
}
