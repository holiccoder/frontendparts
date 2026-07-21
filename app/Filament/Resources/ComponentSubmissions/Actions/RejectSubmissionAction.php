<?php

namespace App\Filament\Resources\ComponentSubmissions\Actions;

use App\Enums\SubmissionStatus;
use App\Models\ComponentSubmission;
use App\Notifications\SubmissionRejectedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Reject workflow action (task 5.3): a pending submission is rejected with
 * a required reason, stored in review_note and mailed to the submitter so
 * they know what to change before resubmitting.
 */
class RejectSubmissionAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ComponentSubmission $record): bool => $record->status === SubmissionStatus::Pending)
            ->modalHeading('Reject submission')
            ->modalSubmitActionLabel('Reject')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (ComponentSubmission $record, array $data): void {
                $record->update([
                    'status' => SubmissionStatus::Rejected,
                    'review_note' => $data['reason'],
                ]);

                $record->user->notify(new SubmissionRejectedNotification($record));

                Notification::make()
                    ->title('Submission rejected')
                    ->body('The submitter was notified with your review note.')
                    ->danger()
                    ->send();
            });
    }
}
