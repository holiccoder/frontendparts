<?php

namespace App\Filament\Resources\Components\Actions;

use App\Jobs\GenerateComponentVariant;
use App\Models\Component;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * AI variant generation (task 5.4, features.ai_variants): queues the
 * generation job and returns immediately. The job writes the variant as a
 * new in-review component linked to this one — never auto-published — and
 * reports back via a database notification. Hidden unless the feature flag
 * is on.
 */
class GenerateVariantAction
{
    public static function make(): Action
    {
        return Action::make('generate-variant')
            ->label('Generate variant')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->visible(fn (): bool => (bool) app(Settings::class)->get('features.ai_variants'))
            ->requiresConfirmation()
            ->modalHeading('Generate AI variant')
            ->modalDescription('An AI will restyle this component (same params API, different visuals) and file the result as a new in-review component linked to this one. Nothing is published automatically.')
            ->modalSubmitActionLabel('Generate variant')
            ->action(function (Component $record): void {
                GenerateComponentVariant::dispatch($record->id, (int) auth('admin')->id());

                Notification::make()
                    ->title('Variant generation queued')
                    ->body('You will get a notification when the AI variant is ready for review.')
                    ->success()
                    ->send();
            });
    }
}
