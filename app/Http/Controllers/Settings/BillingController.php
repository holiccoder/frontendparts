<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BillingCancelRequest;
use App\Models\Order;
use App\Services\Billing\CancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Billing settings (SPEC §15.4, §16.2 B7): the canonical update-payment
 * deep-link target for dunning mail (B6) and the home of the cancel flow —
 * required 1-question exit survey → reason-mapped save offer → confirm.
 */
class BillingController extends Controller
{
    public function edit(Request $request, CancellationService $cancellations): Response
    {
        $order = $cancellations->cancellableOrder($request->user())
            ?? $request->user()->orders()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

        return Inertia::render('settings/billing', [
            'order' => $order === null ? null : $this->serializeOrder($order),
            'cancellable' => $order !== null
                && $order->paddle_subscription_id !== null
                && in_array($order->status, [OrderStatus::Active, OrderStatus::PastDue], true),
            'cancellationReasons' => CancellationReason::options(),
        ]);
    }

    public function cancel(BillingCancelRequest $request, CancellationService $cancellations): RedirectResponse
    {
        $reason = CancellationReason::from($request->validated('reason'));

        $order = $cancellations->cancellableOrder($request->user());

        abort_if($order === null, 403, 'There is no active subscription to cancel.');

        if (! $request->boolean('confirmed')) {
            // Survey answered — present the reason-mapped save offer (SPEC
            // §16.2) and leave the order untouched until the user confirms.
            return back()->with('save_offer', [
                'reason' => $reason->value,
                ...$reason->saveOffer(),
            ]);
        }

        $cancellations->cancel($order, $reason);

        return back()->with('notice', 'Your subscription has been cancelled. A confirmation email is on its way.');
    }

    /**
     * @return array{id: int, plan: string, status: string, license_state: string, billing_period: string, ends_at: string|null, receipt_url: string|null}
     */
    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'plan' => $order->plan->value,
            'status' => $order->status->value,
            'license_state' => $order->licenseState()->value,
            'billing_period' => $order->billing_period->value,
            'ends_at' => $order->ends_at?->toIso8601String(),
            'receipt_url' => $order->receiptUrl(),
        ];
    }
}
