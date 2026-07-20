<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CancelFollowupNotification;
use App\Notifications\CancellationConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * B7 — cancel flow (SPEC §16.2): required 1-question exit survey →
 * reason-mapped save offer → confirmation with access-until date +
 * reactivation link → Day 7 reactivation → Day 30 win-back.
 * All Paddle calls are faked — no network.
 */
class CancelFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.api_key' => 'pdl_test_api_key',
            'cashier.sandbox' => true,
        ]);
    }

    public function test_billing_page_shows_cancellable_subscription()
    {
        $user = User::factory()->create();
        $this->subscriptionOrder($user);

        $this->actingAs($user)
            ->get('/settings/billing')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/billing')
                ->where('order.plan', 'pro')
                ->where('order.status', 'active')
                ->where('cancellable', true)
                ->has('cancellationReasons', 5)
            );
    }

    public function test_survey_required_before_cancel()
    {
        Http::fake($this->cancelFake());

        $user = User::factory()->create();
        $order = $this->subscriptionOrder($user);

        // No reason at all.
        $this->actingAs($user)
            ->from('/settings/billing')
            ->post('/settings/billing/cancel', ['confirmed' => true])
            ->assertSessionHasErrors('reason');

        // An answer outside the survey options is rejected too.
        $this->actingAs($user)
            ->from('/settings/billing')
            ->post('/settings/billing/cancel', ['reason' => 'because', 'confirmed' => true])
            ->assertSessionHasErrors('reason');

        // The order is untouched and Paddle was never called.
        $this->assertSame(OrderStatus::Active, $order->fresh()->status);
        $this->assertNull($order->fresh()->cancellation_reason);
        Http::assertNothingSent();
    }

    public function test_save_offer_mapped_to_each_reason()
    {
        Http::fake($this->cancelFake());

        $user = User::factory()->create();
        $order = $this->subscriptionOrder($user);

        // SPEC §16.2 mapping: price→discount/downgrade · not using→pause ·
        // missing feature→roadmap · project ended→pause · just testing→let go.
        $expected = [
            'price' => 'discount',
            'not_using' => 'pause',
            'missing_feature' => 'roadmap',
            'project_ended' => 'pause',
            'just_testing' => 'none',
        ];

        foreach ($expected as $reason => $offerType) {
            $this->actingAs($user)
                ->from('/settings/billing')
                ->post('/settings/billing/cancel', ['reason' => $reason])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('save_offer', fn (array $offer): bool => $offer['reason'] === $reason
                    && $offer['type'] === $offerType
                    && $offer['title'] !== ''
                    && $offer['body'] !== '');
        }

        // Presenting offers never finalizes anything.
        $this->assertSame(OrderStatus::Active, $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_confirmation_contains_access_until_and_reactivation_link()
    {
        Http::fake($this->cancelFake());
        Notification::fake();

        $user = User::factory()->create();
        $order = $this->subscriptionOrder($user);
        $endsAt = $order->ends_at->copy();

        $this->actingAs($user)
            ->from('/settings/billing')
            ->post('/settings/billing/cancel', ['reason' => 'price', 'confirmed' => true])
            ->assertSessionHasNoErrors();

        $order->refresh();

        // Cancelled with access kept until the current period end (SPEC §7.3).
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertTrue($order->ends_at->equalTo($endsAt));
        $this->assertSame('price', $order->cancellation_reason);

        // Paddle was told to cancel at the period boundary.
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/subscriptions/sub_123/cancel')
            && $request['effective_from'] === 'next_billing_period');

        // The confirmation mail carries the access-until date and the signed
        // reactivation link (SPEC §16.2).
        Notification::assertSentTo(
            $user,
            CancellationConfirmedNotification::class,
            function (CancellationConfirmedNotification $notification) use ($order, $endsAt): bool {
                $mail = $notification->toMail($order->user);

                $mentionsAccessUntil = collect($mail->introLines)
                    ->contains(fn (string $line): bool => str_contains($line, $endsAt->toFormattedDateString()));

                return $mentionsAccessUntil
                    && $mail->actionUrl === URL::signedRoute('billing.reactivate', ['order' => $order->id]);
            },
        );

        // The signed reactivation link forwards to checkout for the same plan.
        $this->get(URL::signedRoute('billing.reactivate', ['order' => $order->id]))
            ->assertRedirect(route('checkout.show', ['plan' => $order->plan->value]));
    }

    public function test_day7_and_day30_followups_scheduled()
    {
        Notification::fake();

        $cancelledAt = now();

        $user = User::factory()->create(['created_at' => $cancelledAt->copy()->subDays(60)]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Cancelled,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => $cancelledAt->copy()->subMonth(),
            'ends_at' => $cancelledAt->copy()->addDays(5),
            'cancelled_at' => $cancelledAt,
            'cancellation_reason' => 'price',
            'created_at' => $cancelledAt->copy()->subMonth(),
        ]);

        // A reactivated user (a newer Active order exists) gets no followups.
        $returnedUser = User::factory()->create(['created_at' => $cancelledAt->copy()->subDays(60)]);
        Order::factory()->create([
            'user_id' => $returnedUser->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Cancelled,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => $cancelledAt->copy()->subMonth(),
            'ends_at' => $cancelledAt->copy()->addDays(5),
            'cancelled_at' => $cancelledAt,
            'created_at' => $cancelledAt->copy()->subMonth(),
        ]);
        Order::factory()->create([
            'user_id' => $returnedUser->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'starts_at' => $cancelledAt->copy()->addDays(2),
            'ends_at' => $cancelledAt->copy()->addYear(),
            'created_at' => $cancelledAt->copy()->addDays(2),
        ]);

        // Day 7 — reactivation.
        $this->travelTo($cancelledAt->copy()->addDays(7));
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            CancelFollowupNotification::class,
            fn (CancelFollowupNotification $notification): bool => $notification->step === 'day-7',
        );
        Notification::assertNotSentTo($returnedUser, CancelFollowupNotification::class);

        // Day 30 — win-back.
        $this->travelTo($cancelledAt->copy()->addDays(30));
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            CancelFollowupNotification::class,
            fn (CancelFollowupNotification $notification): bool => $notification->step === 'day-30',
        );

        Notification::assertSentTimes(CancelFollowupNotification::class, 2);
    }

    /**
     * An Active monthly Paddle subscription mid-period.
     */
    private function subscriptionOrder(User $user): Order
    {
        return Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'paddle_subscription_id' => 'sub_123',
        ]);
    }

    /**
     * Fakes Paddle's subscription-cancel endpoint.
     */
    private function cancelFake(): callable
    {
        return function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/subscriptions/sub_123/cancel')) {
                return Http::response(['data' => [
                    'id' => 'sub_123',
                    'status' => 'active',
                    'scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addDays(20)->toIso8601String()],
                ]]);
            }

            return Http::response(['data' => []]);
        };
    }
}
