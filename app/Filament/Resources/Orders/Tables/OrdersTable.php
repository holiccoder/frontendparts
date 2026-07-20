<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\Billing\RefundNotAllowedException;
use App\Services\Billing\RefundService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('plan')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('billing_period')
                    ->badge()
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('cancelled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Refund order')
                    ->modalDescription(fn (Order $record): string => sprintf(
                        'Refund %s %s for order #%d in full via Paddle? The buyer loses library access immediately.',
                        $record->amount,
                        strtoupper($record->currency),
                        $record->id,
                    ))
                    // Settings-driven refund window + paid states only (SPEC §7.3, §8.7).
                    ->visible(fn (Order $record): bool => app(RefundService::class)->refundable($record))
                    ->action(function (Order $record): void {
                        try {
                            app(RefundService::class)->refund($record);
                        } catch (RefundNotAllowedException $exception) {
                            Notification::make()
                                ->title('Refund not allowed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Order #{$record->id} refunded")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
