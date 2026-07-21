<?php

namespace App\Filament\Resources\ComponentSubmissions\Tables;

use App\Enums\ComponentLevel;
use App\Enums\SubmissionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComponentSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('level')
                    ->badge(),
                TextColumn::make('framework')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (SubmissionStatus $state): string => match ($state) {
                        SubmissionStatus::Pending => 'warning',
                        SubmissionStatus::Approved => 'success',
                        SubmissionStatus::Rejected => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(SubmissionStatus::options()),
                SelectFilter::make('level')
                    ->options(collect(ComponentLevel::cases())
                        ->mapWithKeys(fn (ComponentLevel $level): array => [$level->value => ucfirst($level->value)])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
