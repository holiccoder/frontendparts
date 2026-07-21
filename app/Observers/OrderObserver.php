<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\DomesticPaymentConfirmedNotification;
use App\Notifications\OrderStatusChanged;
use App\Notifications\WelcomeToProNotification;
use App\Services\Affiliates\CommissionService;
use Illuminate\Support\Facades\Notification;

class OrderObserver
{
    public function __construct(
        private readonly CommissionService $commissions = new CommissionService,
    ) {}

    /**
     * Stamp the dunning anchor (SPEC §16.2 B6) whenever an order enters
     * PastDue, from any writer (Paddle webhook, admin edit). A later
     * recovery → re-failure cycle re-anchors the schedule to the new
     * failure; sequence_sends idempotency still prevents duplicate touches.
     */
    public function updating(Order $order): void
    {
        if ($order->isDirty('status') && $order->status === OrderStatus::PastDue) {
            $order->past_due_at = now();
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getOriginal('status');
        $previousValue = is_object($previous) ? $previous->value : (string) $previous;

        $order->loadMissing('user');

        if ($order->user) {
            $order->user->notify(new OrderStatusChanged($order, $previousValue));

            // Order-paid mail (SPEC §16.1) — the single send point for
            // activation, so webhook/notify and admin activations can't
            // double-send. The two §16.1 rows stay distinct: Paddle (MoR)
            // emails its own receipts, so Paddle buyers get the EN
            // welcome/license summary, while domestic buyers get the zh
            // payment-confirmed + access-unlocked mail INSTEAD — we are
            // their only payment confirmation. Exactly one of the two per
            // activation, never both.
            if ($order->status === OrderStatus::Active) {
                $order->user->notify($order->isDomestic()
                    ? new DomesticPaymentConfirmedNotification($order)
                    : new WelcomeToProNotification($order));
            }
        }

        Notification::send(Admin::all(), new OrderStatusChanged($order, $previousValue));

        // Affiliate commission engine (SPEC §17.2): a paid order earns a
        // pending commission when attributed; a refund voids it before
        // payout. Same single seam as the order-paid mail, so webhook,
        // notify and admin transitions all land here exactly once.
        if ($order->status === OrderStatus::Active) {
            $this->commissions->attributePaidOrder($order);
        }

        if ($order->status === OrderStatus::Refunded) {
            $this->commissions->voidForOrder($order, 'refunded');
        }
    }
}
