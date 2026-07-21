<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\RefundProcessedNotification;
use App\Support\Settings;

/**
 * Refunds paid orders through the provider that collected them (SPEC §7.3,
 * §7.5), honoring the settings-driven refund window (14 days,
 * admin-editable via `billing.refund_window_days`, §8.7). Paddle orders go
 * through Paddle's adjustments API; domestic (Alipay / WeChat Pay, CNY)
 * orders go through the DomesticGateway seam's provider refund. A
 * successful refund flips the order to Refunded — which revokes
 * entitlement — and queues the refund-processed email (SPEC §16.1; zh for
 * domestic buyers per the §16.3 domestic convention).
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
        private readonly DomesticGateway $domesticGateway = new DomesticGateway,
        private readonly Settings $settings = new Settings,
    ) {}

    /**
     * Whether the order can currently be refunded: paid, carrying the
     * provider reference its rail refunds by, and still inside the refund
     * window.
     */
    public function refundable(Order $order): bool
    {
        if (! in_array($order->status, self::REFUNDABLE_STATUSES, true)) {
            return false;
        }

        if (! $this->hasProviderReference($order)) {
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
     * Refund the order in full via its provider and mark it Refunded.
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

        if ($order->isDomestic()) {
            $this->domesticGateway->refund(
                $order->domestic_channel,
                $order,
                $reason,
                self::outRefundNoFor($order),
            );
        } else {
            $this->gateway->refund($order->paddle_transaction_id, $reason);
        }

        $order->update(['status' => OrderStatus::Refunded]);

        $order->loadMissing('user');
        $order->user?->notify(new RefundProcessedNotification($order));

        return $order;
    }

    /**
     * The reference each rail needs for a refund: a Paddle transaction id,
     * or the domestic channel + out_trade_no both domestic rails refund by.
     */
    private function hasProviderReference(Order $order): bool
    {
        return $order->isDomestic()
            ? $order->out_trade_no !== null && $order->domestic_channel !== null
            : $order->paddle_transaction_id !== null;
    }

    /**
     * The refund reference WeChat Pay requires (Alipay refunds key off
     * out_trade_no alone): unique per refund attempt, ASCII, ≤ 64 chars —
     * the same shape as DomesticGateway::outTradeNoFor.
     */
    private static function outRefundNoFor(Order $order): string
    {
        return sprintf('fpr%d%s', $order->id, bin2hex(random_bytes(6)));
    }
}
