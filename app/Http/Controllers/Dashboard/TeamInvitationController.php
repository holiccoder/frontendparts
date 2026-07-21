<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team invitations (task 5.2): the organization owner invites by email —
 * the invitee gets a queued transactional mail with a signed acceptance
 * link (existing users accept in place, new users register and are bounced
 * back to the link by the auth middleware). Inviting is capped by the
 * entitled team order's seat count: members plus pending invitations may
 * never exceed the purchased seats.
 */
class TeamInvitationController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
        ]);

        $organization = $request->user()->ownedOrganizations()->first();

        abort_if($organization === null, 403);

        $email = Str::lower($data['email']);

        if ($organization->members()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of your organization.',
            ]);
        }

        if ($organization->pendingInvitations()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An invitation is already pending for this address.',
            ]);
        }

        // Seat cap: members + pending invitations < purchased seats of the
        // entitled team order. No entitled order → no seats → buy first.
        $seats = $this->entitlements->entitledTeamOrder($organization)?->seats ?? 0;
        $used = $organization->members()->count() + $organization->pendingInvitations()->count();

        if ($used >= $seats) {
            throw ValidationException::withMessages([
                'email' => $seats === 0
                    ? 'Your team plan is not active — buy seats to invite members.'
                    : "All {$seats} seats are taken — buy more seats to invite another member.",
            ]);
        }

        $invitation = $organization->invitations()->create([
            'email' => $email,
            'role' => OrganizationRole::Member,
            'token' => Str::random(48),
            'invited_by_user_id' => $request->user()->id,
        ]);

        $invitation->loadMissing(['organization', 'inviter']);

        $notification = new OrganizationInvitationNotification($invitation);
        $recipient = User::query()->where('email', $email)->first();

        // Existing users get the mail + database channels (SPEC §16.1);
        // not-yet-registered invitees get an on-demand mail.
        $recipient !== null
            ? $recipient->notify($notification)
            : Notification::route('mail', $email)->notify($notification);

        return back()->with('notice', "Invitation sent to {$email}.");
    }

    /**
     * Revoke a pending invitation (owner only) — deleting the row invalidates
     * the signed acceptance link.
     */
    public function destroy(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $organization = $request->user()->ownedOrganizations()->first();

        abort_if($organization === null || $invitation->organization_id !== $organization->id, 403);
        abort_unless($invitation->isPending(), 404);

        $invitation->delete();

        return back()->with('notice', "Invitation to {$invitation->email} revoked.");
    }
}
