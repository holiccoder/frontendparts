<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders page (SPEC §15.4, CSR): the user's orders newest-first with the
 * Paddle receipt/invoice URL, the derived license state and the renewal or
 * expiry date (lifetime licenses never expire, ends_at stays null).
 */
class OrdersController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'plan' => $order->plan->value,
                'status' => $order->status->value,
                'license_state' => $order->licenseState()->value,
                'billing_period' => $order->billing_period->value,
                'is_lifetime' => $order->billing_period === BillingPeriod::Lifetime,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'starts_at' => $order->starts_at?->toIso8601String(),
                'ends_at' => $order->ends_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'created_at' => $order->created_at->toIso8601String(),
                'receipt_url' => $order->receiptUrl(),
            ]);

        return Inertia::render('dashboard/orders', [
            'orders' => $orders,
        ]);
    }
}
