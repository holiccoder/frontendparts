<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\ComponentEventType;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Blog;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use App\Notifications\FreeOnboardingNotification;
use App\Notifications\NewDropsDigestNotification;
use App\Notifications\PaidOnboardingNotification;
use App\Notifications\UpgradeTriggerNotification;
use App\Services\Notifications\NotificationPreferences;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SequencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_b1_full_drip_schedule()
    {
        Notification::fake();

        $registeredAt = now();
        $user = User::factory()->create(['created_at' => $registeredAt]);

        // Nothing on Day 0 — the Day-0 welcome is the transactional
        // WelcomeNotification sent by the Registered listener, not the engine.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        Notification::assertNotSentTo($user, FreeOnboardingNotification::class);

        $offsets = [2 => 'day-2', 4 => 'day-4', 7 => 'day-7', 12 => 'day-12'];

        foreach ($offsets as $offset => $step) {
            $this->travelTo($registeredAt->copy()->addDays($offset));
            $this->artisan('mail:run-sequences')->assertSuccessful();

            Notification::assertSentTo(
                $user,
                FreeOnboardingNotification::class,
                fn (FreeOnboardingNotification $notification): bool => $notification->step === $step,
            );
        }

        Notification::assertSentTimes(FreeOnboardingNotification::class, 4);
    }

    public function test_b2_triggered_at_3_gate_events_within_week()
    {
        Notification::fake();

        $user = User::factory()->create();
        $component = Component::factory()->published()->create();

        // Two blur-gate hits within the rolling week — below threshold.
        $component->recordEvent(ComponentEventType::GateHit, $user);
        $component->recordEvent(ComponentEventType::GateHit, $user);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($user, UpgradeTriggerNotification::class);

        // The third hit crosses the threshold (SPEC §16.2).
        $component->recordEvent(ComponentEventType::GateHit, $user);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo($user, UpgradeTriggerNotification::class);

        // Throttled: re-runs within the same 7 days never resend.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(UpgradeTriggerNotification::class, 1);

        // Paid users never get the upgrade trigger.
        $paidUser = User::factory()->create();
        Order::factory()->create([
            'user_id' => $paidUser->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'starts_at' => now()->subDays(30),
            'ends_at' => null,
        ]);

        foreach (range(1, 3) as $i) {
            $component->recordEvent(ComponentEventType::GateHit, $paidUser);
        }

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($paidUser, UpgradeTriggerNotification::class);
    }

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

    public function test_b4_digest_contains_new_components_and_blog_posts()
    {
        Notification::fake();

        $monday = now()->next(CarbonInterface::MONDAY)->setTime(9, 0);
        $this->travelTo($monday);

        $user = User::factory()->create();

        $newComponent = Component::factory()->published()->create(['created_at' => $monday->copy()->subDays(3)]);
        $oldComponent = Component::factory()->published()->create(['created_at' => $monday->copy()->subMonth()]);
        $post = Blog::factory()->create([
            'status' => 'published',
            'published_at' => $monday->copy()->subDays(2),
        ]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            NewDropsDigestNotification::class,
            function (NewDropsDigestNotification $notification) use ($user, $newComponent, $oldComponent, $post): bool {
                $html = (string) $notification->toMail($user)->render();

                return str_contains($html, $newComponent->name)
                    && str_contains($html, $post->title)
                    && ! str_contains($html, $oldComponent->name);
            },
        );
    }

    public function test_b4_respects_weekly_vs_monthly_choice()
    {
        Notification::fake();

        // A Monday that is NOT the 1st of the month isolates the weekly send.
        $monday = now()->next(CarbonInterface::MONDAY);

        if ($monday->day === 1) {
            $monday = $monday->addWeek();
        }

        $this->travelTo($monday->copy()->setTime(9, 0));

        $preferences = app(NotificationPreferences::class);

        $weeklyUser = User::factory()->create();
        $monthlyUser = User::factory()->create();
        $preferences->update($monthlyUser, ['digest_frequency' => 'monthly']);
        $offUser = User::factory()->create();
        $preferences->update($offUser, ['digest_frequency' => 'off']);

        Component::factory()->published()->create();

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo($weeklyUser, NewDropsDigestNotification::class);
        Notification::assertNotSentTo($monthlyUser, NewDropsDigestNotification::class);
        Notification::assertNotSentTo($offUser, NewDropsDigestNotification::class);

        // The 1st of a month: monthly users get their digest, 'off' never does.
        $first = $monday->copy()->addMonth()->startOfMonth();
        $this->travelTo($first->copy()->setTime(9, 0));

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo($monthlyUser, NewDropsDigestNotification::class);
        Notification::assertNotSentTo($offUser, NewDropsDigestNotification::class);
    }
}
