<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\EmailChangedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TransactionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_queues_welcome_and_verification()
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        Notification::assertSentTo($user, WelcomeNotification::class);
        Notification::assertSentTimes(WelcomeNotification::class, 1);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_welcome_email_greets_and_links_to_the_dashboard()
    {
        $user = User::factory()->create(['name' => 'Test User']);

        $html = (string) (new WelcomeNotification)->toMail($user)->render();

        $this->assertStringContainsString('Test User', $html);
        $this->assertStringContainsString(route('dashboard'), $html);
    }

    public function test_password_change_queues_confirmation()
    {
        Notification::fake();

        $user = User::factory()->create();

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

    public function test_email_change_queues_confirmation_to_both_addresses()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'new@example.com',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(
            EmailChangedNotification::class,
            fn (EmailChangedNotification $notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'old@example.com',
        );

        Notification::assertSentTo($user->refresh(), VerifyEmail::class);
    }
}
