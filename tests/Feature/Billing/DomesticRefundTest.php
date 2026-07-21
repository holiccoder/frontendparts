<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RefundProcessedNotification;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\RefundNotAllowedException;
use App\Services\Billing\RefundService;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Domestic refunds (SPEC §7.5): full refunds go through the provider APIs
 * at the DomesticGateway seam (Alipay / WeChat Pay) inside the same
 * settings-driven 14-day window as Paddle (`billing.refund_window_days`,
 * §8.7), flip the order to Refunded, and queue the zh refund-processed
 * email (§16.1, §16.3). All provider calls are mocked at the seam — no
 * network, mirroring RefundTest's Paddle coverage.
 */
class DomesticRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_within_window_succeeds()
    {
        Notification::fake();

        $alipayOrder = $this->domesticOrder(DomesticChannel::Alipay, now()->subDays(5), 'fp1abc012345');
        $wechatOrder = $this->domesticOrder(DomesticChannel::Wechat, now()->subDays(2), 'fp2def012345');

        $this->mock(DomesticGateway::class, fn ($mock) => $mock
            ->shouldReceive('refund')
            ->twice()
            ->withArgs(fn (DomesticChannel $channel, Order $order, string $reason, string $outRefundNo): bool => $reason === 'Changed my mind'
                && str_starts_with($outRefundNo, "fpr{$order->id}")
                && ($channel === DomesticChannel::Alipay && $order->is($alipayOrder)
                    || $channel === DomesticChannel::Wechat && $order->is($wechatOrder)))
            ->andReturn(['refund_id' => '202601012200140001001']));

        app(RefundService::class)->refund($alipayOrder, 'Changed my mind');
        app(RefundService::class)->refund($wechatOrder, 'Changed my mind');

        $this->assertSame(OrderStatus::Refunded, $alipayOrder->refresh()->status);
        $this->assertSame(OrderStatus::Refunded, $wechatOrder->refresh()->status);

        // Refunded orders no longer entitle.
        $this->assertSame(
            OrderPlan::Free,
            app(EntitlementService::class)->for($alipayOrder->user)->plan(),
        );

        // The refund mail went out in zh — the domestic convention (§16.3).
        Notification::assertSentTo(
            $alipayOrder->user,
            RefundProcessedNotification::class,
            fn (RefundProcessedNotification $notification): bool => $notification->order->is($alipayOrder)
                && $notification->locale === 'zh'
                && in_array(ShouldQueue::class, class_implements($notification), true),
        );
    }

    public function test_refund_after_window_blocked()
    {
        Notification::fake();

        $order = $this->domesticOrder(DomesticChannel::Wechat, now()->subDays(30), 'fp1abc012345');

        $this->mock(DomesticGateway::class, fn ($mock) => $mock->shouldNotReceive('refund'));

        $service = app(RefundService::class);

        $this->assertFalse($service->refundable($order));

        try {
            $service->refund($order);
            $this->fail('Expected RefundNotAllowedException past the refund window.');
        } catch (RefundNotAllowedException) {
            // expected
        }

        // Nothing reached the provider and nothing changed locally.
        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        Notification::assertNotSentTo($order->user, RefundProcessedNotification::class);

        // The window is settings-driven and shared with Paddle: widening it
        // (§8.7) makes the same order refundable without a deploy.
        app(Settings::class)->set('billing.refund_window_days', 45);

        $this->assertTrue(app(RefundService::class)->refundable($order->refresh()));
    }

    public function test_admin_refund_action_works_for_domestic_orders()
    {
        Notification::fake();

        $this->mock(DomesticGateway::class, fn ($mock) => $mock
            ->shouldReceive('refund')
            ->once()
            ->andReturn(['refund_id' => '202601012200140001001']));

        $admin = Admin::factory()->create();

        $refundable = $this->domesticOrder(DomesticChannel::Alipay, now()->subDays(3), 'fp1abc012345');

        $outsideWindow = $this->domesticOrder(DomesticChannel::Alipay, now()->subDays(30), 'fp2def012345');

        $this->actingAs($admin, 'admin');

        // The action is hidden outside the refund window.
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('refund', $refundable)
            ->assertTableActionHidden('refund', $outsideWindow)
            ->callTableAction('refund', $refundable);

        $this->assertSame(OrderStatus::Refunded, $refundable->refresh()->status);
    }

    /**
     * A paid domestic order (CNY) activated at the given moment.
     */
    private function domesticOrder(DomesticChannel $channel, Carbon $startsAt, string $outTradeNo): Order
    {
        return Order::factory()->create([
            'user_id' => User::factory(),
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 518.00,
            'currency' => 'CNY',
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => $channel,
            'out_trade_no' => $outTradeNo,
            'domestic_transaction_id' => '202601012200140001',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addYear(),
        ]);
    }
}
