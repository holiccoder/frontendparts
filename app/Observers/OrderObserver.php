<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use App\Notifications\WelcomeToProNotification;
use Illuminate\Support\Facades\Notification;

class OrderObserver
{
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

            // Order-paid welcome mail (SPEC §16.1) — the single send point for
            // activation, so webhook and admin activations can't double-send.
            if ($order->status === OrderStatus::Active) {
                $order->user->notify(new WelcomeToProNotification($order));
            }
        }

        Notification::send(Admin::all(), new OrderStatusChanged($order, $previousValue));
    }
}
