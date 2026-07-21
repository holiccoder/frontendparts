<?php

namespace App\Filament\Resources\AffiliatePayouts\Tables;

use App\Enums\PayoutStatus;
use App\Models\AffiliatePayout;
use App\Services\Affiliates\PayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.user.name')
                    ->label('Affiliate')
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PayoutStatus $state): string => match ($state) {
                        PayoutStatus::Processing => 'warning',
                        PayoutStatus::Paid => 'success',
                        PayoutStatus::Failed => 'danger',
                    }),
                TextColumn::make('commissions_count')
                    ->counts('commissions')
                    ->label('Commissions'),
                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('paid_at')
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
                    ->options(PayoutStatus::options()),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark payout paid')
                    ->modalDescription(fn (AffiliatePayout $record): string => sprintf(
                        'Confirm the %s %s transfer to %s went out — attached commissions flip to paid and the affiliate is mailed.',
                        $record->amount,
                        strtoupper($record->currency),
                        $record->affiliate?->user?->email ?? "affiliate #{$record->affiliate_id}",
                    ))
                    ->schema([
                        TextInput::make('reference')
                            ->label('Payment reference')
                            ->helperText('The PayPal / Wise transaction id, for reconciliation.')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->visible(fn (AffiliatePayout $record): bool => $record->status === PayoutStatus::Processing)
                    ->action(function (AffiliatePayout $record, array $data): void {
                        app(PayoutService::class)->markPaid($record, $data['reference']);

                        Notification::make()
                            ->title("Payout #{$record->id} marked paid")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
