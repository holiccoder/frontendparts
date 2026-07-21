<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Enums\DomesticTradeStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\DomesticEvent;
use App\Models\Order;
use App\Models\User;
use App\Notifications\WelcomeToProNotification;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\DomesticTradeResult;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\InvalidDomesticSignatureException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Domestic notify normalization (SPEC §7.5): the Alipay / WeChat notify
 * endpoints verify the provider signature at the DomesticGateway seam,
 * normalize the trade into the shared orders state machine (paid → Active
 * with a period-based ends_at, lifetime → ends_at null — the same rules as
 * Paddle), and stay idempotent on replayed notifications via
 * domestic_events. The QR page's polling endpoint reports the order state
 * and live-queries the provider while the order is unpaid.
 */
class DomesticNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_alipay_signed_notify_activates_order()
    {
        Notification::fake();

        $order = $this->domesticOrder(BillingPeriod::Yearly);

        $paidAt = now()->startOfSecond();

        $this->fakeGateway(
            alipayTrade: new DomesticTradeResult(DomesticChannel::Alipay, 'fp1abc012345', '202601012200140001', DomesticTradeStatus::Paid, '518.00', 'notify-ali-1', $paidAt),
        );

        $this->post('/pay/domestic/alipay/notify', [
            'notify_id' => 'notify-ali-1',
            'out_trade_no' => 'fp1abc012345',
            'trade_status' => 'TRADE_SUCCESS',
        ])->assertOk()->assertSee('success');

        $order->refresh();

        $this->assertSame(OrderStatus::Active, $order->status);
        $this->assertTrue($order->starts_at->equalTo($paidAt));
        $this->assertTrue($order->ends_at->equalTo($paidAt->copy()->addYear()));
        $this->assertNull($order->cancelled_at);
        $this->assertSame('202601012200140001', $order->domestic_transaction_id);
        $this->assertSame(DomesticChannel::Alipay, $order->domestic_channel);

        // The user is entitled immediately — the domestic order landed in
        // the shared state machine.
        $this->assertSame(OrderPlan::Pro, app(EntitlementService::class)->for($order->user)->plan());

        // OrderObserver side effects fire for domestic orders too: the
        // order-paid welcome mail went out exactly once.
        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );

        $this->assertSame(1, DomesticEvent::count());

        // A lifetime order is a one-time payment: activated with ends_at = null.
        $lifetime = $this->domesticOrder(BillingPeriod::Lifetime, 'fp2def012345');

        $this->fakeGateway(
            alipayTrade: new DomesticTradeResult(DomesticChannel::Alipay, 'fp2def012345', '202601012200140002', DomesticTradeStatus::Paid, '518.00', 'notify-ali-2', $paidAt),
        );

        $this->post('/pay/domestic/alipay/notify', [
            'notify_id' => 'notify-ali-2',
            'out_trade_no' => 'fp2def012345',
            'trade_status' => 'TRADE_SUCCESS',
        ])->assertOk();

        $this->assertSame(OrderStatus::Active, $lifetime->refresh()->status);
        $this->assertNull($lifetime->ends_at);
    }

    public function test_wechat_signed_notify_activates_order()
    {
        Notification::fake();

        $order = $this->domesticOrder(BillingPeriod::Quarterly, 'fp3ghi012345', DomesticChannel::Wechat);

        $paidAt = now()->startOfSecond();

        $this->fakeGateway(
            wechatTrade: new DomesticTradeResult(DomesticChannel::Wechat, 'fp3ghi012345', '4200001234202601011234567890', DomesticTradeStatus::Paid, '518.00', 'wx-notify-1', $paidAt),
        );

        $this->postJson('/pay/domestic/wechat/notify', [
            'id' => 'wx-notify-1',
            'event_type' => 'TRANSACTION.SUCCESS',
        ])
            ->assertOk()
            ->assertJson(['code' => 'SUCCESS']);

        $order->refresh();

        $this->assertSame(OrderStatus::Active, $order->status);
        $this->assertTrue($order->starts_at->equalTo($paidAt));
        $this->assertTrue($order->ends_at->equalTo($paidAt->copy()->addMonths(3)));
        $this->assertSame('4200001234202601011234567890', $order->domestic_transaction_id);
        $this->assertSame(DomesticChannel::Wechat, $order->domestic_channel);

        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    public function test_invalid_signature_rejected()
    {
        Notification::fake();

        $order = $this->domesticOrder(BillingPeriod::Yearly);

        $this->mock(DomesticGateway::class, function ($mock): void {
            $mock->shouldReceive('verifyAlipayNotify')
                ->andThrow(new InvalidDomesticSignatureException('sign mismatch'));
            $mock->shouldReceive('verifyWechatNotify')
                ->andThrow(new InvalidDomesticSignatureException('sign mismatch'));
        });

        $this->post('/pay/domestic/alipay/notify', ['out_trade_no' => 'fp1abc012345'])
            ->assertForbidden();

        $this->postJson('/pay/domestic/wechat/notify', ['id' => 'wx-forged'])
            ->assertForbidden();

        // Nothing was applied or recorded — a legitimate retry is processed fresh.
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(0, DomesticEvent::count());
        $this->assertSame(
            0,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    public function test_polling_endpoint_returns_order_state()
    {
        Notification::fake();

        $order = $this->domesticOrder(BillingPeriod::Monthly);

        $waiting = new DomesticTradeResult(DomesticChannel::Alipay, 'fp1abc012345', null, DomesticTradeStatus::Waiting);
        $paid = new DomesticTradeResult(DomesticChannel::Alipay, 'fp1abc012345', '202601012200140003', DomesticTradeStatus::Paid, '518.00');

        $this->mock(DomesticGateway::class, fn ($mock) => $mock
            ->shouldReceive('queryTrade')
            ->twice()
            ->withArgs(fn (DomesticChannel $channel, string $outTradeNo): bool => $channel === DomesticChannel::Alipay
                && $outTradeNo === 'fp1abc012345')
            ->andReturn($waiting, $paid));

        // Not authenticated / not the owner → 404.
        $this->get(route('pay.domestic.status', $order))->assertFound();

        $this->actingAs(User::factory()->create())
            ->get(route('pay.domestic.status', $order))
            ->assertNotFound();

        // Unpaid: the order state is reported verbatim.
        $this->actingAs($order->user)
            ->get(route('pay.domestic.status', $order))
            ->assertOk()
            ->assertExactJson(['status' => 'pending', 'paid' => false]);

        // Once the provider reports the trade paid, the poll itself
        // activates the order through the same handler as the notifies.
        $this->actingAs($order->user)
            ->get(route('pay.domestic.status', $order))
            ->assertOk()
            ->assertExactJson(['status' => 'active', 'paid' => true]);

        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        $this->assertNotNull($order->starts_at);
        $this->assertTrue($order->ends_at->equalTo($order->starts_at->copy()->addMonth()));
        $this->assertSame('202601012200140003', $order->domestic_transaction_id);

        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    public function test_notify_idempotent_on_replay()
    {
        Notification::fake();

        $order = $this->domesticOrder(BillingPeriod::Yearly);

        $trade = new DomesticTradeResult(DomesticChannel::Alipay, 'fp1abc012345', '202601012200140004', DomesticTradeStatus::Paid, '518.00', 'notify-replay-1', now()->startOfSecond());

        $this->fakeGateway(alipayTrade: $trade);

        $this->post('/pay/domestic/alipay/notify', ['notify_id' => 'notify-replay-1'])
            ->assertOk();

        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        $this->assertSame(1, DomesticEvent::count());

        $startsAt = $order->starts_at->copy();

        // Replay (at-least-once delivery): acknowledged but a no-op.
        $this->post('/pay/domestic/alipay/notify', ['notify_id' => 'notify-replay-1'])
            ->assertOk()
            ->assertSee('success');

        $this->assertSame(1, DomesticEvent::count());
        $this->assertTrue($order->refresh()->starts_at->equalTo($startsAt));

        // The welcome mail went out exactly once.
        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    /**
     * A Pending domestic order with a trade reference, priced in CNY.
     */
    private function domesticOrder(BillingPeriod $period, string $outTradeNo = 'fp1abc012345', DomesticChannel $channel = DomesticChannel::Alipay): Order
    {
        return Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => $period,
            'amount' => 518.00,
            'currency' => 'CNY',
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => $channel,
            'out_trade_no' => $outTradeNo,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    /**
     * Mock the gateway seam: the given verified trades plus the
     * provider-specific acknowledgement body.
     */
    private function fakeGateway(?DomesticTradeResult $alipayTrade = null, ?DomesticTradeResult $wechatTrade = null): void
    {
        $this->mock(DomesticGateway::class, function ($mock) use ($alipayTrade, $wechatTrade): void {
            if ($alipayTrade !== null) {
                $mock->shouldReceive('verifyAlipayNotify')->andReturn($alipayTrade);
            }

            if ($wechatTrade !== null) {
                $mock->shouldReceive('verifyWechatNotify')->andReturn($wechatTrade);
            }

            $mock->shouldReceive('successResponse')
                ->andReturnUsing(fn (DomesticChannel $channel): Psr7Response => match ($channel) {
                    DomesticChannel::Alipay => new Psr7Response(200, [], 'success'),
                    DomesticChannel::Wechat => new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['code' => 'SUCCESS', 'message' => '成功'])),
                });
        });
    }
}
