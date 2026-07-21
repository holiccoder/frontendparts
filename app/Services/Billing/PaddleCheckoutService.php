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
 *
 * Team tier (task 5.2): team checkouts carry a seat count — the Paddle line
 * item quantity is the seat count and the pending order stores both the
 * per-seat-multiplied total and the seat number itself, so the activated
 * order later caps the organization's membership.
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
    public function checkout(User $user, OrderPlan $plan, BillingPeriod $period, ?string $referralCode = null, int $seats = 1): Checkout
    {
        $price = $plan->price($period);

        if ($price === null || $price->paddle_price_id === null) {
            throw new NotFoundHttpException("No Paddle price configured for {$plan->value} ({$period->value}).");
        }

        $seats = $this->seatCount($plan, $seats);

        $order = $this->pendingOrder($user, $plan, $period, $price, $referralCode, $seats);

        $checkout = $user->checkout($price->paddle_price_id, $seats)
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
     * Only team orders are per-seat; solo plans always bill a single seat
     * and leave the column null.
     */
    private function seatCount(OrderPlan $plan, int $seats): int
    {
        return $plan === OrderPlan::Team ? max(1, $seats) : 1;
    }

    /**
     * Reuse the user's latest Pending order for this plan/period (re-priced
     * from the current plan_prices row) or create a fresh one. A fresh
     * referral code re-stamps the order (last-click); a codeless checkout
     * keeps whatever code the order already carries. Team orders additionally
     * store the seat count and the per-seat-multiplied total.
     */
    private function pendingOrder(User $user, OrderPlan $plan, BillingPeriod $period, PlanPrice $price, ?string $referralCode = null, int $seats = 1): Order
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
                'amount' => $this->total($price, $seats),
                'seats' => $plan === OrderPlan::Team ? $seats : null,
                'currency' => $price->currency,
                'referral_code' => $referralCode,
            ]);
        }

        $order->fill([
            'amount' => $this->total($price, $seats),
            'seats' => $plan === OrderPlan::Team ? $seats : null,
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

    /**
     * Order total: the plan_prices amount is per seat for team checkouts,
     * so the stored amount is the seat-multiplied total.
     */
    private function total(PlanPrice $price, int $seats): string
    {
        return number_format((float) $price->amount * $seats, 2, '.', '');
    }
}
