<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Models\Admin;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin notification log (SPEC §16.3): every sent notification is recorded
 * (one row per channel) via the NotificationSent listener, listed in a
 * Filament resource, and requeueable to the original recipient.
 *
 * These tests deliberately do NOT Notification::fake() the initial sends —
 * faking suppresses NotificationSent, so nothing would log. The array mail
 * driver (phpunit.xml) actually "sends" and the event fires.
 */
class NotificationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_lists_sent_notifications()
    {
        $user = User::factory()->create();

        $user->notify(new WelcomeNotification);
        $user->notify(new PasswordChangedNotification);

        // One row per channel (mail + database) per send.
        foreach ([WelcomeNotification::class, PasswordChangedNotification::class] as $notification) {
            foreach (['mail', 'database'] as $channel) {
                $this->assertDatabaseHas('notification_logs', [
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->id,
                    'notification' => $notification,
                    'channel' => $channel,
                ]);
            }
        }

        $this->assertSame(4, NotificationLog::query()->count());

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ListNotificationLogs::class)
            ->assertCanSeeTableRecords(NotificationLog::all())
            ->assertTableActionExists('resend');
    }

    public function test_resend_action_requeues()
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);

        $log = NotificationLog::query()
            ->where('notification', WelcomeNotification::class)
            ->where('channel', 'mail')
            ->firstOrFail();

        // Fake AFTER the initial send: the resend action must re-dispatch
        // the exact notification to the original notifiable.
        Notification::fake();

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ListNotificationLogs::class)
            ->callTableAction('resend', $log);

        Notification::assertSentTo($user, WelcomeNotification::class);
        Notification::assertSentTimes(WelcomeNotification::class, 1);
    }
}
