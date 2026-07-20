<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-click unsubscribe (SPEC §16.3): the signed link carried by every
 * marketing email. Works logged-out — the signature is the authentication
 * — and opts the user out of ALL marketing categories in a single GET,
 * then shows a confirmation page. Transactional mail is unaffected.
 */
class UnsubscribeController extends Controller
{
    public function __invoke(User $user, NotificationPreferences $preferences): Response
    {
        $preferences->unsubscribeAll($user);

        return Inertia::render('unsubscribed', [
            'email' => $user->email,
        ]);
    }
}
