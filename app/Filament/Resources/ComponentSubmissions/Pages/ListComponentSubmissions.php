<?php

namespace App\Filament\Resources\ComponentSubmissions\Pages;

use App\Filament\Resources\ComponentSubmissions\ComponentSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListComponentSubmissions extends ListRecords
{
    protected static string $resource = ComponentSubmissionResource::class;
}
