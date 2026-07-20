<?php

namespace Tests\Feature\Notifications;

use App\Models\Component;
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

    public function test_welcome_email_contains_component_links_when_components_exist()
    {
        $components = Component::factory()->count(3)->published()->create();
        $user = User::factory()->create();

        $html = (string) (new WelcomeNotification)->toMail($user)->render();

        foreach ($components as $component) {
            $this->assertStringContainsString($component->name, $html);
        }

        // Fail-soft: with fewer than three published components the email
        // falls back to the plain catalog CTA (SPEC §16.2 Day-0).
        Component::query()->delete();

        $fallbackHtml = (string) (new WelcomeNotification)->toMail($user)->render();

        $this->assertStringContainsString(route('components.index'), $fallbackHtml);
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
