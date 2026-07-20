<?php

namespace App\Filament\Resources\Components\Actions;

use App\Enums\ComponentStatus;
use App\Models\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Reject workflow action (SPEC §8.5): an in-review component is sent back to
 * draft with a required reason, stored in review_note for the author.
 */
class RejectComponentAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Component $record): bool => $record->status === ComponentStatus::InReview)
            ->modalHeading('Reject component')
            ->modalSubmitActionLabel('Reject')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (Component $record, array $data): void {
                $record->update([
                    'status' => ComponentStatus::Draft,
                    'review_note' => $data['reason'],
                ]);

                Notification::make()
                    ->title('Component rejected')
                    ->body('Sent back to draft with a review note.')
                    ->danger()
                    ->send();
            });
    }
}
