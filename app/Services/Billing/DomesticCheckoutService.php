<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Models\PlanPrice;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Domestic (CNY) checkout setup (SPEC §7.5): creates (or reuses) the local
 * Pending order for a plan × period priced from the `plan_prices` domestic
 * rows — never hardcoded — then hands off to `/pay/domestic/{order}` for
 * the QR pre-order. The paid notify normalizes into the same orders state
 * machine as Paddle (§7.3).
 */
class DomesticCheckoutService
{
    /**
     * The Pending domestic order backing this checkout attempt. The referral
     * code (when the buyer carries an affiliate cookie) is stamped onto the
     * order — the domestic order meta of SPEC §17.1 step 4 — so the paid
     * notify attributes the order even after the cookie is gone.
     *
     * @throws NotFoundHttpException When the plan has no domestic CNY price for the period.
     */
    public function checkout(User $user, OrderPlan $plan, BillingPeriod $period, ?string $referralCode = null): Order
    {
        $price = $plan->price($period, PlanProvider::Domestic);

        if ($price === null) {
            throw new NotFoundHttpException("No domestic price configured for {$plan->value} ({$period->value}).");
        }

        return $this->pendingOrder($user, $plan, $period, $price, $referralCode);
    }

    /**
     * Reuse the user's latest unpaid Pending domestic order for this
     * plan/period (re-priced from the current plan_prices row) or create a
     * fresh one — mirrors PaddleCheckoutService so repeated checkouts never
     * pile up duplicates. A fresh referral code re-stamps the order
     * (last-click); a codeless checkout keeps the code it already carries.
     */
    private function pendingOrder(User $user, OrderPlan $plan, BillingPeriod $period, PlanPrice $price, ?string $referralCode = null): Order
    {
        $order = $user->orders()
            ->where('plan', $plan)
            ->where('billing_period', $period)
            ->where('status', OrderStatus::Pending)
            ->where('provider', PlanProvider::Domestic)
            ->latest('id')
            ->first();

        if ($order === null) {
            return $user->orders()->create([
                'plan' => $plan,
                'status' => OrderStatus::Pending,
                'billing_period' => $period,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'provider' => PlanProvider::Domestic,
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
