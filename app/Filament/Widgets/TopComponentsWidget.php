<?php

namespace App\Filament\Widgets;

use App\Services\Admin\PopularityStats;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * P2 catalog intelligence (SPEC §8.6 row 5): the ten most-engaged components
 * over the trailing 30 days, ranked by views + downloads with both counts
 * visible — the "what's actually being used" board.
 */
class TopComponentsWidget extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top components · last 30 days')
            ->query(app(PopularityStats::class)->topComponentsQuery())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('slug')
                    ->color('gray'),
                TextColumn::make('views_30d')
                    ->label('Views · 30d')
                    ->numeric(),
                TextColumn::make('downloads_30d')
                    ->label('Downloads · 30d')
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
