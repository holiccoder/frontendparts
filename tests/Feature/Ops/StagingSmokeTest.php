<?php

namespace Tests\Feature\Ops;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\Blog;
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

    public function test_blog_index_200(): void
    {
        Blog::factory()->published()->create();

        $this->get('/blog')
            ->assertOk();
    }

    public function test_legal_page_200(): void
    {
        $this->get('/terms')
            ->assertOk();
    }

    public function test_docs_redirects_to_first_page(): void
    {
        $this->get('/docs')
            ->assertRedirect();
    }

    public function test_checkout_route_redirects_to_login(): void
    {
        $this->get('/checkout/starter')
            ->assertRedirect();
    }
}
