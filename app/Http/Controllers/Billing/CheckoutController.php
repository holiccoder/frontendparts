<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Http\Controllers\Controller;
use App\Services\Billing\PaddleCheckoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hosts the Paddle overlay checkout (CSR, noindex — SPEC §15.3). The page
 * receives the checkout session options for the selected plan × period; the
 * period selector simply navigates with a different `?period=` query.
 */
class CheckoutController extends Controller
{
    public function __invoke(Request $request, PaddleCheckoutService $checkout, string $plan): Response
    {
        $plan = OrderPlan::tryFrom($plan);

        abort_if($plan === null || $plan === OrderPlan::Free, 404);

        $period = BillingPeriod::tryFrom(
            (string) $request->query('period', BillingPeriod::Monthly->value)
        );

        abort_if($period === null, 404);

        $session = $checkout->checkout($request->user(), $plan, $period);

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
