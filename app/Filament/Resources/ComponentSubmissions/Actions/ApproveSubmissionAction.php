<?php

namespace App\Filament\Resources\ComponentSubmissions\Actions;

use App\Enums\SubmissionStatus;
use App\Models\ComponentSubmission;
use App\Notifications\SubmissionApprovedNotification;
use App\Services\Submissions\SubmissionApprover;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Approve workflow action (task 5.3): hands the submission to the
 * SubmissionApprover — the source lands in the library tree in the
 * sync-discoverable layout and an in-review component row is created,
 * credited to the submitter. The submitter is mailed the outcome; the next
 * library:sync validates the tree and queues preview builds.
 */
class ApproveSubmissionAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ComponentSubmission $record): bool => $record->status === SubmissionStatus::Pending)
            ->requiresConfirmation()
            ->modalHeading('Approve submission')
            ->modalDescription('Creates an in-review component credited to the submitter and writes the code into the library tree. The next library:sync validates it and queues preview builds.')
            ->modalSubmitActionLabel('Approve')
            ->action(function (ComponentSubmission $record): void {
                try {
                    $component = app(SubmissionApprover::class)->approve($record);
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('Approval failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $record->refresh()->user->notify(new SubmissionApprovedNotification($record));

                Notification::make()
                    ->title('Submission approved')
                    ->body("Component {$component->slug} is now in review.")
                    ->success()
                    ->send();
            });
    }
}
