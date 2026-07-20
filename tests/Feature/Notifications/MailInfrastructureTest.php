<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\EmailChangedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_notifications_implement_should_queue()
    {
        foreach ([
            WelcomeNotification::class,
            PasswordChangedNotification::class,
            EmailChangedNotification::class,
        ] as $notificationClass) {
            $this->assertContains(
                ShouldQueue::class,
                class_implements($notificationClass),
                "{$notificationClass} must implement ShouldQueue",
            );
        }
    }

    public function test_branded_markdown_layout_renders_logo_header()
    {
        $user = User::factory()->create();

        $html = (string) (new PasswordChangedNotification)->toMail($user)->render();

        $this->assertStringContainsString(config('app.url').'/brand/logo.png', $html);
        $this->assertStringContainsString(config('app.name'), $html);
    }

    public function test_database_channel_writes_notifications_row()
    {
        $user = User::factory()->create();

        Notification::send($user, new PasswordChangedNotification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => PasswordChangedNotification::class,
        ]);

        $row = DB::table('notifications')->where('notifiable_id', $user->id)->firstOrFail();

        $data = json_decode((string) $row->data, true);

        $this->assertSame('Password changed', $data['title']);
    }
}
