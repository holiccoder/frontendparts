<?php

namespace App\Notifications;

use App\Models\ComponentSubmission;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Approval mail (task 5.3): sent to the submitter when an admin approves
 * their submission — the component enters the review pipeline (in_review)
 * credited to them and the source lands in the library tree.
 */
class SubmissionApprovedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public ComponentSubmission $submission,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your component submission was approved: {$this->submission->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Great news — your submission \"{$this->submission->name}\" was accepted and is now in our review pipeline, credited to you.")
            ->line('It goes through the same dual-framework QA and preview checks as every library component before publishing.')
            ->action('View your submissions', route('dashboard.submissions.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Submission approved')
            ->body("\"{$this->submission->name}\" entered the review pipeline.")
            ->icon('heroicon-o-check-circle')
            ->getDatabaseMessage();
    }
}
