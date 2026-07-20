<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\RefundProcessedNotification;
use App\Support\Settings;

/**
 * Refunds paid orders through Paddle, honoring the settings-driven refund
 * window (SPEC §7.3: 14 days, admin-editable via `billing.refund_window_days`,
 * §8.7). A successful refund flips the order to Refunded — which revokes
 * entitlement — and queues the refund-processed email (SPEC §16.1).
 */
class RefundService
{
    /**
     * Order states in which money is (or was recently) on the table.
     *
     * @var list<OrderStatus>
     */
    private const REFUNDABLE_STATUSES = [
        OrderStatus::Active,
        OrderStatus::PastDue,
        OrderStatus::Cancelled,
    ];

    public function __construct(
        private readonly PaddleGateway $gateway = new PaddleGateway,
        private readonly Settings $settings = new Settings,
    ) {}

    /**
     * Whether the order can currently be refunded: paid, linked to a Paddle
     * transaction, and still inside the refund window.
     */
    public function refundable(Order $order): bool
    {
        if (! in_array($order->status, self::REFUNDABLE_STATUSES, true)) {
            return false;
        }

        if ($order->paddle_transaction_id === null) {
            return false;
        }

        return $this->withinWindow($order);
    }

    /**
     * The window runs from the purchase moment (activation) — falling back to
     * the order's creation for never-activated orders.
     */
    public function withinWindow(Order $order): bool
    {
        $purchasedAt = $order->starts_at ?? $order->created_at;

        if ($purchasedAt === null) {
            return false;
        }

        $windowDays = (int) $this->settings->get('billing.refund_window_days');

        return $purchasedAt->copy()->addDays($windowDays)->isFuture();
    }

    /**
     * Refund the order in full via Paddle and mark it Refunded.
     *
     * @throws RefundNotAllowedException When the order is not refundable.
     */
    public function refund(Order $order, string $reason = 'Customer requested a refund'): Order
    {
        if (! $this->refundable($order)) {
            throw new RefundNotAllowedException(
                "Order #{$order->id} is not refundable (status {$order->status->value}, window "
                .((int) $this->settings->get('billing.refund_window_days')).' days).'
            );
        }

        $this->gateway->refund($order->paddle_transaction_id, $reason);

        $order->update(['status' => OrderStatus::Refunded]);

        $order->loadMissing('user');
        $order->user?->notify(new RefundProcessedNotification($order));

        return $order;
    }
}
