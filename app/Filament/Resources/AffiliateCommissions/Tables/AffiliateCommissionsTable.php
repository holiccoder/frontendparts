<?php

namespace App\Filament\Resources\AffiliateCommissions\Tables;

use App\Enums\CommissionStatus;
use App\Models\AffiliateCommission;
use App\Services\Affiliates\CommissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.user.name')
                    ->label('Affiliate')
                    ->searchable(),
                TextColumn::make('order_id')
                    ->label('Order')
                    ->prefix('#')
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CommissionStatus $state): string => match ($state) {
                        CommissionStatus::Pending => 'warning',
                        CommissionStatus::Payable => 'info',
                        CommissionStatus::Paid => 'success',
                        CommissionStatus::Voided => 'gray',
                    }),
                TextColumn::make('payable_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('voided_reason')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(CommissionStatus::options()),
            ])
            ->recordActions([
                Action::make('void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Void commission')
                    ->schema([
                        TextInput::make('reason')
                            ->label('Voided reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    // Only unpaid commissions can be voided (SPEC §17.5).
                    ->visible(fn (AffiliateCommission $record): bool => in_array(
                        $record->status,
                        [CommissionStatus::Pending, CommissionStatus::Payable],
                        true,
                    ))
                    ->action(function (AffiliateCommission $record, array $data): void {
                        app(CommissionService::class)->void($record, $data['reason']);

                        Notification::make()
                            ->title("Commission #{$record->id} voided")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
