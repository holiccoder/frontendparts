<?php

namespace App\Notifications;

use App\Filament\Resources\ComponentSubmissions\ComponentSubmissionResource;
use App\Models\ComponentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * New-submission alert (task 5.3): sent on-demand to the admin inbox address
 * (`mail.admin.address`) whenever a user submits a component. Mail-only —
 * the recipient is an on-demand route, so no database channel. Carries the
 * Filament review link.
 */
class SubmissionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ComponentSubmission $submission,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New component submission: {$this->submission->name}")
            ->line(sprintf(
                '%s submitted a %s %s component: "%s".',
                $this->submission->user->name,
                $this->submission->framework->value,
                $this->submission->level->value,
                $this->submission->name,
            ))
            ->action(
                'Review submission',
                ComponentSubmissionResource::getUrl('view', ['record' => $this->submission]),
            );
    }
}
