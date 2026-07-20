<?php

namespace App\Services\Admin;

use App\Enums\BillingPeriod;
use App\Enums\ComponentStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Revenue & growth math behind the admin dashboard P1 widgets (SPEC §8.6
 * rows 1–2), kept out of the Filament widgets so the rules stay queryable
 * and testable on their own.
 *
 * Counting rules (locked here — widgets stay thin):
 *
 * - "Contributing" orders drive MRR, active subscribers, and the plan mix:
 *   the same states that still entitle per the §7.3 state machine — Active,
 *   PastDue (dunning grace), and Cancelled while ends_at is still in the
 *   future (access runs to period end). Pending / Expired / Refunded never
 *   contribute, matching EntitlementService.
 * - Revenue (trend) counts orders that were paid and not refunded —
 *   Active, PastDue, Cancelled, Expired — recognized in the month of
 *   starts_at (falling back to created_at). Pending was never paid and
 *   Refunded was returned, so both are excluded.
 * - Free-plan orders are excluded everywhere: subscribers are Starter+Pro
 *   (SPEC §8.6 row 1).
 * - MRR normalizes each order to a monthly figure: monthly → amount,
 *   quarterly → amount/3, yearly → amount/12. Lifetime is EXCLUDED from
 *   MRR (one-off, not recurring) but INCLUDED in revenue.
 * - Amounts are USD (all Paddle orders are USD); CNY domestic orders are a
 *   Phase-3 concern — the `fx.cny_to_usd` settings key already exists for
 *   that conversion.
 */
class RevenueStats
{
    /**
     * Order states in which money was collected and not returned.
     *
     * @var list<OrderStatus>
     */
    private const REVENUE_STATUSES = [
        OrderStatus::Active,
        OrderStatus::PastDue,
        OrderStatus::Cancelled,
        OrderStatus::Expired,
    ];

    /**
     * Normalized monthly recurring revenue in USD across contributing
     * orders; lifetime orders never contribute.
     */
    public function mrr(): float
    {
        return (float) $this->contributingOrders()
            ->reject(fn (Order $order): bool => $order->billing_period === BillingPeriod::Lifetime)
            ->sum(fn (Order $order): float => $this->monthlyAmount($order));
    }

    /**
     * Registered users with week-over-week deltas (SPEC §8.6 row 1:
     * "+N this week").
     *
     * @return array{total: int, this_week: int, last_week: int}
     */
    public function userGrowth(): array
    {
        return [
            'total' => User::query()->count(),
            'this_week' => User::query()->where('created_at', '>=', now()->subWeek())->count(),
            'last_week' => User::query()
                ->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])
                ->count(),
        ];
    }

    /**
     * Paid (Starter+Pro) subscribers — distinct users holding at least one
     * contributing order.
     */
    public function activeSubscribers(): int
    {
        return $this->contributingOrders()->pluck('user_id')->unique()->count();
    }

    /**
     * Components sitting in the review queue.
     */
    public function awaitingReview(): int
    {
        return Component::query()->where('status', ComponentStatus::InReview)->count();
    }

    /**
     * Monthly revenue for the trailing 12 months including the current one,
     * split into subscription vs lifetime datasets so one-off lifetime
     * spikes stay visually separate from subscription revenue (SPEC §8.6
     * row 2).
     *
     * @return array{labels: list<string>, subscription: list<float>, lifetime: list<float>}
     */
    public function revenueTrend(): array
    {
        $labels = [];
        $subscription = [];
        $lifetime = [];

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $labels[] = $month->format('M Y');
            $subscription[$month->format('Y-m')] = 0.0;
            $lifetime[$month->format('Y-m')] = 0.0;
        }

        foreach ($this->revenueOrders() as $order) {
            $key = ($order->starts_at ?? $order->created_at)->format('Y-m');

            if (! array_key_exists($key, $subscription)) {
                continue; // Outside the 12-month window.
            }

            if ($order->billing_period === BillingPeriod::Lifetime) {
                $lifetime[$key] += (float) $order->amount;
            } else {
                $subscription[$key] += (float) $order->amount;
            }
        }

        return [
            'labels' => $labels,
            'subscription' => array_values($subscription),
            'lifetime' => array_values($lifetime),
        ];
    }

    /**
     * Plan mix donut (SPEC §8.6 row 2): contributing orders counted by
     * plan × billing period — lifetime slices included so cannibalization
     * stays visible. Zero-count slices are omitted.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function planMix(): array
    {
        $counts = $this->contributingOrders()
            ->countBy(fn (Order $order): string => "{$order->plan->value}|{$order->billing_period->value}");

        $labels = [];
        $data = [];

        foreach ([OrderPlan::Starter, OrderPlan::Pro] as $plan) {
            foreach (BillingPeriod::cases() as $period) {
                $count = (int) $counts->get("{$plan->value}|{$period->value}", 0);

                if ($count === 0) {
                    continue;
                }

                $labels[] = ucfirst($plan->value).' · '.ucfirst($period->value);
                $data[] = $count;
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Orders that still entitle (SPEC §7.3): Active, PastDue grace, or
     * Cancelled until ends_at; Free-plan rows never count.
     *
     * @return Collection<int, Order>
     */
    private function contributingOrders(): Collection
    {
        return Order::query()
            ->where('plan', '!=', OrderPlan::Free)
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('status', [OrderStatus::Active, OrderStatus::PastDue])
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('status', OrderStatus::Cancelled)
                    ->where('ends_at', '>', now())))
            ->get();
    }

    /**
     * All paid-and-not-refunded orders, regardless of age.
     *
     * @return Collection<int, Order>
     */
    private function revenueOrders(): Collection
    {
        return Order::query()
            ->where('plan', '!=', OrderPlan::Free)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->get();
    }

    /**
     * One month's worth of an order's price (SPEC §8.6 MRR normalization).
     */
    private function monthlyAmount(Order $order): float
    {
        $amount = (float) $order->amount;

        return match ($order->billing_period) {
            BillingPeriod::Monthly => $amount,
            BillingPeriod::Quarterly => $amount / 3,
            BillingPeriod::Yearly => $amount / 12,
            BillingPeriod::Lifetime => 0.0,
        };
    }
}
