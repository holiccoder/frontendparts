<?php

namespace App\Filament\Resources\AffiliateCommissions;

use App\Filament\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Filament\Resources\AffiliateCommissions\Tables\AffiliateCommissionsTable;
use App\Models\AffiliateCommission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    public static function table(Table $table): Table
    {
        return AffiliateCommissionsTable::configure($table);
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
            'index' => ListAffiliateCommissions::route('/'),
        ];
    }
}
