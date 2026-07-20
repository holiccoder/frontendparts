<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\FreeOnboardingNotification;
use App\Notifications\PasswordChangedNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SequenceEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_b1_day2_sent_only_to_users_registered_2_days_ago()
    {
        Notification::fake();

        $dueUser = User::factory()->create(['created_at' => now()->subDays(2)]);
        $tooEarly = User::factory()->create(['created_at' => now()->subDay()]);
        $tooLate = User::factory()->create(['created_at' => now()->subDays(3)]);

        // Paid users registered 2 days ago are out of the B1 audience — the
        // drip stops once the user upgrades (SPEC §16.2).
        $paidUser = User::factory()->create(['created_at' => now()->subDays(2)]);
        Order::factory()->create([
            'user_id' => $paidUser->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'starts_at' => now()->subDays(30),
            'ends_at' => null,
        ]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $dueUser,
            FreeOnboardingNotification::class,
            fn (FreeOnboardingNotification $notification): bool => $notification->step === 'day-2',
        );
        Notification::assertNotSentTo($tooEarly, FreeOnboardingNotification::class);
        Notification::assertNotSentTo($tooLate, FreeOnboardingNotification::class);
        Notification::assertNotSentTo($paidUser, FreeOnboardingNotification::class);
    }

    public function test_idempotent_no_duplicate_sends()
    {
        Notification::fake();

        $user = User::factory()->create(['created_at' => now()->subDays(2)]);

        $this->artisan('mail:run-sequences')->assertSuccessful();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(FreeOnboardingNotification::class, 1);

        $this->assertDatabaseCount('sequence_sends', 1);
        $this->assertDatabaseHas('sequence_sends', [
            'user_id' => $user->id,
            'sequence' => 'b1-free-onboarding',
            'step' => 'day-2',
        ]);
    }

    public function test_sequence_respects_opt_out()
    {
        Notification::fake();

        $user = User::factory()->create(['created_at' => now()->subDays(2)]);

        app(NotificationPreferences::class)->update($user, ['product_updates' => false]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($user, FreeOnboardingNotification::class);
        $this->assertDatabaseCount('sequence_sends', 0);
    }

    public function test_unsubscribed_user_gets_transactional_only()
    {
        Notification::fake();

        $user = User::factory()->create(['created_at' => now()->subDays(2)]);

        // One-click unsubscribe (signed link, logged-out) opts the user out
        // of ALL marketing categories (SPEC §16.3).
        $this->get(URL::signedRoute('unsubscribe', ['user' => $user->id]))->assertOk();

        $this->assertFalse($user->fresh()->wantsMarketing());

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNothingSent();

        // Transactional mail is mandatory and unaffected by preferences
        // (SPEC §16.1/§16.3).
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
}
