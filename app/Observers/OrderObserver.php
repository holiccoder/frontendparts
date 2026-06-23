<?php

namespace App\Observers;

use App\Models\Admin;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
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
        }

        Notification::send(Admin::all(), new OrderStatusChanged($order, $previousValue));
    }
}
