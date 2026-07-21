<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Save the affiliate's payout coordinates (SPEC §17.4): PayPal or Wise —
 * the rails the monthly admin batch pays out on (§17.2). The batch
 * snapshots the method at creation time, so edits here never rewrite the
 * history of already-created payouts.
 *
 * Suspended affiliates are read-only (see AffiliateController): their
 * payout coordinates are frozen while the admin reviews the account.
 */
class AffiliatePayoutMethodController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;

        abort_if($affiliate === null, 404);
        abort_unless($affiliate->isActive(), 403);

        $validated = $request->validate([
            'method' => ['required', Rule::in(['paypal', 'wise'])],
            'email' => ['required', 'email', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $affiliate->update(['payout_method' => $validated]);

        return redirect()->route('dashboard.affiliate');
    }
}
