<?php

namespace App\Filament\Resources\Affiliates\Tables;

use App\Enums\AffiliateStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AffiliateStatus $state): string => match ($state) {
                        AffiliateStatus::Active => 'success',
                        AffiliateStatus::Suspended => 'danger',
                    }),
                TextColumn::make('referrals_count')
                    ->counts('referrals')
                    ->label('Clicks'),
                TextColumn::make('commissions_count')
                    ->counts('commissions')
                    ->label('Commissions'),
                TextColumn::make('payouts_count')
                    ->counts('payouts')
                    ->label('Payouts'),
                TextColumn::make('terms_accepted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(AffiliateStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
