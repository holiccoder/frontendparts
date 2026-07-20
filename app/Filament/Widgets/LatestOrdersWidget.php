<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * P1 action table (SPEC §8.6 row 4): the ten most recent orders with a
 * vendor-side Paddle transaction link (sandbox-aware via
 * Order::receiptUrl()).
 */
class LatestOrdersWidget extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest orders')
            ->query(Order::query()->latest('created_at')->limit(10))
            ->columns([
                TextColumn::make('user.name'),
                TextColumn::make('plan')
                    ->badge(),
                TextColumn::make('billing_period')
                    ->badge(),
                TextColumn::make('amount')
                    ->numeric(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paddle_link')
                    ->label('Paddle')
                    ->state(fn (Order $record): string => $record->receiptUrl() === null ? '—' : 'View')
                    ->url(fn (Order $record): ?string => $record->receiptUrl())
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
