<?php

namespace App\Services\Billing;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CancellationConfirmedNotification;

/**
 * User-initiated subscription cancellation (SPEC §16.2 B7). After the
 * required exit survey and reason-mapped save offer, this performs the
 * actual cancel: Paddle is told to end the subscription at the current
 * period boundary, the local order mirrors the `subscription.canceled`
 * webhook shape (Cancelled with access kept until ends_at, SPEC §7.3), the
 * survey answer is stored, and the transactional confirmation mail goes out.
 */
class CancellationService
{
    public function __construct(
        private readonly PaddleGateway $paddle = new PaddleGateway,
    ) {}

    /**
     * The order the user could cancel right now: their latest Paddle
     * subscription order that is still in good standing (Active) or in
     * dunning (PastDue). Lifetime and already-terminated orders are not
     * cancellable.
     */
    public function cancellableOrder(User $user): ?Order
    {
        return $user->orders()
            ->whereNotNull('paddle_subscription_id')
            ->whereIn('status', [OrderStatus::Active, OrderStatus::PastDue])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Finalize the cancellation: cancel at Paddle first (a failure there
     * leaves the local order untouched), then mirror the webhook's Cancelled
     * shape locally and send the confirmation mail with the access-until
     * date and signed reactivation link.
     */
    public function cancel(Order $order, CancellationReason $reason): void
    {
        $this->paddle->cancelSubscription((string) $order->paddle_subscription_id);

        // ends_at already holds the current period end (stamped at
        // activation), which is exactly the access-until date.
        $order->fill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason->value,
        ])->save();

        $order->user->notify(new CancellationConfirmedNotification($order));
    }
}
