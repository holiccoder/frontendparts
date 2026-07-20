<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Checkout pages (SPEC §15.3): `/checkout/{plan}` hosts the Paddle overlay
 * (CSR + noindex) with a period selector whose price always comes from
 * `plan_prices`; `/checkout/success` shows the license summary. Tests assert
 * props/routes/middleware — never Paddle.js itself.
 */
class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.api_key' => 'pdl_test_api_key',
            'cashier.sandbox' => true,
            'services.paddle.client_side_token' => 'pdl_test_client_side_token',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/customers')) {
                return Http::response(['data' => []]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/customers')) {
                return Http::response(['data' => [
                    'id' => 'ctm_test_123',
                    'name' => $request['name'] ?? '',
                    'email' => $request['email'],
                ]]);
            }

            return Http::response(['data' => []]);
        });
    }

    public function test_checkout_page_csr_with_noindex()
    {
        $user = User::factory()->create();

        $this->price(OrderPlan::Pro, BillingPeriod::Monthly, 'pri_pro_monthly_123');

        $this->actingAs($user)
            ->get('/checkout/pro')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/show')
                ->where('plan', 'pro')
                ->where('paddle.token', 'pdl_test_client_side_token')
                ->where('paddle.environment', 'sandbox')
                ->where('successUrl', route('checkout.success'))
            );
    }

    public function test_period_selector_passes_correct_price()
    {
        $user = User::factory()->create();

        $this->price(OrderPlan::Pro, BillingPeriod::Monthly, 'pri_pro_monthly_123');
        $this->price(OrderPlan::Pro, BillingPeriod::Quarterly, 'pri_pro_quarterly_123');
        $this->price(OrderPlan::Pro, BillingPeriod::Yearly, 'pri_pro_yearly_123');
        $this->price(OrderPlan::Pro, BillingPeriod::Lifetime, 'pri_pro_lifetime_123');

        $this->actingAs($user)
            ->get('/checkout/pro?period=quarterly')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/show')
                ->where('selectedPeriod', 'quarterly')
                ->where('checkout.items.0.priceId', 'pri_pro_quarterly_123')
                ->has('periods', 4)
                ->where('periods.monthly.price_id', 'pri_pro_monthly_123')
                ->where('periods.quarterly.price_id', 'pri_pro_quarterly_123')
                ->where('periods.yearly.price_id', 'pri_pro_yearly_123')
                ->where('periods.lifetime.price_id', 'pri_pro_lifetime_123')
            );

        // Lifetime resolves its own one-time price id.
        $this->actingAs($user)
            ->get('/checkout/pro?period=lifetime')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedPeriod', 'lifetime')
                ->where('checkout.items.0.priceId', 'pri_pro_lifetime_123')
            );

        // An unknown period is not silently accepted.
        $this->actingAs($user)->get('/checkout/pro?period=weekly')->assertNotFound();

        // Free has no checkout; unknown plans 404.
        $this->actingAs($user)->get('/checkout/free')->assertNotFound();
    }

    public function test_success_page_shows_license_summary()
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 108.00,
            'currency' => 'USD',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->get('/checkout/success')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/success')
                ->where('license.plan', 'pro')
                ->where('license.billing_period', 'yearly')
                ->where('license.status', 'active')
                ->where('license.amount', '108.00')
                ->where('license.currency', 'USD')
                ->has('license.ends_at')
                ->has('nextSteps', 2)
            );
    }

    public function test_auth_required()
    {
        $this->get('/checkout/pro')->assertRedirect('/login');
        $this->get('/checkout/starter')->assertRedirect('/login');
        $this->get('/checkout/success')->assertRedirect('/login');
    }

    private function price(OrderPlan $plan, BillingPeriod $period, string $paddlePriceId): PlanPrice
    {
        return PlanPrice::factory()->create([
            'plan' => $plan,
            'period' => $period,
            'provider' => PlanProvider::Paddle,
            'paddle_price_id' => $paddlePriceId,
        ]);
    }
}
