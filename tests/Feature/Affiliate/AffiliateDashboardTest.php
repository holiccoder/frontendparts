<?php

namespace Tests\Feature\Affiliate;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Affiliate dashboard (SPEC §17.4, CSR zone): the self-serve join flow
 * (terms acceptance, §17.7), the overview stats (clicks, signups,
 * conversion rate, pending/payable/paid earnings), the referral link,
 * commissions, payout history and the payout-method form. Suspended
 * affiliates keep read-only access to their history.
 */
class AffiliateDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_creates_affiliate_with_code()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('dashboard.affiliate.join'), ['terms' => 'on'])
            ->assertRedirect(route('dashboard.affiliate'));

        $affiliate = $user->refresh()->affiliate;

        $this->assertNotNull($affiliate);
        $this->assertSame(AffiliateStatus::Active, $affiliate->status);
        $this->assertSame(8, strlen($affiliate->code));
        $this->assertNotNull($affiliate->terms_accepted_at);

        // Joining twice is an idempotent bounce — one affiliate per user.
        $this->actingAs($user)
            ->post(route('dashboard.affiliate.join'), ['terms' => 'on'])
            ->assertRedirect(route('dashboard.affiliate'));

        $this->assertSame(1, Affiliate::query()->where('user_id', $user->id)->count());
    }

    public function test_overview_props_clicks_signups_earnings()
    {
        $affiliate = Affiliate::factory()->create();

        // Three clicks, two of them linked to signups (SPEC §17.1 step 3).
        AffiliateReferral::factory()->create(['affiliate_id' => $affiliate->id]);
        AffiliateReferral::factory()->converted()->count(2)->create(['affiliate_id' => $affiliate->id]);

        AffiliateCommission::factory()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '32.40',
            'status' => CommissionStatus::Pending,
        ]);
        AffiliateCommission::factory()->payable()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '10.00',
        ]);
        AffiliateCommission::factory()->paid()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '50.00',
        ]);
        // Voided commissions show in the table but never in earnings.
        AffiliateCommission::factory()->voided()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '99.00',
        ]);

        $this->actingAs($affiliate->user)
            ->get(route('dashboard.affiliate'))
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/affiliate')
                ->where('affiliate.code', $affiliate->code)
                ->where('affiliate.status', 'active')
                ->where('affiliate.referral_url', route('affiliate.referral', ['code' => $affiliate->code]))
                ->where('stats.clicks', 3)
                ->where('stats.signups', 2)
                ->where('stats.conversion_rate', 66.7)
                ->where('stats.earnings.pending.0', ['currency' => 'USD', 'amount' => '32.40'])
                ->where('stats.earnings.payable.0', ['currency' => 'USD', 'amount' => '10.00'])
                ->where('stats.earnings.paid.0', ['currency' => 'USD', 'amount' => '50.00'])
                ->has('commissions', 4)
                ->where('settings.commission_rate', 30)
                ->where('settings.payout_threshold', 50)
            );
    }

    public function test_payout_method_saved()
    {
        $affiliate = Affiliate::factory()->create();

        $this->actingAs($affiliate->user)
            ->put(route('dashboard.affiliate.payout-method.update'), [
                'method' => 'wise',
                'email' => 'jane@example.com',
                'account_name' => 'Jane Doe',
            ])
            ->assertRedirect(route('dashboard.affiliate'));

        $this->assertSame([
            'method' => 'wise',
            'email' => 'jane@example.com',
            'account_name' => 'Jane Doe',
        ], $affiliate->refresh()->payout_method);

        // Validation: only the PayPal / Wise rails (SPEC §17.2).
        $this->actingAs($affiliate->user)
            ->put(route('dashboard.affiliate.payout-method.update'), [
                'method' => 'crypto',
                'email' => 'jane@example.com',
            ])
            ->assertSessionHasErrors('method');
    }

    public function test_non_affiliate_sees_join_page()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.affiliate'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/affiliate')
                ->where('affiliate', null)
                ->where('stats', null)
                ->where('terms_url', route('legal.affiliate-terms'))
                ->where('settings.commission_rate', 30)
            );
    }

    public function test_suspended_affiliate_read_only()
    {
        $affiliate = Affiliate::factory()->suspended()->create();

        // History stays visible…
        $this->actingAs($affiliate->user)
            ->get(route('dashboard.affiliate'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/affiliate')
                ->where('affiliate.status', 'suspended')
            );

        // …but the payout coordinates are frozen while suspended.
        $this->actingAs($affiliate->user)
            ->put(route('dashboard.affiliate.payout-method.update'), [
                'method' => 'paypal',
                'email' => 'jane@example.com',
            ])
            ->assertForbidden();

        $this->assertNull($affiliate->refresh()->payout_method);
    }
}
