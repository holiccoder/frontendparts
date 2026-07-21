<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Http\Controllers\Controller;
use App\Services\Affiliates\ReferralService;
use App\Services\Billing\DomesticCheckoutService;
use App\Services\Billing\PaddleCheckoutService;
use App\Services\Billing\RegionDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hosts checkout (CSR, noindex — SPEC §15.3). The buyer's region routes the
 * backend (SPEC §7.5): USD resolves to the Paddle overlay session for the
 * selected plan × period (the period selector simply navigates with a
 * different `?period=` query); CNY creates the domestic Pending order and
 * forwards to the QR payment page at `/pay/domestic/{order}`.
 */
class CheckoutController extends Controller
{
    public function __invoke(
        Request $request,
        PaddleCheckoutService $checkout,
        DomesticCheckoutService $domesticCheckout,
        RegionDetector $region,
        ReferralService $referrals,
        string $plan,
    ): Response|RedirectResponse {
        $plan = OrderPlan::tryFrom($plan);

        abort_if($plan === null || $plan === OrderPlan::Free, 404);

        $period = BillingPeriod::tryFrom(
            (string) $request->query('period', BillingPeriod::Monthly->value)
        );

        abort_if($period === null, 404);

        // Affiliate attribution (SPEC §17.1 step 4): the referral cookie's
        // code rides the checkout so the paid webhook attributes the order
        // even after the cookie is gone.
        $referralCode = $referrals->codeFromRequest($request);

        // CN region → CNY domestic QR checkout (SPEC §7.5).
        if ($region->preferredCurrency($request) === RegionDetector::CNY) {
            $order = $domesticCheckout->checkout($request->user(), $plan, $period, $referralCode);

            return redirect()->route('pay.domestic', $order);
        }

        $session = $checkout->checkout($request->user(), $plan, $period, $referralCode);

        return Inertia::render('checkout/show', [
            'plan' => $plan->value,
            'selectedPeriod' => $period->value,
            'periods' => $this->periods($plan),
            'checkout' => $session->options(),
            'paddle' => [
                'token' => config('services.paddle.client_side_token'),
                'environment' => config('cashier.sandbox') ? 'sandbox' : 'production',
            ],
            'successUrl' => route('checkout.success'),
            // Manual currency switch (SPEC §7.5) — posting CNY re-enters
            // this controller, which then routes to the domestic QR flow.
            'currencySwitchUrl' => route('billing.currency.switch'),
        ]);
    }

    /**
     * Every billing period with its plan_prices row, so the selector can
     * display amounts and link each period — prices never hardcoded.
     *
     * @return array<string, array{amount: string|null, currency: string, price_id: string|null}>
     */
    private function periods(OrderPlan $plan): array
    {
        return collect(BillingPeriod::cases())
            ->mapWithKeys(function (BillingPeriod $period) use ($plan): array {
                $price = $plan->price($period);

                return [
                    $period->value => [
                        'amount' => $price?->amount,
                        'currency' => $price?->currency ?? 'USD',
                        'price_id' => $price?->paddle_price_id,
                    ],
                ];
            })
            ->all();
    }
}
