<?php

namespace App\Filament\Resources\Components\Pages;

use App\Filament\Resources\Components\ComponentResource;
use App\Services\Library\LibrarySync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListComponents extends ListRecords
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runSync')
                ->label('Run sync')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $result = app(LibrarySync::class)->run();

                    $summary = "Scanned {$result->scanned}, upserted {$result->upserted}, failed ".count($result->failures()).'.';

                    if ($result->hasErrors()) {
                        Notification::make()
                            ->title('Library sync finished with errors')
                            ->body($summary)
                            ->danger()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Library sync complete')
                            ->body($summary)
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
