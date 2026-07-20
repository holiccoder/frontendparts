<?php

namespace App\Notifications;

use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class EmailChangedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public Carbon $changedAt;

    public function __construct(
        public string $oldEmail,
        public string $newEmail,
    ) {
        $this->changedAt = now();
    }

    /**
     * The security notice is delivered on-demand to the old address, which no
     * longer belongs to an account — the database channel needs a real
     * notifiable, so anonymous recipients only get the mail copy.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your email address was changed')
            ->greeting('Hello,')
            ->line("The email address on your FrontendParts account was changed from {$this->oldEmail} to {$this->newEmail} on {$this->changedAt->toDayDateTimeString()}.")
            ->line("If you didn't make this change, reset your password immediately and contact support.")
            ->action('Reset your password', route('password.request'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Email address changed')
            ->body("Account email changed from {$this->oldEmail} to {$this->newEmail}.")
            ->icon('heroicon-o-shield-exclamation')
            ->getDatabaseMessage();
    }
}
