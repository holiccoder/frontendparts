<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Team member removal (task 5.2): the organization owner removes a member —
 * the pivot row is detached, so the next EntitlementService read no longer
 * resolves a team seat for them (revocation is immediate, live resolution).
 * The owner's own seat cannot be removed.
 */
class TeamMemberController extends Controller
{
    public function destroy(Request $request, User $member): RedirectResponse
    {
        $organization = $request->user()->ownedOrganizations()->first();

        abort_if($organization === null, 403);
        abort_if($member->id === $organization->owner_user_id, 403);
        abort_unless($organization->members()->where('users.id', $member->id)->exists(), 404);

        $organization->members()->detach($member->id);

        return back()->with('notice', "{$member->name} was removed from {$organization->name}.");
    }
}
