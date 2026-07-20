<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (TicketStatus $state): string => match ($state) {
                        TicketStatus::Open => 'warning',
                        TicketStatus::Pending => 'info',
                        TicketStatus::Resolved => 'success',
                        TicketStatus::Closed => 'gray',
                    }),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Messages'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TicketStatus::options()),
                SelectFilter::make('category')
                    ->options(TicketCategory::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
