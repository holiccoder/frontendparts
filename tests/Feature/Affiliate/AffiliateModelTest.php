<?php

namespace Tests\Feature\Affiliate;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Affiliate data model (SPEC §17.3) + the admin-editable program knobs
 * registered in platform settings (§17.2, §8.7).
 */
class AffiliateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_code_unique_per_user()
    {
        $affiliate = Affiliate::factory()->create();

        $this->assertSame(AffiliateStatus::Active, $affiliate->status);

        // Codes are globally unique — they appear in public `/r/{code}` URLs.
        try {
            Affiliate::factory()->create(['code' => $affiliate->code]);
            $this->fail('A duplicate referral code did not hit the unique index.');
        } catch (QueryException) {
            // expected
        }

        // One affiliate account per user (SPEC §17.3: user_id unique).
        try {
            Affiliate::factory()->create(['user_id' => $affiliate->user_id]);
            $this->fail('A second affiliate for the same user did not hit the unique index.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(1, Affiliate::query()->where('code', $affiliate->code)->count());
    }

    public function test_commission_status_enum_and_unique_order()
    {
        $commission = AffiliateCommission::factory()->create([
            'status' => CommissionStatus::Pending,
        ]);

        // The lifecycle states cast to the enum (SPEC §17.3).
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertSame(
            ['pending', 'payable', 'paid', 'voided'],
            array_map(fn (CommissionStatus $status): string => $status->value, CommissionStatus::cases()),
        );

        // One commission per order per affiliate — also the webhook
        // idempotency guard (SPEC §17.3).
        try {
            AffiliateCommission::factory()->create([
                'affiliate_id' => $commission->affiliate_id,
                'order_id' => $commission->order_id,
            ]);
            $this->fail('A duplicate (order, affiliate) commission did not hit the unique index.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(1, AffiliateCommission::query()->where('order_id', $commission->order_id)->count());
    }

    public function test_settings_defaults_registered()
    {
        $settings = app(Settings::class);

        // The §17.2 knobs, admin-editable via the Settings page (§8.7).
        $this->assertSame(30, $settings->get('affiliate.commission_rate'));
        $this->assertSame(30, $settings->get('affiliate.cookie_days'));
        $this->assertSame(12, $settings->get('affiliate.recurring_months'));
        $this->assertSame(30, $settings->get('affiliate.holding_days'));
        $this->assertSame(50, $settings->get('affiliate.payout_threshold'));
    }
}
