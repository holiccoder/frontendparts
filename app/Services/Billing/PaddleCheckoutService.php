<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PlanPrice;
use App\Models\User;
use Laravel\Paddle\Checkout;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds Paddle overlay checkout sessions for a plan × period (SPEC §7.3,
 * §15.3). The Paddle price id always resolves from the `plan_prices` table —
 * never hardcoded — so repricing happens in the admin panel without a deploy.
 *
 * A local Pending order is created (or reused) for the attempt and referenced
 * from the checkout's custom data, so the `transaction.completed` webhook can
 * activate exactly this order.
 */
class PaddleCheckoutService
{
    /**
     * Create the checkout session for the given plan and billing period.
     *
     * The billable user's Paddle customer is created/synced on first use by
     * Cashier's `checkout()` builder (`createAsCustomer`).
     *
     * The referral code (when the buyer carries an affiliate cookie, SPEC
     * §17.1 step 4) is stamped onto the local order AND mirrored into the
     * session's custom data, so the `transaction.completed` webhook can
     * attribute the order even after the cookie is gone.
     *
     * @throws NotFoundHttpException When the plan has no Paddle price for the period.
     */
    public function checkout(User $user, OrderPlan $plan, BillingPeriod $period, ?string $referralCode = null): Checkout
    {
        $price = $plan->price($period);

        if ($price === null || $price->paddle_price_id === null) {
            throw new NotFoundHttpException("No Paddle price configured for {$plan->value} ({$period->value}).");
        }

        $order = $this->pendingOrder($user, $plan, $period, $price, $referralCode);

        $checkout = $user->checkout($price->paddle_price_id)
            ->customData(array_filter([
                'order_id' => (string) $order->id,
                'affiliate_code' => $order->referral_code,
            ]))
            ->returnTo(route('checkout.success'));

        $order->paddle_customer_id = $checkout->getCustomer()?->paddle_id;

        if ($order->isDirty()) {
            $order->save();
        }

        return $checkout;
    }

    /**
     * Reuse the user's latest Pending order for this plan/period (re-priced
     * from the current plan_prices row) or create a fresh one. A fresh
     * referral code re-stamps the order (last-click); a codeless checkout
     * keeps whatever code the order already carries.
     */
    private function pendingOrder(User $user, OrderPlan $plan, BillingPeriod $period, PlanPrice $price, ?string $referralCode = null): Order
    {
        $order = $user->orders()
            ->where('plan', $plan)
            ->where('billing_period', $period)
            ->where('status', OrderStatus::Pending)
            ->latest('id')
            ->first();

        if ($order === null) {
            return $user->orders()->create([
                'plan' => $plan,
                'status' => OrderStatus::Pending,
                'billing_period' => $period,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'referral_code' => $referralCode,
            ]);
        }

        $order->fill([
            'amount' => $price->amount,
            'currency' => $price->currency,
        ]);

        if ($referralCode !== null) {
            $order->referral_code = $referralCode;
        }

        if ($order->isDirty()) {
            $order->save();
        }

        return $order;
    }
}
