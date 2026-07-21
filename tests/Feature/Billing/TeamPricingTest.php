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
 * Team tier on `/pricing` (task 5.2): the page exposes the team plan with
 * per-seat prices from `plan_prices` for every period (never hardcoded,
 * missing rows render unavailable), while the SPEC §7.1 comparison matrix
 * keeps its Free/Starter/Pro shape.
 */
class TeamPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_exposes_team_plan_with_per_seat_prices()
    {
        PlanPrice::factory()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 108.00,
            'currency' => 'USD',
        ]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->component('pricing')
            ->where('plans.team.name', 'Team')
            ->where('plans.team.checkout_url', route('checkout.show', ['plan' => 'team']))
            ->has('plans.team.prices', 4)
            ->where('plans.team.prices.yearly.amount', '108.00')
            ->where('plans.team.prices.yearly.currency', 'USD')
            ->where('plans.team.prices.yearly.per_month', '9.00')
            ->where('plans.team.prices.monthly.amount', null)
        );
    }

    public function test_team_prices_reprice_without_a_deploy()
    {
        $price = PlanPrice::factory()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 13.50,
            'currency' => 'USD',
        ]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->where('plans.team.prices.monthly.amount', '13.50')
        );

        $price->update(['amount' => 16.00]);

        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->where('plans.team.prices.monthly.amount', '16.00')
        );
    }

    public function test_comparison_matrix_keeps_the_spec_shape()
    {
        // SPEC §7.1 is a Free/Starter/Pro matrix — adding the team tier must
        // not change the comparison rows (team sells from its own card).
        $this->get('/pricing')->assertInertia(fn (Assert $page) => $page
            ->has('comparison', 8)
            ->where('comparison.0', ['feature' => 'Browse + preview full catalog', 'free' => true, 'starter' => true, 'pro' => true])
        );
    }
}
