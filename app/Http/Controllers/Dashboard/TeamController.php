<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team management (task 5.2, CSR dashboard zone): the owner's home for
 * their organization — member list with live seat usage against the team
 * order's seat count, pending invitations with revoke, and the invite form.
 * Members who own no organization see the organizations they belong to;
 * everyone else gets the create-organization form.
 *
 * v1 keeps one organization per owner and owner-only management; the
 * admin/member roles exist in the vocabulary but carry no extra rights yet.
 */
class TeamController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $organization = $user->ownedOrganizations()
            ->with(['members', 'pendingInvitations'])
            ->first();

        return Inertia::render('dashboard/team', [
            'organization' => $organization === null
                ? null
                : $this->serializeOrganization($organization, $user),
            'memberships' => $this->memberships($user),
        ]);
    }

    /**
     * Create the user's organization (v1: one per owner). The owner always
     * occupies the first seat.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        if ($request->user()->ownedOrganizations()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'You already own an organization.',
            ]);
        }

        $organization = Organization::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8)),
            'owner_user_id' => $request->user()->id,
        ]);

        $organization->members()->attach($request->user()->id, [
            'role' => OrganizationRole::Owner->value,
        ]);

        return redirect()->route('dashboard.team')
            ->with('notice', "Organization “{$organization->name}” created — invite your team below.");
    }

    /**
     * @return array{id: int, name: string, seats_used: int, seat_limit: int|null, plan_active: bool, checkout_url: string, members: list<array{id: int, name: string, email: string, role: string, you: bool}>, invitations: list<array{id: int, email: string, invited_at: string|null}>}
     */
    private function serializeOrganization(Organization $organization, User $user): array
    {
        $entitledOrder = $this->entitlements->entitledTeamOrder($organization);

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'seats_used' => $organization->members->count(),
            'seat_limit' => $entitledOrder?->seats,
            'plan_active' => $entitledOrder !== null,
            'checkout_url' => route('checkout.show', ['plan' => 'team']),
            'members' => $organization->members
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'you' => $member->id === $user->id,
                ])
                ->values()
                ->all(),
            'invitations' => $organization->pendingInvitations
                ->map(fn ($invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'invited_at' => $invitation->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Organizations the user belongs to without owning them.
     *
     * @return list<array{name: string, role: string, owner: string, plan_active: bool}>
     */
    private function memberships(User $user): array
    {
        return $user->organizations()
            ->where('organizations.owner_user_id', '!=', $user->id)
            ->with('owner')
            ->get()
            ->map(fn (Organization $organization): array => [
                'name' => $organization->name,
                'role' => $organization->pivot->role,
                'owner' => $organization->owner->name,
                'plan_active' => $this->entitlements->entitledTeamOrder($organization) !== null,
            ])
            ->all();
    }
}
