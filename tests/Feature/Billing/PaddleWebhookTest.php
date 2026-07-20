<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaddleEvent;
use App\Notifications\WelcomeToProNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paddle webhook processing (SPEC §7.3): the endpoint verifies the
 * `Paddle-Signature` HMAC-SHA256 header over `ts:body`, drives the orders
 * state machine (completed → active, canceled → cancelled-until-ends_at,
 * payment failed → past_due, refunded → refunded) and is idempotent on
 * replayed event ids.
 */
class PaddleWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'pdl_test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook_secret' => $this->secret]);
    }

    public function test_invalid_signature_403()
    {
        $payload = [
            'event_id' => 'evt_invalid_1',
            'event_type' => 'transaction.completed',
            'data' => [],
        ];

        // No signature header at all.
        $this->postJson('/paddle/webhook', $payload)->assertForbidden();

        // A signature computed with the wrong secret.
        $this->paddleWebhook($payload, secret: 'wrong-secret')->assertForbidden();

        // A valid signature over a tampered body.
        $body = json_encode($payload);
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}:{$body}", $this->secret);

        $this->call('POST', '/paddle/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$hash}",
        ], json_encode(['event_id' => 'evt_tampered', 'event_type' => 'transaction.completed', 'data' => []]))
            ->assertForbidden();

        $this->assertSame(0, PaddleEvent::count());
    }

    public function test_transaction_completed_activates_order()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Yearly,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $billedAt = now()->startOfSecond();

        $this->paddleWebhook([
            'event_id' => 'evt_txn_completed_1',
            'event_type' => 'transaction.completed',
            'occurred_at' => $billedAt->toIso8601String(),
            'data' => [
                'id' => 'txn_123',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'subscription_id' => 'sub_123',
                'currency_code' => 'USD',
                'billed_at' => $billedAt->toIso8601String(),
                'custom_data' => ['order_id' => (string) $order->id],
                'details' => ['totals' => ['total' => '10800']],
            ],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Active, $order->status);
        $this->assertTrue($order->starts_at->equalTo($billedAt));
        $this->assertTrue($order->ends_at->equalTo($billedAt->copy()->addYear()));
        $this->assertNull($order->cancelled_at);
        $this->assertSame('txn_123', $order->paddle_transaction_id);
        $this->assertSame('sub_123', $order->paddle_subscription_id);
        $this->assertSame('ctm_123', $order->paddle_customer_id);

        // The user is entitled immediately.
        $this->assertSame(OrderPlan::Pro, app(EntitlementService::class)->for($order->user)->plan());

        // A lifetime order is a one-time transaction: activated with ends_at = null.
        $lifetime = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Lifetime,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->paddleWebhook([
            'event_id' => 'evt_txn_completed_lifetime',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_lifetime_1',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'subscription_id' => null,
                'custom_data' => ['order_id' => (string) $lifetime->id],
                'billed_at' => $billedAt->toIso8601String(),
                'details' => ['totals' => ['total' => '29900']],
            ],
        ])->assertOk();

        $lifetime->refresh();

        $this->assertSame(OrderStatus::Active, $lifetime->status);
        $this->assertNull($lifetime->ends_at);
        $this->assertNull($lifetime->paddle_subscription_id);
    }

    public function test_cancellation_sets_ends_at_and_keeps_access()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'paddle_subscription_id' => 'sub_123',
        ]);

        $periodEnd = now()->addDays(20)->startOfSecond();

        $this->paddleWebhook([
            'event_id' => 'evt_sub_canceled_1',
            'event_type' => 'subscription.canceled',
            'data' => [
                'id' => 'sub_123',
                'status' => 'canceled',
                'canceled_at' => now()->toIso8601String(),
                'current_billing_period' => [
                    'starts_at' => now()->subDays(10)->toIso8601String(),
                    'ends_at' => $periodEnd->toIso8601String(),
                ],
            ],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertTrue($order->ends_at->equalTo($periodEnd));

        // Access is kept until ends_at (SPEC §7.3).
        $this->assertSame(OrderPlan::Pro, app(EntitlementService::class)->for($order->user)->plan());
    }

    public function test_payment_failed_marks_past_due()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'paddle_transaction_id' => 'txn_123',
            'paddle_subscription_id' => 'sub_123',
        ]);

        $this->paddleWebhook([
            'event_id' => 'evt_payment_failed_1',
            'event_type' => 'transaction.payment_failed',
            'data' => [
                'id' => 'txn_123',
                'status' => 'failed',
                'customer_id' => 'ctm_123',
                'subscription_id' => 'sub_123',
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::PastDue, $order->refresh()->status);

        // Dunning grace still entitles (SPEC §7.3 PastDue grace).
        $this->assertSame(OrderPlan::Pro, app(EntitlementService::class)->for($order->user)->plan());
    }

    public function test_webhook_idempotent_on_replay()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $payload = [
            'event_id' => 'evt_replay_1',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_replay_1',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'subscription_id' => null,
                'custom_data' => ['order_id' => (string) $order->id],
                'billed_at' => now()->startOfSecond()->toIso8601String(),
                'details' => ['totals' => ['total' => '1500']],
            ],
        ];

        $this->paddleWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        $this->assertSame(1, PaddleEvent::count());

        $startsAt = $order->starts_at->copy();

        // Replay (Paddle at-least-once delivery): acknowledged but a no-op.
        $this->paddleWebhook($payload)
            ->assertOk()
            ->assertSee('already processed');

        $this->assertSame(1, PaddleEvent::count());
        $this->assertTrue($order->refresh()->starts_at->equalTo($startsAt));

        // The welcome mail went out exactly once.
        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    /**
     * POST a webhook payload with a properly computed Paddle-Signature
     * header (HMAC-SHA256 over `ts:body` with the webhook secret).
     *
     * @param  array<string, mixed>  $payload
     */
    private function paddleWebhook(array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}:{$body}", $secret ?? $this->secret);

        return $this->call('POST', '/paddle/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$hash}",
        ], $body);
    }
}
