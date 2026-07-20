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

        $this->assertSame(1, $settings->get('plans.project_limit.free'));
        $this->assertSame(3, $settings->get('plans.project_limit.starter'));
        $this->assertNull($settings->get('plans.project_limit.pro'));
        $this->assertSame(14, $settings->get('billing.refund_window_days'));
        $this->assertTrue($settings->get('features.preview_dark_toggle'));
        $this->assertTrue($settings->get('features.tree_interactions'));
        $this->assertFalse($settings->get('features.live_edit'));
        $this->assertSame(100, $settings->get('goals.launch_component_target'));
        $this->assertSame(20, $settings->get('goals.components_per_month'));
        $this->assertSame(10000, $settings->get('goals.organic_visits_monthly'));
        $this->assertSame(5, $settings->get('goals.signup_conversion_pct'));
        $this->assertSame(3, $settings->get('goals.paid_conversion_pct_min'));
        $this->assertSame(5, $settings->get('goals.paid_conversion_pct_max'));
        $this->assertSame(5, $settings->get('goals.churn_max_pct'));
        $this->assertSame(2000, $settings->get('goals.mrr_target_usd'));
        $this->assertSame(0.14, $settings->get('fx.cny_to_usd'));
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

    public function test_typed_casts_int_bool_array()
    {
        $settings = app(Settings::class);

        $settings->set('plans.project_limit.free', 5);
        $this->assertSame(5, $settings->get('plans.project_limit.free'));

        $settings->set('features.preview_dark_toggle', false);
        $this->assertSame(false, $settings->get('features.preview_dark_toggle'));

        $settings->set('fx.cny_to_usd', 0.15);
        $this->assertSame(0.15, $settings->get('fx.cny_to_usd'));

        $settings->set('plans.project_limit.pro', ['unlimited' => true]);
        $this->assertSame(['unlimited' => true], $settings->get('plans.project_limit.pro'));
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
