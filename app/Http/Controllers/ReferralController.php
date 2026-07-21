<?php

namespace App\Http\Controllers;

use App\Services\Affiliates\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/r/{code}` — the affiliate referral link (SPEC §17.1 step 2). A valid,
 * active code records the click (ip, user agent, landing URL, timestamp),
 * stamps the 30-day first-party attribution cookie, and 301-redirects to the
 * target page (pricing — the conversion entry). An unknown or suspended code
 * silently redirects without recording anything (fraud control, §17.2). The
 * route is rate-limited per IP.
 */
class ReferralController extends Controller
{
    public function __invoke(Request $request, ReferralService $referrals, string $code): RedirectResponse
    {
        $target = route('pricing');

        $referral = $referrals->recordClick($code, $request, $target);

        $response = redirect()->to($target, 301);

        if ($referral !== null) {
            $response->cookie(ReferralService::COOKIE, $referral->affiliate->code, $referrals->cookieMinutes());
        }

        return $response;
    }
}
