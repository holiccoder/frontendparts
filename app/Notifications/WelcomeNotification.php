<?php

namespace App\Notifications;

use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Day-0 welcome (SPEC §16.2): sent on registration — the generic greeting
 * every account gets, independent of any product surface.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Welcome to {$appName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Thanks for creating your {$appName} account — you're all set.")
            ->action('Open your dashboard', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Welcome to '.config('app.name'))
            ->body('Your account is ready — head to your dashboard to get started.')
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }
}
