<?php

namespace App\Filament\Resources\Components\Tables;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Filament\Resources\Components\Actions\PreviewComponentAction;
use App\Filament\Resources\Components\Actions\PublishComponentAction;
use App\Filament\Resources\Components\Actions\RejectComponentAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (ComponentLevel $state): string => match ($state) {
                        ComponentLevel::Element => 'gray',
                        ComponentLevel::Block => 'info',
                        ComponentLevel::Section => 'warning',
                        ComponentLevel::Page => 'success',
                    })
                    ->sortable(),
                TextColumn::make('usageCategory.name')
                    ->label('Usage')
                    ->sortable(),
                TextColumn::make('access_level')
                    ->label('Access')
                    ->badge()
                    ->color(fn (AccessLevel $state): string => match ($state) {
                        AccessLevel::Free => 'success',
                        AccessLevel::Paid => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ComponentStatus $state): string => match ($state) {
                        ComponentStatus::Draft => 'gray',
                        ComponentStatus::InReview => 'warning',
                        ComponentStatus::Published => 'success',
                    })
                    ->sortable(),
                TextColumn::make('version')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options(ComponentLevel::class),
                SelectFilter::make('access_level')
                    ->label('Access')
                    ->options(AccessLevel::class),
                SelectFilter::make('status')
                    ->options(ComponentStatus::class),
                SelectFilter::make('usage_category_id')
                    ->label('Usage')
                    ->relationship('usageCategory', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                PreviewComponentAction::make(),
                PublishComponentAction::make(),
                RejectComponentAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
