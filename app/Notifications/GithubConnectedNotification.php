<?php

namespace App\Notifications;

use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * GitHub-connected security notice (SPEC §16.1, §6.4): transactional mail +
 * database ping sent the moment an OAuth connection is established, so an
 * unexpected connection is noticed and can be revoked from settings.
 */
class GithubConnectedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public Carbon $connectedAt;

    public function __construct(
        public string $githubLogin,
    ) {
        $this->connectedAt = now();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('GitHub account connected')
            ->greeting("Hi {$notifiable->name},")
            ->line("The GitHub account \"{$this->githubLogin}\" was connected to your FrontendParts account on {$this->connectedAt->toDayDateTimeString()}.")
            ->line('It can create repositories and push project exports to them. If you didn\'t connect it, disconnect it immediately to secure your account.')
            ->action('Review connections', route('connections.edit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('GitHub account connected')
            ->body("GitHub \"{$this->githubLogin}\" was connected on {$this->connectedAt->toDayDateTimeString()}.")
            ->icon('heroicon-o-link')
            ->getDatabaseMessage();
    }
}
