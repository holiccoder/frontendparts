<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * Reactivation link target (SPEC §16.2 B7), carried by the cancellation
 * confirmation and the Day 7 / Day 30 followup mails. Signature-authenticated
 * like the unsubscribe link so it works from mail clients without a session.
 *
 * Mechanism (documented choice): a cancelled Paddle subscription cannot be
 * un-cancelled from our side, so "reactivation" means a fresh checkout for
 * the same plan. Guests are bounced through login by the checkout zone's
 * auth middleware and land back on checkout with the plan preselected.
 */
class ReactivateOrderController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        return redirect()->route('checkout.show', ['plan' => $order->plan->value]);
    }
}
