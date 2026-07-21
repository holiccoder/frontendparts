<?php

namespace App\Services\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * Referral tracking (SPEC §17.1): records clicks on `/r/{code}`, manages the
 * first-party attribution cookie, and links the click to the visitor when
 * they sign up.
 *
 * Attribution is last-click: every valid click re-stamps the cookie, and the
 * code the buyer carries into checkout (cookie → order `referral_code` →
 * Paddle `custom_data` / domestic order meta) wins over the older
 * signup-link record.
 *
 * Fraud controls (§17.2): only ACTIVE affiliates record clicks or hand out
 * codes — an unknown or suspended code silently redirects without recording,
 * and the route is rate-limited.
 */
class ReferralService
{
    /**
     * First-party attribution cookie name (SPEC §17.1 step 2).
     */
    public const COOKIE = 'fp_ref';

    public function __construct(
        private readonly Settings $settings = new Settings,
    ) {}

    /**
     * Record a click for a valid, active affiliate code. Returns null for an
     * unknown or suspended code — the caller then redirects silently without
     * recording anything or setting the cookie.
     */
    public function recordClick(string $code, Request $request, string $target): ?AffiliateReferral
    {
        $affiliate = $this->affiliateForCode($code);

        if ($affiliate === null) {
            return null;
        }

        return $affiliate->referrals()->create([
            'clicked_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'landing_url' => $target,
        ]);
    }

    /**
     * The active affiliate behind a code — null when the code is unknown or
     * the affiliate is suspended.
     */
    public function affiliateForCode(?string $code): ?Affiliate
    {
        if ($code === null || $code === '') {
            return null;
        }

        return Affiliate::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->first();
    }

    /**
     * The referral cookie's code when it maps to an active affiliate — the
     * value checkout stamps onto the order (and Paddle custom_data).
     */
    public function codeFromRequest(Request $request): ?string
    {
        $code = $request->cookie(self::COOKIE);

        return $this->affiliateForCode(is_string($code) ? $code : null)?->code;
    }

    /**
     * Link the visitor's latest unattributed click to the freshly registered
     * user (SPEC §17.1 step 3). A no-op without a valid cookie — most
     * signups are organic.
     */
    public function linkSignup(User $user, Request $request): void
    {
        $affiliate = $this->affiliateForCode(
            is_string($code = $request->cookie(self::COOKIE)) ? $code : null
        );

        if ($affiliate === null) {
            return;
        }

        $referral = $affiliate->referrals()
            ->whereNull('referred_user_id')
            ->latest('clicked_at')
            ->latest('id')
            ->first();

        $referral?->update([
            'referred_user_id' => $user->id,
            'converted_at' => now(),
        ]);
    }

    /**
     * Cookie lifetime in minutes, from the admin-editable days knob
     * (`affiliate.cookie_days`, SPEC §17.2).
     */
    public function cookieMinutes(): int
    {
        return (int) $this->settings->get('affiliate.cookie_days') * 24 * 60;
    }
}
