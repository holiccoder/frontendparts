<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\Carbon;

/**
 * Applies Paddle webhook events to the `orders` state machine (SPEC §7.3):
 *
 * - transaction.completed        → Active; lifetime orders keep ends_at = null,
 *   subscriptions get a period-based ends_at.
 * - subscription.canceled        → Cancelled, access kept until the current
 *   billing period's ends_at.
 * - transaction.payment_failed   → PastDue (dunning grace still entitles).
 * - transaction.updated (status refunded) → Refunded.
 *
 * Unknown events and unknown orders are ignored; the caller records the event
 * for idempotency regardless.
 */
class PaddleWebhookHandler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        match ($payload['event_type'] ?? null) {
            'transaction.completed' => $this->transactionCompleted($payload['data'] ?? []),
            'transaction.updated' => $this->transactionUpdated($payload['data'] ?? []),
            'transaction.payment_failed' => $this->paymentFailed($payload['data'] ?? []),
            'subscription.canceled' => $this->subscriptionCanceled($payload['data'] ?? []),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transactionCompleted(array $data): void
    {
        $order = $this->orderFromCustomData($data)
            ?? $this->orderFromSubscription($data['subscription_id'] ?? null);

        if ($order === null) {
            return;
        }

        $startsAt = isset($data['billed_at'])
            ? Carbon::parse($data['billed_at'])
            : now();

        $order->fill([
            'status' => OrderStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $this->periodEnd($order->billing_period, $startsAt),
            'cancelled_at' => null,
            'paddle_customer_id' => $data['customer_id'] ?? $order->paddle_customer_id,
            'paddle_transaction_id' => $data['id'] ?? $order->paddle_transaction_id,
            'paddle_subscription_id' => $data['subscription_id'] ?? $order->paddle_subscription_id,
            // Affiliate attribution backfill (SPEC §17.1 step 4): the code
            // mirrored into the checkout session's custom data re-stamps an
            // order that lost it — attribution survives cookie loss.
            'referral_code' => $order->referral_code ?? $this->referralCodeFromCustomData($data),
        ])->save();
    }

    /**
     * A fully refunded transaction flips the order to Refunded (partial
     * refunds keep access — SPEC §7.3 refunds are full refunds).
     *
     * @param  array<string, mixed>  $data
     */
    private function transactionUpdated(array $data): void
    {
        if (($data['status'] ?? null) !== 'refunded') {
            return;
        }

        Order::query()
            ->where('paddle_transaction_id', $data['id'] ?? null)
            ->first()
            ?->update(['status' => OrderStatus::Refunded]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paymentFailed(array $data): void
    {
        $order = $this->orderFromCustomData($data)
            ?? Order::query()->where('paddle_transaction_id', $data['id'] ?? null)->first()
            ?? $this->orderFromSubscription($data['subscription_id'] ?? null);

        $order?->update(['status' => OrderStatus::PastDue]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function subscriptionCanceled(array $data): void
    {
        $order = $this->orderFromSubscription($data['id'] ?? null)
            ?? $this->orderFromCustomData($data);

        if ($order === null) {
            return;
        }

        $order->fill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => isset($data['canceled_at'])
                ? Carbon::parse($data['canceled_at'])
                : now(),
            'ends_at' => isset($data['current_billing_period']['ends_at'])
                ? Carbon::parse($data['current_billing_period']['ends_at'])
                : $order->ends_at,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function orderFromCustomData(array $data): ?Order
    {
        $orderId = $data['custom_data']['order_id'] ?? null;

        return $orderId !== null ? Order::query()->find($orderId) : null;
    }

    /**
     * The affiliate code mirrored into the checkout session's custom data
     * (SPEC §17.1 step 4), when present.
     *
     * @param  array<string, mixed>  $data
     */
    private function referralCodeFromCustomData(array $data): ?string
    {
        $code = $data['custom_data']['affiliate_code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function orderFromSubscription(?string $subscriptionId): ?Order
    {
        if ($subscriptionId === null) {
            return null;
        }

        return Order::query()
            ->where('paddle_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();
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
