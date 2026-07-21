<?php

namespace App\Filament\Resources\Components\Pages;

use App\Filament\Resources\Components\Actions\GenerateVariantAction;
use App\Filament\Resources\Components\Actions\PreviewComponentAction;
use App\Filament\Resources\Components\Actions\PublishComponentAction;
use App\Filament\Resources\Components\Actions\RejectComponentAction;
use App\Filament\Resources\Components\ComponentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewComponent extends ViewRecord
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewComponentAction::make(),
            GenerateVariantAction::make(),
            PublishComponentAction::make(),
            RejectComponentAction::make(),
        ];
    }
}
