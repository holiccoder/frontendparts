<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\Carbon;

/**
 * Normalizes verified domestic trades (Alipay / WeChat notify payloads and
 * QR-page polling results alike — SPEC §7.5) into the shared `orders` state
 * machine (§7.3):
 *
 * - paid   → Active; lifetime orders keep ends_at = null, subscriptions get
 *   a period-based ends_at (one-time payment per period — no auto-deduct),
 *   exactly the Paddle transaction.completed rules.
 * - closed → the Pending order is left untouched: the pre-order simply
 *   expired unpaid and the buyer can scan a fresh QR. PastDue is not used
 *   for domestic orders (it is a renewal-dunning state and domestic
 *   subscriptions do not auto-renew).
 *
 * The buyer-facing side effects (welcome mail, admin notification) fire from
 * OrderObserver on the status change, same as Paddle activations.
 */
class DomesticNotifyHandler
{
    /**
     * Apply a verified trade to its order. Unknown out_trade_nos, trades for
     * already-active orders and amount mismatches are ignored — the caller
     * records the event for idempotency regardless, mirroring
     * PaddleWebhookHandler.
     */
    public function handle(DomesticTradeResult $trade): void
    {
        if (! $trade->isPaid()) {
            return;
        }

        $order = Order::query()
            ->where('out_trade_no', $trade->outTradeNo)
            ->latest('id')
            ->first();

        if ($order === null || $order->status === OrderStatus::Active) {
            return;
        }

        // Guard: the paid amount must match the order's CNY amount, so a
        // re-priced order is never activated by a stale pre-order.
        if ($trade->amount !== null && bccomp($trade->amount, (string) $order->amount, 2) !== 0) {
            return;
        }

        $startsAt = $trade->paidAt ?? now();

        $order->fill([
            'status' => OrderStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $this->periodEnd($order->billing_period, $startsAt),
            'cancelled_at' => null,
            'domestic_channel' => $trade->channel,
            'domestic_transaction_id' => $trade->transactionId ?? $order->domestic_transaction_id,
        ])->save();
    }

    private function periodEnd(BillingPeriod $period, Carbon $startsAt): ?Carbon
    {
        return match ($period) {
            BillingPeriod::Monthly => $startsAt->copy()->addMonth(),
            BillingPeriod::Quarterly => $startsAt->copy()->addMonths(3),
            BillingPeriod::Yearly => $startsAt->copy()->addYear(),
            BillingPeriod::Lifetime => null,
        };
    }
}
