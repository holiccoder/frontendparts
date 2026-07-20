<?php

namespace App\Filament\Resources\Components\Actions;

use App\Enums\ComponentStatus;
use App\Models\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;

/**
 * Publish workflow action (SPEC §8.5). The modal forces the full QA
 * checklist (all five items accepted) and the record must also pass the
 * canPublish() artifact gate (previews + screenshots); otherwise the action
 * notifies danger and leaves the status untouched. On success the submitted
 * checklist is stored on the component as the publish audit trail.
 */
class PublishComponentAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Component $record): bool => $record->status !== ComponentStatus::Published)
            ->modalHeading('Publish component')
            ->modalDescription('Confirm every QA checklist item before publishing.')
            ->modalSubmitActionLabel('Publish')
            ->schema([
                Checkbox::make('viewports')
                    ->label('Renders correctly at 3 viewports (375 / 768 / 1280)')
                    ->accepted(),
                Checkbox::make('visual_parity')
                    ->label('React and Vue builds have visual parity')
                    ->accepted(),
                Checkbox::make('data_separated')
                    ->label('Sample data is fully separated from markup')
                    ->accepted(),
                Checkbox::make('license_clean')
                    ->label('Content is license-clean (no copied code, text, or imagery)')
                    ->accepted(),
                Checkbox::make('accessibility')
                    ->label('Accessibility checked for interactive components (keyboard, focus, ARIA)')
                    ->accepted(),
            ])
            ->action(function (Component $record, array $data): void {
                if (! $record->canPublish()) {
                    Notification::make()
                        ->title('Cannot publish')
                        ->body('Previews/screenshots missing — the preview build must be green before publishing.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'status' => ComponentStatus::Published,
                    'qa_checklist' => $data,
                    'review_note' => null,
                ]);

                Notification::make()
                    ->title('Component published')
                    ->success()
                    ->send();
            });
    }
}
