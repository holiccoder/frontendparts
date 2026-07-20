<?php

namespace App\Filament\Resources\PlanPrices;

use App\Filament\Resources\PlanPrices\Pages\CreatePlanPrice;
use App\Filament\Resources\PlanPrices\Pages\EditPlanPrice;
use App\Filament\Resources\PlanPrices\Pages\ListPlanPrices;
use App\Filament\Resources\PlanPrices\Schemas\PlanPriceForm;
use App\Filament\Resources\PlanPrices\Tables\PlanPricesTable;
use App\Models\PlanPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlanPriceResource extends Resource
{
    protected static ?string $model = PlanPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return PlanPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanPricesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanPrices::route('/'),
            'create' => CreatePlanPrice::route('/create'),
            'edit' => EditPlanPrice::route('/{record}/edit'),
        ];
    }
}
