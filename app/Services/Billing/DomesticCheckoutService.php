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
 *
 * Team tier (task 5.2): team checkouts carry a seat count — the stored
 * amount is the per-seat price multiplied by the seats (the QR pre-order
 * charges the order's amount, so the total is what the buyer scans for) and
 * the seat number caps the organization's membership once activated.
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
    public function checkout(User $user, OrderPlan $plan, BillingPeriod $period, ?string $referralCode = null, int $seats = 1): Order
    {
        $price = $plan->price($period, PlanProvider::Domestic);

        if ($price === null) {
            throw new NotFoundHttpException("No domestic price configured for {$plan->value} ({$period->value}).");
        }

        return $this->pendingOrder($user, $plan, $period, $price, $referralCode, $this->seatCount($plan, $seats));
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
     * Reuse the user's latest unpaid Pending domestic order for this
     * plan/period (re-priced from the current plan_prices row) or create a
     * fresh one — mirrors PaddleCheckoutService so repeated checkouts never
     * pile up duplicates. A fresh referral code re-stamps the order
     * (last-click); a codeless checkout keeps the code it already carries.
     * Team orders additionally store the seat count and total.
     */
    private function pendingOrder(User $user, OrderPlan $plan, BillingPeriod $period, PlanPrice $price, ?string $referralCode = null, int $seats = 1): Order
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
                'amount' => $this->total($price, $seats),
                'seats' => $plan === OrderPlan::Team ? $seats : null,
                'currency' => $price->currency,
                'provider' => PlanProvider::Domestic,
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
