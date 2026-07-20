<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferenceUpdateRequest;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Preference center (SPEC §16.3): digest / blog / product-updates are
 * individually opt-out-able; transactional mail is shown as mandatory and
 * has no flag to persist. The page also exposes the same signed one-click
 * unsubscribe URL that marketing mails carry.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, NotificationPreferences $preferences): Response
    {
        return Inertia::render('settings/notifications', [
            'preferences' => $preferences->for($request->user()),
        ]);
    }

    public function update(NotificationPreferenceUpdateRequest $request, NotificationPreferences $preferences): RedirectResponse
    {
        $preferences->update($request->user(), $request->validated());

        return back();
    }
}
