<?php

namespace App\Filament\Resources\AffiliatePayouts;

use App\Filament\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Filament\Resources\AffiliatePayouts\Tables\AffiliatePayoutsTable;
use App\Models\AffiliatePayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliatePayoutResource extends Resource
{
    protected static ?string $model = AffiliatePayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    public static function table(Table $table): Table
    {
        return AffiliatePayoutsTable::configure($table);
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
            'index' => ListAffiliatePayouts::route('/'),
        ];
    }
}
