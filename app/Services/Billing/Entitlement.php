<?php

namespace App\Services\Billing;

use App\Enums\OrderPlan;

/**
 * Resolved entitlements for one user at one moment (SPEC §7.1 feature
 * matrix): the effective plan, what that plan unlocks, and the
 * settings-driven project limit (`null` = unlimited). Immutable — resolve a
 * fresh instance through EntitlementService whenever state may have changed.
 */
final readonly class Entitlement
{
    public function __construct(
        private OrderPlan $plan,
        private ?int $projectLimit,
    ) {}

    public function plan(): OrderPlan
    {
        return $this->plan;
    }

    public function isPaid(): bool
    {
        return $this->plan !== OrderPlan::Free;
    }

    /**
     * Full-library copy/download rights (SPEC §7.1): Starter and Pro both get
     * 100% of the catalog; Team seats are Pro-equivalent (task 5.2); Free is
     * limited to the free subset.
     */
    public function hasFullLibrary(): bool
    {
        return $this->plan === OrderPlan::Starter || $this->plan === OrderPlan::Pro || $this->plan === OrderPlan::Team;
    }

    /**
     * Next.js / Nuxt scaffolding (SPEC §7.1): Pro and Team only.
     */
    public function canScaffold(): bool
    {
        return $this->plan === OrderPlan::Pro || $this->plan === OrderPlan::Team;
    }

    /**
     * GitHub repo export (SPEC §6.4): part of the Pro scaffolding family
     * (§7.1) — Pro and Team only.
     */
    public function canExportToGithub(): bool
    {
        return $this->plan === OrderPlan::Pro || $this->plan === OrderPlan::Team;
    }

    /**
     * Project limit resolved from platform settings (SPEC §8.7);
     * `null` means unlimited.
     */
    public function projectLimit(): ?int
    {
        return $this->projectLimit;
    }
}
