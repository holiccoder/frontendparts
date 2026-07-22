<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaidOnboardingNotification;
use App\Notifications\PasswordChangedNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SequenceEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_b3_day3_sent_only_to_users_activated_3_days_ago()
    {
        Notification::fake();

        $dueUser = $this->activatedUser(now()->subDays(3));
        $tooEarly = $this->activatedUser(now()->subDays(2));
        $tooLate = $this->activatedUser(now()->subDays(4));

        // A free user registered 3 days ago is out of the B3 audience.
        $freeUser = User::factory()->create(['created_at' => now()->subDays(3)]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $dueUser,
            PaidOnboardingNotification::class,
            fn (PaidOnboardingNotification $notification): bool => $notification->step === 'day-3',
        );
        Notification::assertNotSentTo($tooEarly, PaidOnboardingNotification::class);
        Notification::assertNotSentTo($tooLate, PaidOnboardingNotification::class);
        Notification::assertNotSentTo($freeUser, PaidOnboardingNotification::class);
    }

    public function test_idempotent_no_duplicate_sends()
    {
        Notification::fake();

        $user = $this->activatedUser(now()->subDays(3));

        $this->artisan('mail:run-sequences')->assertSuccessful();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(PaidOnboardingNotification::class, 1);

        $this->assertDatabaseCount('sequence_sends', 1);
        $this->assertDatabaseHas('sequence_sends', [
            'user_id' => $user->id,
            'sequence' => 'b3-paid-onboarding',
            'step' => 'day-3',
        ]);
    }

    public function test_sequence_respects_opt_out()
    {
        Notification::fake();

        $user = $this->activatedUser(now()->subDays(3));

        app(NotificationPreferences::class)->update($user, ['product_updates' => false]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($user, PaidOnboardingNotification::class);
        $this->assertDatabaseCount('sequence_sends', 0);
    }

    public function test_unsubscribed_user_gets_transactional_only()
    {
        Notification::fake();

        $user = $this->activatedUser(now()->subDays(3));

        // One-click unsubscribe (signed link, logged-out) opts the user out
        // of ALL marketing categories.
        $this->get(URL::signedRoute('unsubscribe', ['user' => $user->id]))->assertOk();

        $this->assertFalse($user->fresh()->wantsMarketing());

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNothingSent();

        // Transactional mail is mandatory and unaffected by preferences.
        $this->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    /**
     * A user whose first paid order activated at the given time — the B3
     * audience anchor.
     */
    private function activatedUser(\Illuminate\Support\Carbon $activatedAt): User
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'starts_at' => $activatedAt,
            'ends_at' => $activatedAt->copy()->addYear(),
        ]);

        return $user;
    }
}
