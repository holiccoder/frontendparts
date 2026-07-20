<?php

namespace App\Filament\Resources\NotificationLogs\Tables;

use App\Models\NotificationLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class NotificationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('notification')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mail' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->state(fn (NotificationLog $record): string => $record->recipientLabel())
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHasMorph('notifiable', '*', fn ($morphQuery) => $morphQuery->where('email', 'like', "%{$search}%"))),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                SelectFilter::make('channel')
                    ->options([
                        'mail' => 'Mail',
                        'database' => 'Database',
                    ]),
            ])
            ->recordActions([
                Action::make('resend')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalHeading('Resend notification')
                    ->modalDescription(fn (NotificationLog $record): string => sprintf(
                        'Requeue %s to %s?',
                        class_basename($record->notification),
                        $record->recipientLabel(),
                    ))
                    ->action(function (NotificationLog $record): void {
                        try {
                            $record->resend();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Resend failed')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Notification requeued')
                            ->send();
                    }),
            ]);
    }
}
