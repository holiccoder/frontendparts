<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-payment confirmation page (CSR, noindex — SPEC §15.3): license
 * summary plus next steps. Paddle may bounce the buyer here before the
 * `transaction.completed` webhook lands, so the summary reflects the latest
 * order whatever state it is in.
 */
class CheckoutSuccessController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $order = $request->user()->orders()->latest('id')->first();

        return Inertia::render('checkout/success', [
            'license' => $order === null ? null : [
                'plan' => $order->plan->value,
                'billing_period' => $order->billing_period->value,
                'status' => $order->status->value,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'starts_at' => $order->starts_at?->toIso8601String(),
                'ends_at' => $order->ends_at?->toIso8601String(),
            ],
            'nextSteps' => [
                ['label' => 'Browse the library', 'href' => route('components.index')],
                ['label' => 'Open your dashboard', 'href' => route('dashboard')],
            ],
        ]);
    }
}
