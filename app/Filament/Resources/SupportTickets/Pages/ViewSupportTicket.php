<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Enums\TicketAuthorType;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketResolvedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Ticket thread view with the reply / resolve / close inbox actions
 * (SPEC §13.3). Admin reply flips the ticket to pending (TicketStatus map)
 * and mails the user; resolve mails the user too (SPEC §16.1).
 */
class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->visible(fn (SupportTicket $record): bool => $record->status !== TicketStatus::Closed)
                ->schema([
                    Textarea::make('body')
                        ->label('Reply')
                        ->required()
                        ->maxLength(10000),
                    FileUpload::make('attachments')
                        ->disk('local')
                        ->directory(fn (SupportTicket $record): string => "support-tickets/{$record->id}")
                        ->multiple()
                        ->maxFiles(3)
                        ->maxSize(5120),
                ])
                ->action(function (SupportTicket $record, array $data): void {
                    $record->messages()->create([
                        'author_type' => TicketAuthorType::Admin,
                        'author_id' => auth('admin')->id(),
                        'body' => $data['body'],
                        'attachments' => collect($data['attachments'] ?? [])
                            ->map(fn (string $path): array => [
                                'name' => basename($path),
                                'path' => $path,
                                'size' => 0,
                            ])
                            ->values()
                            ->all(),
                    ]);

                    // Admin reply → pending (TicketStatus map; pending stays pending).
                    if ($record->status !== TicketStatus::Pending && $record->status->canTransitionTo(TicketStatus::Pending)) {
                        $record->update(['status' => TicketStatus::Pending]);
                    }

                    $record->user->notify(new TicketRepliedNotification($record));

                    Notification::make()
                        ->title('Reply sent')
                        ->success()
                        ->send();
                }),
            Action::make('resolve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (SupportTicket $record): bool => in_array($record->status, [TicketStatus::Open, TicketStatus::Pending], true))
                ->action(function (SupportTicket $record): void {
                    $record->update(['status' => TicketStatus::Resolved]);

                    $record->user->notify(new TicketResolvedNotification($record));

                    Notification::make()
                        ->title('Ticket resolved')
                        ->success()
                        ->send();
                }),
            Action::make('close')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (SupportTicket $record): bool => $record->status !== TicketStatus::Closed)
                ->action(function (SupportTicket $record): void {
                    $record->update(['status' => TicketStatus::Closed]);

                    Notification::make()
                        ->title('Ticket closed')
                        ->success()
                        ->send();
                }),
        ];
    }
}
