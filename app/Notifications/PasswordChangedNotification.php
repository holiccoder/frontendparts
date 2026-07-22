<?php

namespace App\Notifications;

use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public Carbon $changedAt;

    public function __construct()
    {
        $this->changedAt = now();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your '.config('app.name')." password was changed on {$this->changedAt->toDayDateTimeString()}.")
            ->line("If you didn't make this change, reset your password immediately to secure your account.")
            ->action('Reset your password', route('password.request'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Password changed')
            ->body("Your password was changed on {$this->changedAt->toDayDateTimeString()}.")
            ->icon('heroicon-o-shield-check')
            ->getDatabaseMessage();
    }
}
