<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Join the affiliate program (SPEC §17.1 step 1, §17.4): self-serve from
 * the dashboard. Joining requires accepting the Affiliate Program Terms
 * (§17.7) — the acceptance timestamp is recorded on the affiliate row.
 * Already-affiliates are bounced back idempotently.
 */
class AffiliateJoinController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->affiliate !== null) {
            return redirect()->route('dashboard.affiliate');
        }

        $request->validate([
            'terms' => ['accepted'],
        ]);

        $request->user()->affiliate()->create([
            'code' => Affiliate::generateCode(),
            'status' => AffiliateStatus::Active,
            'terms_accepted_at' => now(),
        ]);

        return redirect()->route('dashboard.affiliate');
    }
}
