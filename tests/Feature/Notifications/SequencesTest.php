<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaidOnboardingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SequencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_b3_paid_onboarding_schedule()
    {
        Notification::fake();

        $activatedAt = now();
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'starts_at' => $activatedAt,
            'ends_at' => $activatedAt->copy()->addYear(),
        ]);

        // A pending (never-activated) order does not anchor the sequence.
        $pendingUser = User::factory()->create();
        Order::factory()->create([
            'user_id' => $pendingUser->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Pending,
            'billing_period' => BillingPeriod::Monthly,
            'starts_at' => null,
        ]);

        // Day 0 is the transactional WelcomeToProNotification from the order
        // observer — the engine sends nothing on the activation day itself.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertNotSentTo($user, PaidOnboardingNotification::class);

        $this->travelTo($activatedAt->copy()->addDays(3));
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            PaidOnboardingNotification::class,
            fn (PaidOnboardingNotification $notification): bool => $notification->step === 'day-3',
        );
        Notification::assertNotSentTo(
            $user,
            PaidOnboardingNotification::class,
            fn (PaidOnboardingNotification $notification): bool => $notification->step === 'day-7',
        );
        Notification::assertNotSentTo($pendingUser, PaidOnboardingNotification::class);

        $this->travelTo($activatedAt->copy()->addDays(7));
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            PaidOnboardingNotification::class,
            fn (PaidOnboardingNotification $notification): bool => $notification->step === 'day-7',
        );

        Notification::assertSentTimes(PaidOnboardingNotification::class, 2);
    }
}
