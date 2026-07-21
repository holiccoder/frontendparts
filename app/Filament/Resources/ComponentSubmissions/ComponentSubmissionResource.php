<?php

namespace App\Filament\Resources\ComponentSubmissions;

use App\Filament\Resources\ComponentSubmissions\Pages\ListComponentSubmissions;
use App\Filament\Resources\ComponentSubmissions\Pages\ViewComponentSubmission;
use App\Filament\Resources\ComponentSubmissions\Schemas\ComponentSubmissionInfolist;
use App\Filament\Resources\ComponentSubmissions\Tables\ComponentSubmissionsTable;
use App\Models\ComponentSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Community submission review inbox (task 5.3, PRD §4.2 P3). Like the
 * component resource, submission code is never form-edited — the resource
 * is read-only (list + view) and the only writes are the Approve / Reject
 * workflow actions. Approval hands the code to the library pipeline.
 */
class ComponentSubmissionResource extends Resource
{
    protected static ?string $model = ComponentSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?string $navigationLabel = 'Submissions';

    protected static ?string $modelLabel = 'submission';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ComponentSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComponentSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComponentSubmissions::route('/'),
            'view' => ViewComponentSubmission::route('/{record}'),
        ];
    }
}
