<?php

namespace Tests\Feature\Security;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\InvalidDomesticSignatureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_paddle_webhook_rejects_invalid_signature(): void
    {
        config(['cashier.webhook_secret' => 'pdl_test_webhook_secret']);

        $payload = [
            'event_id' => 'evt_forge_1',
            'event_type' => 'transaction.completed',
            'data' => [],
        ];

        // No signature header.
        $this->postJson('/paddle/webhook', $payload)->assertForbidden();

        // Signature computed with the wrong secret.
        $body = json_encode($payload);
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}:{$body}", 'wrong-secret');

        $this->call('POST', '/paddle/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$hash}",
        ], $body)->assertForbidden();
    }

    public function test_domestic_webhooks_reject_invalid_signature(): void
    {
        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 518.00,
            'currency' => 'CNY',
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => DomesticChannel::Alipay,
            'out_trade_no' => 'fp1abc012345',
            'starts_at' => null,
            'ends_at' => null,
        ]);

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

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }
}
