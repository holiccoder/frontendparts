<?php

namespace Tests\Feature\Admin;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_registered_default()
    {
        $settings = app(Settings::class);

        $this->assertSame(14, $settings->get('billing.refund_window_days'));
        $this->assertSame(0.14, $settings->get('fx.cny_to_usd'));
        $this->assertSame(30, $settings->get('affiliate.commission_rate'));
        $this->assertSame(30, $settings->get('affiliate.cookie_days'));
        $this->assertSame(12, $settings->get('affiliate.recurring_months'));
        $this->assertSame(30, $settings->get('affiliate.holding_days'));
        $this->assertSame(50, $settings->get('affiliate.payout_threshold'));
    }

    public function test_set_persists_and_flushes_cache()
    {
        $settings = app(Settings::class);

        $this->assertSame(14, $settings->get('billing.refund_window_days'));

        // An out-of-band write is hidden by the resolved-value cache.
        DB::table('settings')->updateOrInsert(
            ['key' => 'billing.refund_window_days'],
            ['value' => json_encode(99), 'created_at' => now(), 'updated_at' => now()],
        );
        $this->assertSame(14, $settings->get('billing.refund_window_days'));

        // set() persists and flushes both cache layers for the key.
        $settings->set('billing.refund_window_days', 30);

        $this->assertSame(30, $settings->get('billing.refund_window_days'));
        $this->assertDatabaseHas('settings', [
            'key' => 'billing.refund_window_days',
            'value' => '30',
        ]);
    }

    public function test_typed_casts_round_trip()
    {
        $settings = app(Settings::class);

        $settings->set('billing.refund_window_days', 21);
        $this->assertSame(21, $settings->get('billing.refund_window_days'));

        $settings->set('fx.cny_to_usd', 0.15);
        $this->assertSame(0.15, $settings->get('fx.cny_to_usd'));

        $settings->set('affiliate.payout_threshold', 75);
        $this->assertSame(75, $settings->get('affiliate.payout_threshold'));
    }

    public function test_unknown_key_rejected()
    {
        $settings = app(Settings::class);

        try {
            $settings->set('unknown.key', 1);
            $this->fail('set() did not reject an unknown key.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('unknown.key', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);

        $settings->get('unknown.key');
    }
}
