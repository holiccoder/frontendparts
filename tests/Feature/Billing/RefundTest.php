<?php

namespace Tests\Feature\Billing;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\RefundProcessedNotification;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\RefundNotAllowedException;
use App\Services\Billing\RefundService;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Refunds (SPEC §7.3, §16.1): full refunds go through Paddle's adjustments
 * API inside the settings-driven 14-day window (`billing.refund_window_days`,
 * §8.7), flip the order to Refunded, and queue the refund-processed email.
 * All Paddle calls are faked — no network.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.api_key' => 'pdl_test_api_key',
            'cashier.sandbox' => true,
        ]);
    }

    public function test_refund_within_window_succeeds()
    {
        Http::fake($this->refundFake());
        Notification::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::Active,
            'starts_at' => now()->subDays(5),
            'paddle_transaction_id' => 'txn_123',
        ]);

        app(RefundService::class)->refund($order, 'Changed my mind');

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);

        // The transaction was fetched for its line items, then a full refund
        // adjustment was created for every item.
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/transactions/txn_123'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/adjustments')
            && $request['action'] === 'refund'
            && $request['transaction_id'] === 'txn_123'
            && $request['reason'] === 'Changed my mind'
            && $request['items'] === [
                ['item_id' => 'txi_1', 'type' => 'full'],
                ['item_id' => 'txi_2', 'type' => 'full'],
            ]);

        // Refunded orders no longer entitle.
        $this->assertSame(
            OrderPlan::Free,
            app(EntitlementService::class)->for($order->user)->plan(),
        );
    }

    public function test_refund_after_window_blocked()
    {
        Http::fake($this->refundFake());
        Notification::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::Active,
            'starts_at' => now()->subDays(30),
            'paddle_transaction_id' => 'txn_123',
        ]);

        $service = app(RefundService::class);

        $this->assertFalse($service->refundable($order));

        try {
            $service->refund($order);
            $this->fail('Expected RefundNotAllowedException past the refund window.');
        } catch (RefundNotAllowedException) {
            // expected
        }

        // Nothing reached Paddle and nothing changed locally.
        Http::assertNothingSent();
        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        Notification::assertNotSentTo($order->user, RefundProcessedNotification::class);

        // The window is settings-driven: widening it (§8.7) makes the same
        // order refundable without a deploy.
        app(Settings::class)->set('billing.refund_window_days', 45);

        $this->assertTrue(app(RefundService::class)->refundable($order->refresh()));
    }

    public function test_refund_processed_notification_queued()
    {
        Http::fake($this->refundFake());
        Notification::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::Active,
            'starts_at' => now()->subDays(2),
            'paddle_transaction_id' => 'txn_123',
        ]);

        app(RefundService::class)->refund($order);

        Notification::assertSentTo(
            $order->user,
            RefundProcessedNotification::class,
            fn (RefundProcessedNotification $notification): bool => $notification->order->is($order)
                && in_array(ShouldQueue::class, class_implements($notification), true),
        );
    }

    public function test_admin_refund_action_on_order_resource()
    {
        Http::fake($this->refundFake());
        Notification::fake();

        $admin = Admin::factory()->create();

        $refundable = Order::factory()->create([
            'status' => OrderStatus::Active,
            'starts_at' => now()->subDays(3),
            'paddle_transaction_id' => 'txn_123',
        ]);

        $outsideWindow = Order::factory()->create([
            'status' => OrderStatus::Active,
            'starts_at' => now()->subDays(30),
            'paddle_transaction_id' => 'txn_123',
        ]);

        $this->actingAs($admin, 'admin');

        // The action is hidden outside the refund window.
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('refund', $refundable)
            ->assertTableActionHidden('refund', $outsideWindow)
            ->callTableAction('refund', $refundable);

        $this->assertSame(OrderStatus::Refunded, $refundable->refresh()->status);
    }

    /**
     * Fakes Paddle's transaction lookup (two line items) and adjustment
     * creation endpoints.
     */
    private function refundFake(): callable
    {
        return function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/transactions/')) {
                return Http::response(['data' => [
                    'id' => 'txn_123',
                    'status' => 'completed',
                    'details' => [
                        'line_items' => [
                            ['id' => 'txi_1', 'price_id' => 'pri_1'],
                            ['id' => 'txi_2', 'price_id' => 'pri_1'],
                        ],
                    ],
                ]]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/adjustments')) {
                return Http::response(['data' => [
                    'id' => 'adj_123',
                    'action' => 'refund',
                    'status' => 'approved',
                ]]);
            }

            return Http::response(['data' => []]);
        };
    }
}
