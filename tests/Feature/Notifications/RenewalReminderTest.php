<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DomesticPaymentConfirmedNotification;
use App\Notifications\RefundProcessedNotification;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\WelcomeToProNotification;
use App\Services\Sequences\RenewalReminderSequence;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * B5 — domestic renewal reminders (SPEC §16.2): a domestic, non-lifetime,
 * Active subscription anchors a five-touch schedule on its ends_at (T-7 /
 * T-3 / T-1 before, expired+1 / +7 after — one-time payment per period, no
 * auto-deduct, SPEC §7.5). zh templates ship with domestic payments
 * (§16.3); the domestic payment-confirmed mail (§16.1) replaces the
 * Paddle-oriented welcome mail for domestic activations.
 */
class RenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_schedule_matrix()
    {
        Notification::fake();

        $expiresAt = now()->addDays(30);

        // Registered well outside the B1 drip window; B3's day-3/7 anchors
        // land behind the first reminder run as well.
        $user = User::factory()->create(['created_at' => now()->subDays(90)]);
        $order = $this->domesticSubscription($user, $expiresAt);

        // A Paddle subscription expiring the same day never enters the
        // domestic-only schedule.
        $paddleUser = User::factory()->create(['created_at' => now()->subDays(90)]);
        Order::factory()->create([
            'user_id' => $paddleUser->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => now()->subMonth(),
            'ends_at' => $expiresAt->copy(),
        ]);

        // Nothing due while expiry is beyond the T-7 horizon.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertNotSentTo($user, RenewalReminderNotification::class);

        $runs = [
            7 => 't-minus-7',
            3 => 't-minus-3',
            1 => 't-minus-1',
            -1 => 'expired-plus-1',
            -7 => 'expired-plus-7',
        ];

        foreach ($runs as $daysBeforeExpiry => $step) {
            $this->travelTo($expiresAt->copy()->subDays($daysBeforeExpiry));
            $this->artisan('mail:run-sequences')->assertSuccessful();

            Notification::assertSentTo(
                $user,
                RenewalReminderNotification::class,
                fn (RenewalReminderNotification $notification): bool => $notification->step === $step
                    && $notification->order->is($order),
            );

            // A non-offset day inside the window sends nothing extra.
            if ($daysBeforeExpiry === 7) {
                $this->travelTo($expiresAt->copy()->subDays(5));
                $this->artisan('mail:run-sequences')->assertSuccessful();
                Notification::assertSentTimes(RenewalReminderNotification::class, 1);
            }
        }

        Notification::assertSentTimes(RenewalReminderNotification::class, 5);
        Notification::assertNotSentTo($paddleUser, RenewalReminderNotification::class);

        // One send per step per period — progress rows are expiry-stamped.
        $this->assertSame(5, $user->sequenceSends()->where('sequence', RenewalReminderSequence::KEY)->count());

        // Idempotent: a same-day re-run sends nothing more.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertSentTimes(RenewalReminderNotification::class, 5);
    }

    public function test_no_reminders_for_lifetime_orders()
    {
        Notification::fake();

        // Domestic lifetime: a natural one-time QR purchase, never renewed.
        $user = User::factory()->create(['created_at' => now()->subDays(90)]);
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => DomesticChannel::Alipay,
            'amount' => 1288.00,
            'currency' => 'CNY',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Sweep the whole reminder window; a lifetime order has no ends_at
        // and must never anchor the schedule.
        $base = now()->addDays(30);

        foreach ([7, 3, 1, -1, -7] as $daysBeforeExpiry) {
            $this->travelTo($base->copy()->subDays($daysBeforeExpiry));
            $this->artisan('mail:run-sequences')->assertSuccessful();
        }

        Notification::assertNotSentTo($user, RenewalReminderNotification::class);
        $this->assertSame(0, $user->sequenceSends()->where('sequence', RenewalReminderSequence::KEY)->count());
    }

    public function test_zh_templates_render()
    {
        $user = User::factory()->create(['name' => '小明']);
        $order = $this->domesticSubscription($user, now()->addDays(7));

        App::setLocale('zh');

        $expectedSubjects = [
            't-minus-7' => '您的 FrontendParts 订阅将于 7 天后到期',
            't-minus-3' => '您的 FrontendParts 订阅将于 3 天后到期',
            't-minus-1' => '您的 FrontendParts 订阅将于明天到期',
            'expired-plus-1' => '您的 FrontendParts 订阅已到期',
            'expired-plus-7' => '最后提醒：请续费您的 FrontendParts 订阅',
        ];

        foreach ($expectedSubjects as $step => $subject) {
            $notification = new RenewalReminderNotification($step, $order);

            // The pinned locale carries the zh render through the queue.
            $this->assertSame('zh', $notification->locale);

            $mail = $notification->toMail($user);

            $this->assertSame($subject, $mail->subject, "Step {$step} subject is not the zh template.");

            $html = (string) $mail->render();

            $this->assertStringContainsString('您好，小明', $html);
            $this->assertStringContainsString('立即续费', $html);
            $this->assertStringNotContainsString('renew now', strtolower($html));
        }

        // Pre-expiry vs expired anchoring lines.
        $beforeHtml = (string) (new RenewalReminderNotification('t-minus-7', $order))->toMail($user)->render();
        $this->assertStringContainsString('将于 '.$order->ends_at->toDateString().' 到期', $beforeHtml);

        $afterHtml = (string) (new RenewalReminderNotification('expired-plus-1', $order))->toMail($user)->render();
        $this->assertStringContainsString('已于 '.$order->ends_at->toDateString().' 到期', $afterHtml);

        // Payment-confirmed + access-unlocked mail (SPEC §16.1).
        $confirmed = new DomesticPaymentConfirmedNotification($order);

        $this->assertSame('zh', $confirmed->locale);

        $confirmedMail = $confirmed->toMail($user);

        $this->assertSame('支付成功——您的 FrontendParts 访问权限已开通', $confirmedMail->subject);

        $confirmedHtml = (string) $confirmedMail->render();

        $this->assertStringContainsString('支付宝', $confirmedHtml);
        $this->assertStringContainsString('¥68.00', $confirmedHtml);
        $this->assertStringContainsString('解锁', $confirmedHtml);

        // Domestic refund mail follows the same zh convention (§16.3).
        $refund = new RefundProcessedNotification($order);

        $this->assertSame('zh', $refund->locale);
        $this->assertSame('您的退款已处理', $refund->toMail($user)->subject);

        // English source strings remain the fallback for other locales.
        App::setLocale('en');

        $enMail = (new RenewalReminderNotification('t-minus-7', $order))->toMail($user);

        $this->assertSame('Your FrontendParts subscription expires in 7 days', $enMail->subject);
    }

    public function test_payment_confirmed_email_queued()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'user_id' => User::factory(),
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Yearly,
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => DomesticChannel::Alipay,
            'amount' => 518.00,
            'currency' => 'CNY',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        // The domestic notify activation path flips the order Active;
        // OrderObserver queues the zh payment-confirmed mail from that one
        // send point.
        $order->update([
            'status' => OrderStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        Notification::assertSentTo(
            $order->user,
            DomesticPaymentConfirmedNotification::class,
            fn (DomesticPaymentConfirmedNotification $notification): bool => $notification->order->is($order)
                && $notification->locale === 'zh'
                && in_array(ShouldQueue::class, class_implements($notification), true),
        );

        $this->assertSame(
            1,
            Notification::sent($order->user, DomesticPaymentConfirmedNotification::class)->count(),
        );

        // No double-send: the Paddle-oriented welcome mail is not sent for
        // domestic activations (SPEC §16.1 keeps the two rows distinct).
        Notification::assertNotSentTo($order->user, WelcomeToProNotification::class);
    }

    public function test_stops_once_renewed()
    {
        Notification::fake();

        $expiresAt = now()->addDays(30);
        $user = User::factory()->create(['created_at' => now()->subDays(90)]);
        $this->domesticSubscription($user, $expiresAt);

        // The buyer renewed early: a newer Active paid order outlives the
        // expiring one, so neither the pre-expiry nor the expired nudges
        // should go out.
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => DomesticChannel::Wechat,
            'amount' => 68.00,
            'currency' => 'CNY',
            'starts_at' => $expiresAt->copy()->subDays(10),
            'ends_at' => $expiresAt->copy()->addMonth(),
        ]);

        foreach ([7, 3, 1, -1, -7] as $daysBeforeExpiry) {
            $this->travelTo($expiresAt->copy()->subDays($daysBeforeExpiry));
            $this->artisan('mail:run-sequences')->assertSuccessful();
        }

        Notification::assertNotSentTo($user, RenewalReminderNotification::class);
        $this->assertSame(0, $user->sequenceSends()->where('sequence', RenewalReminderSequence::KEY)->count());
    }

    /**
     * A domestic (CNY, one-time per period) Active subscription expiring at
     * the given moment.
     */
    private function domesticSubscription(User $user, Carbon $expiresAt): Order
    {
        return Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Domestic,
            'domestic_channel' => DomesticChannel::Alipay,
            'amount' => 68.00,
            'currency' => 'CNY',
            'starts_at' => $expiresAt->copy()->subMonth(),
            'ends_at' => $expiresAt,
            'created_at' => $expiresAt->copy()->subMonth(),
        ]);
    }
}
