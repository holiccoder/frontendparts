<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\DomesticPreOrder;
use App\Services\Billing\RegionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Domestic checkout routing (SPEC §7.5): the buyer's region — geo-detect
 * heuristic plus the manual currency switch persisted in the session —
 * selects the backend. CN region gets the CNY domestic QR checkout priced
 * from the `plan_prices` domestic rows (never hardcoded); international
 * buyers get the untouched Paddle overlay flow.
 */
class DomesticCheckoutTest extends TestCase
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

    public function test_cn_region_gets_cny_qr_checkout()
    {
        $user = User::factory()->create();

        PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Yearly,
            'amount' => 778.00,
        ]);

        // Geo-detect: a Chinese top Accept-Language locale routes to CNY.
        $response = $this->actingAs($user)
            ->withHeader('Accept-Language', 'zh-CN,zh;q=0.9,en;q=0.8')
            ->get('/checkout/pro?period=yearly');

        $order = $user->orders()->sole();

        $response->assertRedirect(route('pay.domestic', $order));

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PlanProvider::Domestic, $order->provider);
        $this->assertSame('CNY', $order->currency);
        $this->assertSame('778.00', $order->amount);
        $this->assertSame(BillingPeriod::Yearly, $order->billing_period);

        // The QR page creates the pre-order at the gateway seam and renders.
        $this->mock(DomesticGateway::class, fn ($mock) => $mock
            ->shouldReceive('scanPreOrder')
            ->once()
            ->withArgs(fn (DomesticChannel $channel, $scannedOrder): bool => $channel === DomesticChannel::Alipay
                && $scannedOrder->is($order))
            ->andReturn(new DomesticPreOrder(DomesticChannel::Alipay, 'fp1abc', 'https://qr.alipay.com/bax01234', 'https://qr.alipay.com/bax01234')));

        $this->actingAs($user)
            ->get(route('pay.domestic', $order))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('pay/domestic')
                ->where('order.amount', '778.00')
                ->where('order.currency', 'CNY')
                ->where('channel', 'alipay')
                ->where('qrContent', 'https://qr.alipay.com/bax01234')
                ->where('wakeUpUrl', 'https://qr.alipay.com/bax01234')
                ->has('statusUrl')
                ->has('successUrl'));

        // The pre-order stamped the trade reference + rail for polling.
        $this->assertNotNull($order->refresh()->out_trade_no);
        $this->assertSame(DomesticChannel::Alipay, $order->domestic_channel);
    }

    public function test_international_gets_paddle()
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

        // No switch, English locale → the Paddle overlay host page.
        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/checkout/pro?period=yearly')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/show')
                ->where('plan', 'pro')
                ->where('selectedPeriod', 'yearly')
                ->where('checkout.items.0.priceId', 'pri_pro_yearly_123'));

        $order = $user->orders()->sole();

        $this->assertSame(PlanProvider::Paddle, $order->provider);
        $this->assertSame('USD', $order->currency);

        // An explicit USD switch keeps a zh-locale buyer on Paddle too.
        Http::fake($this->paddleFake());

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 9.00,
            'paddle_price_id' => 'pri_starter_monthly_123',
        ]);

        $this->actingAs($user)
            ->withSession([RegionDetector::SESSION_KEY => RegionDetector::USD])
            ->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')
            ->get('/checkout/starter?period=monthly')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('checkout/show'));
    }

    public function test_manual_currency_switch()
    {
        PlanPrice::factory()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 72.00,
            'currency' => 'USD',
        ]);

        PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Yearly,
            'amount' => 518.00,
        ]);

        // Switch to CNY — persists in the session and re-prices the page.
        $this->post(route('billing.currency.switch'), ['currency' => 'CNY'])
            ->assertRedirect()
            ->assertSessionHas(RegionDetector::SESSION_KEY, 'CNY');

        $this->withSession([RegionDetector::SESSION_KEY => 'CNY'])
            ->get('/pricing')
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency', 'CNY')
                ->where('plans.starter.prices.yearly.amount', '518.00')
                ->where('plans.starter.prices.yearly.currency', 'CNY')
                ->where('plans.starter.prices.yearly.per_month', '43.17'));

        // Switch back to USD — the Paddle rows return.
        $this->post(route('billing.currency.switch'), ['currency' => 'USD'])
            ->assertSessionHas(RegionDetector::SESSION_KEY, 'USD');

        $this->withSession([RegionDetector::SESSION_KEY => 'USD'])
            ->get('/pricing')
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency', 'USD')
                ->where('plans.starter.prices.yearly.amount', '72.00')
                ->where('plans.starter.prices.yearly.currency', 'USD'));

        // Anything else is rejected.
        $this->post(route('billing.currency.switch'), ['currency' => 'EUR'])
            ->assertSessionHasErrors('currency');
    }

    public function test_cny_prices_from_plan_prices()
    {
        $user = User::factory()->create();

        // No domestic row at all → the plan is not purchasable in CNY.
        $this->actingAs($user)
            ->withSession([RegionDetector::SESSION_KEY => 'CNY'])
            ->get('/checkout/pro?period=yearly')
            ->assertNotFound();

        $price = PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Yearly,
            'amount' => 778.00,
        ]);

        $this->actingAs($user)
            ->withSession([RegionDetector::SESSION_KEY => 'CNY'])
            ->get('/checkout/pro?period=yearly')
            ->assertRedirect();

        $this->assertSame('778.00', $user->orders()->sole()->amount);

        // Repricing the domestic row is picked up without a deploy — the
        // reused Pending order is re-priced from the table.
        $price->update(['amount' => 818.00]);

        $this->actingAs($user)
            ->withSession([RegionDetector::SESSION_KEY => 'CNY'])
            ->get('/checkout/pro?period=yearly')
            ->assertRedirect();

        $orders = $user->orders()->get();

        $this->assertCount(1, $orders);
        $this->assertSame('818.00', $orders->sole()->amount);
    }

    /**
     * Fakes the Paddle customer endpoints Cashier calls while syncing the
     * billable user (same fake as PaddleCheckoutTest).
     */
    private function paddleFake(): callable
    {
        return function (HttpRequest $request) {
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
