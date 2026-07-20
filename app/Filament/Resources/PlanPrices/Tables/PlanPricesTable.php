<?php

namespace App\Filament\Resources\PlanPrices\Tables;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlanPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan')
                    ->badge()
                    ->sortable(),
                TextColumn::make('period')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('provider')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('paddle_price_id')
                    ->label('Paddle price ID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')
                    ->options(OrderPlan::class),
                SelectFilter::make('period')
                    ->options(BillingPeriod::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('plan');
    }
}
