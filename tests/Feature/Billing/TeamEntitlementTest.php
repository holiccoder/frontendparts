<?php

namespace Tests\Feature\Billing;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team-tier entitlement resolution (task 5.2): the buyer's team order
 * entitles like any personal order, and every organization member inherits
 * the Pro-equivalent Team plan while the organization's team order (the
 * owner's latest team-plan order) entitles per the SPEC §7.3 state machine.
 *
 * Precedence rule: a personally entitled order always wins; organization
 * membership only lifts a user who would otherwise resolve to Free.
 */
class TeamEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_buyer_gets_pro_equivalent_entitlements()
    {
        $owner = User::factory()->create();

        Order::factory()->create([
            'user_id' => $owner->id,
            'plan' => OrderPlan::Team,
            'status' => OrderStatus::Active,
            'seats' => 5,
        ]);

        $entitlement = app(EntitlementService::class)->for($owner);

        $this->assertSame(OrderPlan::Team, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
        $this->assertTrue($entitlement->canExportToGithub());
        $this->assertNull($entitlement->projectLimit());
    }

    public function test_member_inherits_team_entitlements_while_order_active()
    {
        [$organization, $owner] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $entitlement = app(EntitlementService::class)->for($member);

        $this->assertSame(OrderPlan::Team, $entitlement->plan());
        $this->assertTrue($entitlement->isPaid());
        $this->assertTrue($entitlement->hasFullLibrary());
        $this->assertTrue($entitlement->canScaffold());
    }

    public function test_member_loses_entitlement_when_team_order_stops_entitling()
    {
        $service = app(EntitlementService::class);

        [$organization, $owner] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $this->assertSame(OrderPlan::Team, $service->for($member)->plan());

        // Expiry cuts the whole organization off (live resolution).
        $owner->orders()->update(['status' => OrderStatus::Expired]);

        $this->assertSame(OrderPlan::Free, $service->for($member)->plan());

        // A pending (unpaid) renewal order entitles nobody either.
        $owner->orders()->update(['status' => OrderStatus::Pending]);

        $this->assertSame(OrderPlan::Free, $service->for($member)->plan());
    }

    public function test_member_loses_entitlement_on_removal()
    {
        $service = app(EntitlementService::class);

        [$organization] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        $this->assertSame(OrderPlan::Team, $service->for($member)->plan());

        // Removal revokes immediately — resolution is live, nothing cached.
        $organization->members()->detach($member->id);

        $entitlement = $service->for($member);

        $this->assertSame(OrderPlan::Free, $entitlement->plan());
        $this->assertFalse($entitlement->hasFullLibrary());
    }

    public function test_personal_paid_order_beats_organization_membership()
    {
        [$organization] = $this->teamOrganization(3);

        // The member's own Starter license wins over the inherited Team plan.
        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        Order::factory()->create([
            'user_id' => $member->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);

        $entitlement = app(EntitlementService::class)->for($member);

        $this->assertSame(OrderPlan::Starter, $entitlement->plan());
        $this->assertFalse($entitlement->canScaffold());
        $this->assertSame(3, $entitlement->projectLimit());
    }

    public function test_organization_membership_lifts_free_users_only()
    {
        [$organization] = $this->teamOrganization(3);

        // An expired personal Pro license resolves to Free, so the
        // organization seat lifts the member back to Team.
        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        Order::factory()->create([
            'user_id' => $member->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Expired,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(OrderPlan::Team, app(EntitlementService::class)->for($member)->plan());
    }

    public function test_non_members_get_nothing_from_the_organization()
    {
        $this->teamOrganization(3);

        $outsider = User::factory()->create();

        $this->assertSame(OrderPlan::Free, app(EntitlementService::class)->for($outsider)->plan());
    }

    public function test_team_project_limit_reads_from_settings()
    {
        [$organization] = $this->teamOrganization(3);

        $member = User::factory()->create();
        $organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

        // Default: unlimited, like Pro.
        $this->assertNull(app(EntitlementService::class)->for($member)->projectLimit());

        // Admin re-tunes the team limit — reflected without a deploy.
        app(Settings::class)->set('plans.project_limit.team', 25);

        $this->assertSame(25, app(EntitlementService::class)->for($member)->projectLimit());
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
