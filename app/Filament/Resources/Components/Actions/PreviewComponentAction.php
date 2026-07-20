<?php

namespace App\Filament\Resources\Components\Actions;

use App\Models\Component;
use Filament\Actions\Action;

/**
 * Preview workflow action (SPEC §8.5): opens the component's built React
 * preview iframe in a new tab. Previews exist pre-publish, so the action is
 * visible whenever a React preview artifact path is recorded.
 */
class PreviewComponentAction
{
    public static function make(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->url(fn (Component $record): ?string => $record->previewUrl('react'), shouldOpenInNewTab: true)
            ->visible(fn (Component $record): bool => $record->previewPath('react') !== null);
    }
}
