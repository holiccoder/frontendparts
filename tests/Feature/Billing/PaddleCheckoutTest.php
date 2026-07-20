<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Billing\PaddleCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Cashier Paddle checkout setup (SPEC §7.3): checkout sessions are built for
 * a plan × period with the price id resolved from `plan_prices`, the Paddle
 * customer is created/synced via Cashier and linked to the user, and every
 * outbound call is faked — no network, no real Paddle account.
 */
class PaddleCheckoutTest extends TestCase
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

    public function test_checkout_session_created_for_plan_and_period()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 108.00,
            'paddle_price_id' => 'pri_pro_yearly_123',
        ]);

        $checkout = app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Pro, BillingPeriod::Yearly);

        $options = $checkout->options();

        $this->assertSame([['priceId' => 'pri_pro_yearly_123', 'quantity' => 1]], $options['items']);
        $this->assertSame('ctm_test_123', $options['customer']['id']);
        $this->assertSame(route('checkout.success'), $options['settings']['successUrl']);

        // A Pending local order backs the attempt and is referenced from the
        // session so the webhook can activate exactly this order.
        $order = $user->orders()->sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(OrderPlan::Pro, $order->plan);
        $this->assertSame(BillingPeriod::Yearly, $order->billing_period);
        $this->assertSame('108.00', $order->amount);
        $this->assertSame('ctm_test_123', $order->paddle_customer_id);
        $this->assertSame((string) $order->id, $options['customData']['order_id']);

        // Re-checkout for the same plan/period reuses the Pending order
        // instead of piling up duplicates.
        app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Pro, BillingPeriod::Yearly);

        $this->assertSame(1, $user->orders()->count());
    }

    public function test_customer_record_linked_to_user()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create(['email' => 'buyer@example.com']);

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'paddle_price_id' => 'pri_starter_monthly_123',
        ]);

        app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Starter, BillingPeriod::Monthly);

        $customer = $user->customer()->sole();

        $this->assertSame($user->id, $customer->billable_id);
        $this->assertSame(User::class, $customer->billable_type);
        $this->assertSame('ctm_test_123', $customer->paddle_id);
        $this->assertSame('buyer@example.com', $customer->email);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/customers')
            && $request['email'] === 'buyer@example.com');
    }

    public function test_price_ids_come_from_plan_prices_table()
    {
        Http::fake($this->paddleFake());

        $user = User::factory()->create();

        // Without a plan_prices row the plan is not purchasable at all.
        try {
            app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Pro, BillingPeriod::Monthly);
            $this->fail('Expected NotFoundHttpException for a missing plan price.');
        } catch (NotFoundHttpException) {
            // expected
        }

        $price = PlanPrice::factory()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'paddle_price_id' => 'pri_original',
        ]);

        $checkout = app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Pro, BillingPeriod::Monthly);

        $this->assertSame('pri_original', $checkout->options()['items'][0]['priceId']);

        // Repricing / re-pointing the price id in the table is picked up
        // without a deploy — nothing is hardcoded.
        $price->update(['paddle_price_id' => 'pri_repriced']);

        $checkout = app(PaddleCheckoutService::class)->checkout($user, OrderPlan::Pro, BillingPeriod::Monthly);

        $this->assertSame('pri_repriced', $checkout->options()['items'][0]['priceId']);
    }

    /**
     * Fakes the Paddle customer endpoints Cashier calls while syncing the
     * billable user: lookup-by-email misses, then the create call succeeds.
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
