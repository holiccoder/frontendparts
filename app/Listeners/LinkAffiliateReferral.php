<?php

namespace App\Listeners;

use App\Services\Affiliates\ReferralService;
use Illuminate\Auth\Events\Registered;

/**
 * Signup attribution (SPEC §17.1 step 3): when a referred visitor registers,
 * link their click record to the new user so later purchases attribute even
 * after the referral cookie is gone. Auto-discovered on the Registered event
 * alongside SendWelcomeNotification.
 */
class LinkAffiliateReferral
{
    public function __construct(
        private readonly ReferralService $referrals = new ReferralService,
    ) {}

    public function handle(Registered $event): void
    {
        $this->referrals->linkSignup($event->user, request());
    }
}
