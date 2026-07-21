<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Team dashboard (task 5.2): organization creation (one per owner), email
 * invitations with a queued transactional mail, signed-link acceptance
 * (existing users + post-registration claim), revocation, member removal
 * and the owner-only authorization boundary.
 */
class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_an_organization()
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('dashboard.team.store'), ['name' => 'Acme Studios'])
            ->assertRedirect(route('dashboard.team'));

        $organization = Organization::query()->sole();

        $this->assertSame('Acme Studios', $organization->name);
        $this->assertSame($owner->id, $organization->owner_user_id);
        $this->assertNotNull($organization->slug);

        // The owner occupies the first seat.
        $this->assertSame(
            OrganizationRole::Owner->value,
            $organization->members()->where('users.id', $owner->id)->sole()->pivot->role,
        );

        $this->actingAs($owner)
            ->get(route('dashboard.team'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/team')
                ->where('organization.name', 'Acme Studios')
                ->where('organization.seats_used', 1)
                ->where('organization.plan_active', false)
                ->has('organization.members', 1)
            );
    }

    public function test_one_organization_per_owner()
    {
        $owner = User::factory()->create();
        Organization::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('dashboard.team'))
            ->post(route('dashboard.team.store'), ['name' => 'Second Org'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Organization::query()->count());
    }

    public function test_owner_invites_a_member_and_the_mail_is_queued()
    {
        Notification::fake();

        [$organization, $owner] = $this->teamOrganization(3);

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'newbie@example.com'])
            ->assertSessionHasNoErrors();

        $invitation = $organization->invitations()->sole();

        $this->assertSame('newbie@example.com', $invitation->email);
        $this->assertSame(OrganizationRole::Member, $invitation->role);
        $this->assertTrue($invitation->isPending());
        $this->assertSame($owner->id, $invitation->invited_by_user_id);

        // No account at that address → on-demand mail.
        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            fn ($notification, $channels, $notifiable): bool => in_array('mail', $channels, true)
                && $notifiable->routes['mail'] === 'newbie@example.com'
                && $notification->invitation->is($invitation),
        );

        // An existing user is notified directly instead (mail + database).
        $member = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => $member->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $member,
            OrganizationInvitationNotification::class,
            fn ($notification, $channels): bool => in_array('mail', $channels, true)
                && in_array('database', $channels, true),
        );
    }

    public function test_invitations_are_capped_by_the_purchased_seats()
    {
        Notification::fake();

        // Two seats: the owner takes one, one invitation fits.
        [$organization, $owner] = $this->teamOrganization(2);

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'one@example.com'])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'two@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $organization->invitations()->count());

        // No entitled team order at all → no seats to invite into.
        $other = User::factory()->create();
        Organization::factory()->create(['owner_user_id' => $other->id]);

        $this->actingAs($other)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');

        // Only the first (in-cap) invitation ever mailed.
        Notification::assertSentOnDemandTimes(OrganizationInvitationNotification::class, 1);
    }

    public function test_invitation_rejects_duplicates_and_existing_members()
    {
        Notification::fake();

        [$organization, $owner] = $this->teamOrganization(5);

        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'pending@example.com',
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'pending@example.com'])
            ->assertSessionHasErrors('email');

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $this->actingAs($owner)
            ->post(route('dashboard.team.invitations.store'), ['email' => $member->email])
            ->assertSessionHasErrors('email');
    }

    public function test_invitee_accepts_via_the_signed_link()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $invitee = User::factory()->unverified()->create();

        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => $invitee->email,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->get($invitation->acceptUrl())
            ->assertRedirect(route('dashboard.team'));

        $this->assertFalse($invitation->refresh()->isPending());
        $this->assertTrue($organization->members()->where('users.id', $invitee->id)->exists());

        // The mailbox proof doubles as email verification.
        $this->assertTrue($invitee->refresh()->hasVerifiedEmail());

        // Accepting twice is a no-op redirect, not an error.
        $this->actingAs($invitee)
            ->get($invitation->acceptUrl())
            ->assertRedirect(route('dashboard.team'));

        $this->assertSame(2, $organization->members()->count());
    }

    public function test_signed_link_rejects_the_wrong_user_and_unsigned_requests()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'invitee@example.com',
            'invited_by_user_id' => $owner->id,
        ]);

        // Signed URL, but the authenticated user's email doesn't match.
        $this->actingAs(User::factory()->create())
            ->get($invitation->acceptUrl())
            ->assertForbidden();

        // No signature at all.
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->actingAs($invitee)
            ->get(route('team.invitations.accept', ['invitation' => $invitation]))
            ->assertForbidden();

        $this->assertTrue($invitation->refresh()->isPending());
    }

    public function test_guests_are_bounced_through_login_and_back()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'invitee@example.com',
            'invited_by_user_id' => $owner->id,
        ]);

        // The auth middleware captures the signed URL as the intended
        // destination — the post-registration claim path.
        $this->get($invitation->acceptUrl())->assertRedirect('/login');
    }

    public function test_owner_revokes_a_pending_invitation()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('dashboard.team.invitations.destroy', $invitation))
            ->assertRedirect();

        $this->assertDatabaseMissing('organization_invitations', ['id' => $invitation->id]);
    }

    public function test_owner_removes_a_member_and_entitlement_drops_immediately()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $this->assertTrue(
            app(EntitlementService::class)->for($member)->isPaid(),
        );

        $this->actingAs($owner)
            ->delete(route('dashboard.team.members.destroy', $member))
            ->assertRedirect();

        $this->assertFalse($organization->members()->where('users.id', $member->id)->exists());

        $this->assertFalse(
            app(EntitlementService::class)->for($member)->isPaid(),
        );
    }

    public function test_the_owner_seat_cannot_be_removed()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $this->actingAs($owner)
            ->delete(route('dashboard.team.members.destroy', $owner))
            ->assertForbidden();

        $this->assertTrue($organization->members()->where('users.id', $owner->id)->exists());
    }

    public function test_non_owners_cannot_manage_the_organization()
    {
        Notification::fake();

        [$organization, $owner] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $owner->id,
        ]);

        // A plain member cannot invite, revoke or remove.
        $this->actingAs($member)
            ->post(route('dashboard.team.invitations.store'), ['email' => 'sneaky@example.com'])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('dashboard.team.invitations.destroy', $invitation))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('dashboard.team.members.destroy', $owner))
            ->assertForbidden();

        // A user with no organization at all cannot manage either.
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->delete(route('dashboard.team.members.destroy', $member))
            ->assertForbidden();

        $this->assertSame(2, $organization->members()->count());
        $this->assertDatabaseHas('organization_invitations', ['id' => $invitation->id]);
    }

    public function test_member_sees_their_memberships_on_the_team_page()
    {
        [$organization] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $this->actingAs($member)
            ->get(route('dashboard.team'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/team')
                ->where('organization', null)
                ->has('memberships', 1)
                ->where('memberships.0.name', $organization->name)
                ->where('memberships.0.plan_active', true)
            );
    }

    /**
     * An organization whose owner holds an Active team order with the given
     * seat count; the owner occupies the first seat.
     *
     * @return array{Organization, User}
     */
    private function teamOrganization(int $seats): array
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create(['owner_user_id' => $owner->id]);
        $organization->members()->attach($owner->id, ['role' => OrganizationRole::Owner->value]);

        Order::factory()->create([
            'user_id' => $owner->id,
            'plan' => OrderPlan::Team,
            'status' => OrderStatus::Active,
            'seats' => $seats,
        ]);

        return [$organization, $owner];
    }
}
