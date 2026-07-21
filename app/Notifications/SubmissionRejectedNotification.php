<?php

namespace App\Notifications;

use App\Models\ComponentSubmission;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Rejection mail (task 5.3): sent to the submitter when an admin rejects
 * their submission; carries the admin's review note so the user knows what
 * to change before resubmitting.
 */
class SubmissionRejectedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public ComponentSubmission $submission,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your component submission was not accepted: {$this->submission->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Thanks for contributing — unfortunately your submission \"{$this->submission->name}\" was not accepted this time.")
            ->line("Reason: {$this->submission->review_note}")
            ->action('View your submissions', route('dashboard.submissions.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Submission rejected')
            ->body("\"{$this->submission->name}\" was not accepted.")
            ->icon('heroicon-o-x-circle')
            ->getDatabaseMessage();
    }
}
