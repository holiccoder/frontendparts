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
        $this->assertSame(1, $settings->get('plans.project_limit.free'));
        $this->assertSame(14, $settings->get('billing.refund_window_days'));
        $this->assertTrue($settings->get('features.preview_dark_toggle'));

        Livewire::test(Settings::class)
            ->fillForm([
                'plans_project_limit_free' => 2,
                'plans_project_limit_starter' => 5,
                'plans_project_limit_pro' => null,
                'billing_refund_window_days' => 30,
                'features_preview_dark_toggle' => false,
                'features_tree_interactions' => false,
                'goals_launch_component_target' => 120,
                'goals_components_per_month' => 25,
                'goals_organic_visits_monthly' => 20000,
                'goals_signup_conversion_pct' => 7,
                'goals_paid_conversion_pct_min' => 4,
                'goals_paid_conversion_pct_max' => 6,
                'goals_churn_max_pct' => 3,
                'goals_mrr_target_usd' => 3000,
                'fx_cny_to_usd' => 0.15,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Plans & limits
        $this->assertSame(2, $settings->get('plans.project_limit.free'));
        $this->assertSame(5, $settings->get('plans.project_limit.starter'));
        $this->assertNull($settings->get('plans.project_limit.pro'), 'empty Pro limit must persist as unlimited (null)');
        $this->assertSame(30, $settings->get('billing.refund_window_days'));

        // Feature flags
        $this->assertFalse($settings->get('features.preview_dark_toggle'));
        $this->assertFalse($settings->get('features.tree_interactions'));

        // Goals
        $this->assertSame(120, $settings->get('goals.launch_component_target'));
        $this->assertSame(0.15, $settings->get('fx.cny_to_usd'));
    }

    public function test_goal_targets_saved()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        // The form mounts with current values, so only the goal fields need filling.
        Livewire::test(Settings::class)
            ->fillForm([
                'goals_launch_component_target' => 150,
                'goals_components_per_month' => 30,
                'goals_organic_visits_monthly' => 50000,
                'goals_signup_conversion_pct' => 8,
                'goals_paid_conversion_pct_min' => 4,
                'goals_paid_conversion_pct_max' => 7,
                'goals_churn_max_pct' => 4,
                'goals_mrr_target_usd' => 5000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(PlatformSettings::class);

        $this->assertSame(150, $settings->get('goals.launch_component_target'));
        $this->assertSame(30, $settings->get('goals.components_per_month'));
        $this->assertSame(50000, $settings->get('goals.organic_visits_monthly'));
        $this->assertSame(8, $settings->get('goals.signup_conversion_pct'));
        $this->assertSame(4, $settings->get('goals.paid_conversion_pct_min'));
        $this->assertSame(7, $settings->get('goals.paid_conversion_pct_max'));
        $this->assertSame(4, $settings->get('goals.churn_max_pct'));
        $this->assertSame(5000, $settings->get('goals.mrr_target_usd'));
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
