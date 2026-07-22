<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * `/pricing` (SPEC §7.1, §7.2, §15.1): public SSR page with a plan ×
 * period toggle fed from `plan_prices` (Paddle/USD rows only — domestic
 * CNY rows never leak in), the SPEC §7.1 feature matrix, and a billing
 * FAQ. Missing plan × period rows render as unavailable, never crash.
 */
class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_ssr_200()
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertHeaderMissing('X-SSR-Skipped')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertInertia(fn (Assert $page) => $page
                ->component('pricing')
                ->has('plans')
                ->has('comparison')
                ->has('faq')
                ->where('meta.canonical', url('/pricing'))
            );
    }

    public function test_prices_come_from_plan_prices_table()
    {
        PlanPrice::factory()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 72.00,
            'currency' => 'USD',
        ]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->component('pricing')
            ->where('plans.starter.prices.yearly.amount', '72.00')
            ->where('plans.starter.prices.yearly.currency', 'USD')
            ->where('plans.starter.prices.yearly.per_month', '6.00')
        );

        // Repricing without deploys (SPEC §7.3): an admin edit is reflected immediately.
        PlanPrice::query()
            ->where('plan', OrderPlan::Starter)
            ->where('period', BillingPeriod::Yearly)
            ->update(['amount' => 96.00]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->where('plans.starter.prices.yearly.amount', '96.00')
            ->where('plans.starter.prices.yearly.per_month', '8.00')
        );

        // Domestic CNY rows (SPEC §7.5, Phase 3) never leak into the public page.
        PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Yearly,
            'amount' => 518.00,
        ]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->where('plans.starter.prices.yearly.amount', '96.00')
            ->where('plans.starter.prices.yearly.currency', 'USD')
        );
    }

    public function test_all_four_periods_present()
    {
        // No plan_prices rows at all: every period still renders, marked unavailable.
        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->component('pricing')
            ->where('periods', ['monthly', 'quarterly', 'yearly', 'lifetime'])
            ->has('plans.starter.prices', 4)
            ->has('plans.pro.prices', 4)
            ->where('plans.starter.prices.monthly.amount', null)
            ->where('plans.starter.prices.quarterly.amount', null)
            ->where('plans.starter.prices.yearly.amount', null)
            ->where('plans.starter.prices.lifetime.amount', null)
            ->where('plans.starter.prices.lifetime.per_month', null)
            ->where('plans.pro.prices.monthly.amount', null)
            ->where('plans.pro.prices.quarterly.amount', null)
            ->where('plans.pro.prices.yearly.amount', null)
            ->where('plans.pro.prices.lifetime.amount', null)
        );
    }

    public function test_comparison_table_matches_feature_matrix()
    {
        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->component('pricing')
            ->has('comparison', 5)
            ->where('comparison.0', ['feature' => 'Core features', 'free' => true, 'starter' => true, 'pro' => true])
            ->where('comparison.1', ['feature' => 'Usage allowance', 'free' => 'Limited', 'starter' => 'Standard', 'pro' => 'Highest'])
            ->where('comparison.2', ['feature' => 'Priority support', 'free' => false, 'starter' => true, 'pro' => true])
            ->where('comparison.3', ['feature' => 'Advanced features', 'free' => false, 'starter' => false, 'pro' => true])
            ->where('comparison.4', ['feature' => 'Early access to new features', 'free' => false, 'starter' => false, 'pro' => true])
        );
    }
}
