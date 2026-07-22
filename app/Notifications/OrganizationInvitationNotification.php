<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Team invitation mail (task 5.2): sent when an organization owner invites
 * someone by email. Carries the signed acceptance link — one link covers
 * both existing users and post-registration claims.
 *
 * Existing users follow the SPEC §16.1 convention (queued, mail + database
 * channels so the Filament bell and the email share one system); invitees
 * without an account receive an on-demand mail only — the database channel
 * has nowhere to land for them.
 */
class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $inviter = $this->invitation->inviter;
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("{$inviter->name} invited you to join {$organization->name} on {$appName}")
            ->greeting('Hi there,')
            ->line("{$inviter->name} invited you to join the **{$organization->name}** team on {$appName}.")
            ->line('As a team member you get everything the team plan includes — covered by the team subscription.')
            ->action('Accept invitation', $this->invitation->acceptUrl())
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $organization = $this->invitation->organization;

        return FilamentNotification::make()
            ->title("Invitation to {$organization->name}")
            ->body("{$this->invitation->inviter->name} invited you to join {$organization->name} — check your email for the acceptance link.")
            ->icon('heroicon-o-user-group')
            ->getDatabaseMessage();
    }
}
