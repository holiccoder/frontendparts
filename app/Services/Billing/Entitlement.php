<?php

namespace App\Services\Billing;

use App\Enums\OrderPlan;

/**
 * Resolved entitlements for one user at one moment: the effective plan and
 * whether it is a paid one. Immutable — resolve a fresh instance through
 * EntitlementService whenever state may have changed. New products extend
 * this with their own capability flags (see SKELETON.md).
 */
final readonly class Entitlement
{
    public function __construct(
        private OrderPlan $plan,
    ) {}

    public function plan(): OrderPlan
    {
        return $this->plan;
    }

    public function isPaid(): bool
    {
        return $this->plan !== OrderPlan::Free;
    }
}
