<?php

namespace Tests\Feature\Affiliate;

use App\Enums\BillingPeriod;
use App\Enums\CommissionStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Affiliates\ReferralService;
use App\Services\Billing\RegionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Checkout attribution (SPEC §17.1 step 4): the referral code rides the
 * checkout — Paddle session custom_data / domestic order meta — so the paid
 * webhook attributes the order to the affiliate even after the referral
 * cookie is gone. An order without a code stays unattributed.
 */
class CheckoutAttributionTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'pdl_test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.api_key' => 'pdl_test_api_key',
            'cashier.sandbox' => true,
            'cashier.webhook_secret' => $this->secret,
            'services.paddle.client_side_token' => 'pdl_test_client_side_token',
        ]);
    }

    public function test_checkout_session_carries_referral_code()
    {
        Http::fake($this->paddleFake());

        $affiliate = Affiliate::factory()->create();
        $user = User::factory()->create();

        PlanPrice::factory()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Paddle,
            'amount' => 108.00,
            'paddle_price_id' => 'pri_pro_yearly_123',
        ]);

        PlanPrice::factory()->domestic()->create([
            'plan' => OrderPlan::Pro,
            'period' => BillingPeriod::Yearly,
            'amount' => 778.00,
        ]);

        // A codeless checkout leaves the order unattributed (checked first —
        // withCookie below persists as a default cookie for later requests;
        // the domestic branch also needs no Paddle customer round-trip).
        $other = User::factory()->create();

        $this->actingAs($other)
            ->withSession([RegionDetector::SESSION_KEY => RegionDetector::CNY])
            ->get('/checkout/pro?period=yearly');

        $this->assertNull($other->orders()->sole()->referral_code);

        // Paddle: the code is stamped on the local order AND mirrored into
        // the session's custom data (the test client encrypts request
        // cookies transparently, like a real browser round-trip). The
        // explicit USD switch overrides the CNY session persisted above.
        $this->actingAs($user)
            ->withCookie(ReferralService::COOKIE, $affiliate->code)
            ->withSession([RegionDetector::SESSION_KEY => RegionDetector::USD])
            ->get('/checkout/pro?period=yearly')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/show')
                ->where('checkout.customData.affiliate_code', $affiliate->code));

        $order = $user->orders()->where('provider', PlanProvider::Paddle)->sole();

        $this->assertSame($affiliate->code, $order->referral_code);

        // Domestic: the code is stamped as the order meta (SPEC §17.1 step 4).
        $this->actingAs($user)
            ->withCookie(ReferralService::COOKIE, $affiliate->code)
            ->withSession([RegionDetector::SESSION_KEY => RegionDetector::CNY])
            ->get('/checkout/pro?period=yearly')
            ->assertRedirect();

        $domesticOrder = $user->orders()->where('provider', PlanProvider::Domestic)->sole();

        $this->assertSame($affiliate->code, $domesticOrder->referral_code);
    }

    public function test_webhook_attributes_order_to_affiliate()
    {
        Notification::fake();

        $affiliate = Affiliate::factory()->create();
        $buyer = User::factory()->create();

        // The buyer came through the link and signed up (referral linked).
        $referral = AffiliateReferral::factory()->converted($buyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        // (a) Order stamped at checkout; the webhook activates it and the
        //     commission engine attributes it.
        $stamped = Order::factory()->create([
            'user_id' => $buyer->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 108.00,
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'referral_code' => $affiliate->code,
        ]);

        $this->paddleWebhook([
            'event_id' => 'evt_aff_stamped_1',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_aff_1',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'custom_data' => ['order_id' => (string) $stamped->id],
                'billed_at' => now()->startOfSecond()->toIso8601String(),
            ],
        ])->assertOk();

        $commission = AffiliateCommission::query()->where('order_id', $stamped->id)->sole();

        $this->assertSame($affiliate->id, $commission->affiliate_id);
        $this->assertSame($referral->id, $commission->referral_id);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertSame('32.40', $commission->amount);
        $this->assertSame('USD', $commission->currency);
        $this->assertNull($commission->payable_at);

        // (b) Cookie lost AND the order never got stamped: the affiliate
        //     code mirrored in Paddle's custom data backfills the order, so
        //     attribution survives (SPEC §17.1 step 4).
        $unstamped = Order::factory()->create([
            'user_id' => $buyer->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Monthly,
            'amount' => 15.00,
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'referral_code' => null,
        ]);

        $this->paddleWebhook([
            'event_id' => 'evt_aff_backfill_1',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_aff_2',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'custom_data' => [
                    'order_id' => (string) $unstamped->id,
                    'affiliate_code' => $affiliate->code,
                ],
                'billed_at' => now()->startOfSecond()->toIso8601String(),
            ],
        ])->assertOk();

        $this->assertSame($affiliate->code, $unstamped->refresh()->referral_code);

        $backfilled = AffiliateCommission::query()->where('order_id', $unstamped->id)->sole();

        $this->assertSame($affiliate->id, $backfilled->affiliate_id);
        $this->assertSame('4.50', $backfilled->amount);
    }

    public function test_order_without_code_unattributed()
    {
        Notification::fake();

        // An organic buyer: no referral code, no signup link, no history.
        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 108.00,
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'referral_code' => null,
        ]);

        $this->paddleWebhook([
            'event_id' => 'evt_aff_none_1',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_aff_none_1',
                'status' => 'completed',
                'customer_id' => 'ctm_123',
                'custom_data' => ['order_id' => (string) $order->id],
                'billed_at' => now()->startOfSecond()->toIso8601String(),
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Active, $order->refresh()->status);
        $this->assertNull($order->referral_code);
        $this->assertSame(0, AffiliateCommission::count());
    }

    /**
     * POST a webhook payload with a properly computed Paddle-Signature
     * header (HMAC-SHA256 over `ts:body` with the webhook secret).
     *
     * @param  array<string, mixed>  $payload
     */
    private function paddleWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}:{$body}", $this->secret);

        return $this->call('POST', '/paddle/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$hash}",
        ], $body);
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
