<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DunningNotification;
use App\Services\Sequences\DunningSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * B6 — dunning (SPEC §16.2): an order entering PastDue anchors a 5-touch /
 * 15-day schedule through the lifecycle engine; every touch deep-links the
 * update-payment page; recovery stops the remaining touches.
 */
class DunningTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_touch_schedule_on_past_due()
    {
        Notification::fake();

        $failedAt = now();

        // Registered well outside the B1 drip window, so only dunning sends.
        $user = User::factory()->create(['created_at' => $failedAt->copy()->subDays(60)]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => $failedAt->copy()->subMonth(),
            'ends_at' => $failedAt->copy()->addMonth(),
            'created_at' => $failedAt->copy()->subMonth(),
        ]);

        // The payment failure flips the order to PastDue; OrderObserver
        // stamps past_due_at, which anchors the schedule.
        $order->update(['status' => OrderStatus::PastDue]);

        $this->assertNotNull($order->fresh()->past_due_at);

        // Nothing on the failure day itself — Paddle's own retry covers it.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertNotSentTo($user, DunningNotification::class);

        $touches = [1 => 'touch-1', 4 => 'touch-2', 8 => 'touch-3', 11 => 'touch-4', 15 => 'touch-5'];

        foreach ($touches as $offset => $step) {
            $this->travelTo($failedAt->copy()->addDays($offset));
            $this->artisan('mail:run-sequences')->assertSuccessful();

            Notification::assertSentTo(
                $user,
                DunningNotification::class,
                fn (DunningNotification $notification): bool => $notification->step === $step
                    && $notification->order->is($order),
            );
        }

        Notification::assertSentTimes(DunningNotification::class, 5);

        $this->assertSame(5, $user->sequenceSends()->where('sequence', DunningSequence::KEY)->count());

        // Idempotent: a same-day re-run sends nothing more.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertSentTimes(DunningNotification::class, 5);
    }

    public function test_every_mail_links_update_payment_page()
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::PastDue,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(10),
            'past_due_at' => now()->subDay(),
        ]);

        $sequence = new DunningSequence;

        foreach ($sequence->steps() as $step) {
            $notification = $sequence->notification($step, $user);

            $this->assertNotNull($notification, "No notification built for {$step->key}.");

            $mail = $notification->toMail($user);

            // The one canonical update-payment page, linked by every touch.
            $this->assertSame(route('settings.billing'), $mail->actionUrl, "Touch {$step->key} does not deep-link the update-payment page.");
        }
    }

    public function test_stops_on_recovery()
    {
        Notification::fake();

        $failedAt = now();
        $user = User::factory()->create(['created_at' => $failedAt->copy()->subDays(60)]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => $failedAt->copy()->subMonth(),
            'ends_at' => $failedAt->copy()->addMonth(),
            'created_at' => $failedAt->copy()->subMonth(),
        ]);

        $order->update(['status' => OrderStatus::PastDue]);

        // Touch 1 goes out.
        $this->travelTo($failedAt->copy()->addDay());
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo($user, DunningNotification::class);

        // Recovery: the retried payment succeeds, the order is Active again.
        $order->update(['status' => OrderStatus::Active]);

        foreach ([4, 8, 11, 15] as $offset) {
            $this->travelTo($failedAt->copy()->addDays($offset));
            $this->artisan('mail:run-sequences')->assertSuccessful();
        }

        // No further touches after recovery — and no progress recorded for
        // them either, so a re-failure later starts from the new anchor.
        Notification::assertSentTimes(DunningNotification::class, 1);

        $this->assertSame(1, $user->sequenceSends()->where('sequence', DunningSequence::KEY)->count());
    }
}
