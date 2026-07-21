<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\RegionDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual currency switch (SPEC §7.5): persists the buyer's USD/CNY choice in
 * the session; RegionDetector prefers it over the geo-detect heuristic from
 * then on. Used by the pricing and checkout pages, guests included — the
 * session driver covers them.
 */
class CurrencySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:'.RegionDetector::USD.','.RegionDetector::CNY],
        ]);

        $request->session()->put(RegionDetector::SESSION_KEY, $validated['currency']);

        return back();
    }
}
