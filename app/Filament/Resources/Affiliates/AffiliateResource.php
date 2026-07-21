<?php

namespace App\Filament\Resources\Affiliates;

use App\Filament\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Resources\Affiliates\Tables\AffiliatesTable;
use App\Models\Affiliate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'view' => ViewAffiliate::route('/{record}'),
        ];
    }
}
