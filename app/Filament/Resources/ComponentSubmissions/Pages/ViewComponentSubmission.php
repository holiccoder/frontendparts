<?php

namespace App\Filament\Resources\ComponentSubmissions\Pages;

use App\Filament\Resources\ComponentSubmissions\Actions\ApproveSubmissionAction;
use App\Filament\Resources\ComponentSubmissions\Actions\RejectSubmissionAction;
use App\Filament\Resources\ComponentSubmissions\ComponentSubmissionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewComponentSubmission extends ViewRecord
{
    protected static string $resource = ComponentSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveSubmissionAction::make(),
            RejectSubmissionAction::make(),
        ];
    }
}
