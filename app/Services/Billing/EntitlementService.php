<?php

namespace App\Services\Billing;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;

/**
 * Resolves a user's effective plan from their orders (SPEC §7.3 order state
 * machine) into an Entitlement:
 *
 * - Guest / no orders → Free.
 * - The latest order wins. It entitles when it is Active (lifetime orders
 *   have ends_at = null and never expire while Active), PastDue (grace
 *   during dunning), or Cancelled with ends_at still in the future.
 * - Everything else (Pending unpaid, Expired, Refunded, Cancelled past
 *   ends_at) → Free.
 *
 * Team tier (task 5.2) — precedence rule: a personally entitled order
 * always wins; organization membership only lifts a user who resolves to
 * Free. In that case every organization the user belongs to (any role) is
 * checked, and the first one whose owner's latest team-plan order entitles
 * per the same state machine grants the Pro-equivalent Team plan. Because
 * resolution is live, member removal or the team order expiring revokes the
 * inherited entitlement on the next read.
 */
class EntitlementService
{
    public function for(?User $user): Entitlement
    {
        $personal = $user === null ? OrderPlan::Free : $this->planFor($user->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first());

        // A personally entitled order always beats an inherited team seat.
        if ($personal !== OrderPlan::Free) {
            return new Entitlement($personal);
        }

        $plan = $user !== null && $this->hasEntitledOrganization($user)
            ? OrderPlan::Team
            : OrderPlan::Free;

        return new Entitlement($plan);
    }

    /**
     * The organization's team subscription if it currently entitles (per
     * the same state machine personal orders use), null otherwise. Also the
     * source of the seat cap for invitations.
     */
    public function entitledTeamOrder(Organization $organization): ?Order
    {
        $order = $organization->teamOrder();

        return $order !== null && $this->planFor($order) === OrderPlan::Team ? $order : null;
    }

    /**
     * Whether any organization the user belongs to has an entitled team
     * order — the membership half of the precedence rule.
     */
    private function hasEntitledOrganization(User $user): bool
    {
        return $user->organizations()
            ->get()
            ->contains(fn (Organization $organization): bool => $this->entitledTeamOrder($organization) !== null);
    }

    private function planFor(?Order $order): OrderPlan
    {
        if ($order === null) {
            return OrderPlan::Free;
        }

        $entitled = match ($order->status) {
            OrderStatus::Active, OrderStatus::PastDue => true,
            OrderStatus::Cancelled => $order->ends_at !== null && $order->ends_at->isFuture(),
            OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Refunded => false,
        };

        return $entitled ? $order->plan : OrderPlan::Free;
    }
}
