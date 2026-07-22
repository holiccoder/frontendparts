<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Filament\Pages\Settings;
use App\Filament\Resources\PlanPrices\Pages\CreatePlanPrice;
use App\Filament\Resources\PlanPrices\Pages\EditPlanPrice;
use App\Models\Admin;
use App\Support\Settings as PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_each_group_and_flushes_cache()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $settings = app(PlatformSettings::class);

        // Warm the resolved-value cache so the save proves it flushes.
        $this->assertSame(14, $settings->get('billing.refund_window_days'));
        $this->assertSame(0.14, $settings->get('fx.cny_to_usd'));
        $this->assertSame(30, $settings->get('affiliate.commission_rate'));

        Livewire::test(Settings::class)
            ->fillForm([
                'billing_refund_window_days' => 30,
                'fx_cny_to_usd' => 0.15,
                'affiliate_commission_rate' => 25,
                'affiliate_cookie_days' => 45,
                'affiliate_recurring_months' => 6,
                'affiliate_holding_days' => 14,
                'affiliate_payout_threshold' => 100,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Billing
        $this->assertSame(30, $settings->get('billing.refund_window_days'));
        $this->assertSame(0.15, $settings->get('fx.cny_to_usd'));

        // Affiliate
        $this->assertSame(25, $settings->get('affiliate.commission_rate'));
        $this->assertSame(45, $settings->get('affiliate.cookie_days'));
        $this->assertSame(6, $settings->get('affiliate.recurring_months'));
        $this->assertSame(14, $settings->get('affiliate.holding_days'));
        $this->assertSame(100, $settings->get('affiliate.payout_threshold'));
    }

    public function test_plan_price_crud_updates_checkout_amounts_without_deploy()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreatePlanPrice::class)
            ->fillForm([
                'plan' => OrderPlan::Starter->value,
                'period' => BillingPeriod::Quarterly->value,
                'provider' => PlanProvider::Paddle->value,
                'amount' => 49.00,
                'currency' => 'USD',
                'paddle_price_id' => 'pri_starter_quarterly',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $price = OrderPlan::Starter->price(BillingPeriod::Quarterly);

        $this->assertNotNull($price, 'checkout must resolve the newly created plan price without a deploy');
        $this->assertSame('49.00', $price->amount);
        $this->assertSame('pri_starter_quarterly', $price->paddle_price_id);

        // Duplicate plan+period+provider is rejected.
        Livewire::test(CreatePlanPrice::class)
            ->fillForm([
                'plan' => OrderPlan::Starter->value,
                'period' => BillingPeriod::Quarterly->value,
                'provider' => PlanProvider::Paddle->value,
                'amount' => 59.00,
                'currency' => 'USD',
            ])
            ->call('create')
            ->assertHasFormErrors(['provider']);

        // Editing the amount immediately changes what checkout resolves.
        Livewire::test(EditPlanPrice::class, ['record' => $price->id])
            ->fillForm([
                'amount' => 59.00,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('59.00', OrderPlan::Starter->price(BillingPeriod::Quarterly)?->amount);
    }
}
