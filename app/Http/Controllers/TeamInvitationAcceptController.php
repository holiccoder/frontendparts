<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signed invitation acceptance (task 5.2): the link from the invitation
 * email. Guests are bounced through login/registration and back by the auth
 * middleware (post-registration claim); the signed middleware guarantees the
 * link is untampered, and revocation works by deleting the invitation row.
 *
 * Accepting attaches the user with the invited role and stamps accepted_at.
 * Clicking a link that was delivered to the invitee's mailbox proves control
 * of that address, so an unverified account is marked verified here — the
 * same proof Laravel's own verification link relies on.
 */
class TeamInvitationAcceptController extends Controller
{
    public function __invoke(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        if (! $invitation->isPending()) {
            return redirect()->route('dashboard.team')
                ->with('notice', 'This invitation was already used or revoked.');
        }

        abort_unless(Str::lower($user->email) === Str::lower($invitation->email), 403);

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->organization->members()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role->value],
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });

        return redirect()->route('dashboard.team')
            ->with('notice', "You've joined {$invitation->organization->name}.");
    }
}
