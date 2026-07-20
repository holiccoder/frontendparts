<?php

namespace App\Filament\Resources\Components;

use App\Filament\Resources\Components\Pages\ListComponents;
use App\Filament\Resources\Components\Pages\ViewComponent;
use App\Filament\Resources\Components\Schemas\ComponentInfolist;
use App\Filament\Resources\Components\Tables\ComponentsTable;
use App\Models\Component;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catalog management resource (SPEC §8.5). Component source code is never
 * form-edited — the resource is read-only (list + view) and all changes flow
 * through the sync pipeline; the only writes are the workflow actions
 * (Publish / Reject) on the view page and table rows. No create/edit pages
 * exist by design.
 */
class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ComponentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComponentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComponents::route('/'),
            'view' => ViewComponent::route('/{record}'),
        ];
    }
}
