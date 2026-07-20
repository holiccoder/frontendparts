<?php

namespace App\Services\Billing;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;

/**
 * Resolves a user's effective plan from their orders (SPEC §7.3 order state
 * machine) into an Entitlement:
 *
 * - Guest / no orders → Free.
 * - The latest order wins. It entitles when it is Active (lifetime orders
 *   have ends_at = null and never expire while Active), PastDue (grace
 *   during dunning), or Cancelled with ends_at still in the future.
 * - Everything else (Pending unpaid, Expired, Cancelled past ends_at) → Free.
 */
class EntitlementService
{
    public function __construct(
        private readonly Settings $settings = new Settings,
    ) {}

    public function for(?User $user): Entitlement
    {
        $plan = $this->planFor($user?->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first());

        return new Entitlement($plan, $this->projectLimit($plan));
    }

    private function planFor(?Order $order): OrderPlan
    {
        if ($order === null) {
            return OrderPlan::Free;
        }

        $entitled = match ($order->status) {
            OrderStatus::Active, OrderStatus::PastDue => true,
            OrderStatus::Cancelled => $order->ends_at !== null && $order->ends_at->isFuture(),
            OrderStatus::Pending, OrderStatus::Expired => false,
        };

        return $entitled ? $order->plan : OrderPlan::Free;
    }

    private function projectLimit(OrderPlan $plan): ?int
    {
        $limit = $this->settings->get("plans.project_limit.{$plan->value}");

        return $limit === null ? null : (int) $limit;
    }
}
