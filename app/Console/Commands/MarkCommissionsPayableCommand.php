<?php

namespace App\Console\Commands;

use App\Notifications\AffiliateCommissionPayableNotification;
use App\Services\Affiliates\CommissionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('affiliates:mark-payable')]
#[Description('Flip affiliate commissions past the refund window + holding period from pending to payable (SPEC §17.1 step 5)')]
class MarkCommissionsPayableCommand extends Command
{
    public function handle(CommissionService $commissions): int
    {
        $released = $commissions->markPayable();

        // "Commission payable" mail (SPEC §17.6 — transactional): one per
        // flipped commission, straight from the engine's return list.
        foreach ($released as $commission) {
            $commission->loadMissing('affiliate.user');
            $commission->affiliate->user?->notify(new AffiliateCommissionPayableNotification($commission));
        }

        $this->info("Commissions complete — {$released->count()} commission(s) marked payable.");

        return self::SUCCESS;
    }
}
