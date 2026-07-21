<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Billing\PaddleCheckoutService;
use App\Services\Billing\RegionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Team per-seat checkout (task 5.2): `/checkout/team` takes a `?seats=`
 * count — the Paddle line item quantity is the seat count, the stored order
 * amount is the per-seat plan_prices amount multiplied by the seats, and
 * the seat number rides the order into entitlement resolution. Solo plans
 * never take seats. All outbound Paddle calls are faked.
 */
class TeamCheckoutTest extends TestCase
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
    }

    public function test_paddle_team_checkout_bills_per_seat()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 13.50,
            'paddle_price_id' => 'pri_team_monthly_123',
        ]);

        $checkout = app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Team, BillingPeriod::Monthly, seats: 3);

        $this->assertSame([['priceId' => 'pri_team_monthly_123', 'quantity' => 3]], $checkout->options()['items']);

        $order = $user->orders()->sole();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(OrderPlan::Team, $order->plan);
        $this->assertSame(3, $order->seats);
        $this->assertSame('40.50', $order->amount);
    }

    public function test_team_checkout_page_passes_seats_through()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 108.00,
            'paddle_price_id' => 'pri_team_yearly_123',
        ]);

        $this->actingAs($user)
            ->get('/checkout/team?period=yearly&seats=5')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/show')
                ->where('plan', 'team')
                ->where('selectedPeriod', 'yearly')
                ->where('seats', 5)
                ->where('maxSeats', 100)
                ->where('checkout.items.0.priceId', 'pri_team_yearly_123')
                ->where('checkout.items.0.quantity', 5)
            );

        $order = $user->orders()->sole();

        $this->assertSame(5, $order->seats);
        $this->assertSame('540.00', $order->amount);

        // Re-checkout with a different seat count reuses the Pending order
        // and re-prices it — no duplicate orders pile up.
        $this->actingAs($user)->get('/checkout/team?period=yearly&seats=2');

        $order = $user->orders()->sole();

        $this->assertSame(2, $order->seats);
        $this->assertSame('216.00', $order->amount);
    }

    public function test_team_checkout_defaults_to_one_seat()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 13.50,
            'paddle_price_id' => 'pri_team_monthly_123',
        ]);

        $this->actingAs($user)->get('/checkout/team?period=monthly')->assertOk();

        $order = $user->orders()->sole();

        $this->assertSame(1, $order->seats);
        $this->assertSame('13.50', $order->amount);
    }

    public function test_solo_plans_ignore_the_seats_query()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 15.00,
            'paddle_price_id' => 'pri_pro_monthly_123',
        ]);

        $this->actingAs($user)
            ->get('/checkout/pro?period=monthly&seats=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seats', 1)
                ->where('checkout.items.0.quantity', 1)
            );

        $order = $user->orders()->sole();

        $this->assertNull($order->seats);
        $this->assertSame('15.00', $order->amount);
    }

    public function test_invalid_seat_counts_404()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/checkout/team?seats=0')->assertNotFound();
        $this->actingAs($user)->get('/checkout/team?seats=-3')->assertNotFound();
        $this->actingAs($user)->get('/checkout/team?seats=abc')->assertNotFound();
        $this->actingAs($user)->get('/checkout/team?seats=101')->assertNotFound();
    }

    public function test_domestic_team_checkout_multiplies_the_cny_seat_price()
    {
        $user = User::factory()->create();

        PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Team,
            'period' => BillingPeriod::Yearly,
            'amount' => 778.00,
        ]);

        $this->actingAs($user)
            ->withSession([RegionDetector::SESSION_KEY => RegionDetector::CNY])
            ->get('/checkout/team?period=yearly&seats=4')
            ->assertRedirect();

        $order = $user->orders()->sole();

        $this->assertSame(PlanProvider::Domestic, $order->provider);
        $this->assertSame(4, $order->seats);
        $this->assertSame('3112.00', $order->amount);
        $this->assertSame('CNY', $order->currency);
    }

    /**
     * Fakes the Paddle customer endpoints Cashier calls while syncing the
     * billable user (same fake as PaddleCheckoutTest).
     */
    private function paddleFake(): callable
    {
        return function (Request $request) {
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
        };
    }
}
