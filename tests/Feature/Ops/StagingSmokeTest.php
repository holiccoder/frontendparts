<?php

namespace Tests\Feature\Ops;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\Category;
use App\Models\Component;
use App\Models\PlanPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_200(): void
    {
        $this->get('/')
            ->assertOk();
    }

    public function test_catalog_page_200(): void
    {
        $this->get('/components')
            ->assertOk();
    }

    public function test_component_detail_page_200(): void
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        Component::factory()->published()->free()->create([
            'slug' => 'elements/hero-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->get('/components/hero/hero-01')
            ->assertOk();
    }

    public function test_pricing_page_200(): void
    {
        foreach ([BillingPeriod::Monthly, BillingPeriod::Yearly, BillingPeriod::Quarterly, BillingPeriod::Lifetime] as $period) {
            foreach ([OrderPlan::Starter, OrderPlan::Pro] as $plan) {
                PlanPrice::factory()->create([
                    'plan' => $plan,
                    'period' => $period,
                    'provider' => PlanProvider::Paddle,
                    'currency' => 'USD',
                ]);
            }
        }

        $this->get('/pricing')
            ->assertOk();
    }

    public function test_checkout_route_redirects_to_login(): void
    {
        $this->get('/checkout/starter')
            ->assertRedirect();
    }
}
