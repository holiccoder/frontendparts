<?php

namespace App\Filament\Resources\Components\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('access_level')
                    ->label('Access')
                    ->badge()
                    ->sortable(),
            ]);
    }
}
