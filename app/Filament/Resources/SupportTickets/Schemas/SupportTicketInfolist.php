<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\TicketCategory;
use App\Models\Order;
use App\Models\SupportTicket;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ticket thread view (SPEC §13.3): ticket facts, the order context section
 * (billing tickets only — the requester's five most recent orders with
 * plan/status/amount) and the full message thread.
 */
class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket')
                    ->schema([
                        TextEntry::make('subject'),
                        TextEntry::make('category')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('user.name'),
                        TextEntry::make('user.email'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Order context')
                    ->description("The requester's five most recent orders.")
                    ->visible(fn (SupportTicket $record): bool => $record->category === TicketCategory::Billing)
                    ->schema([
                        RepeatableEntry::make('order_context')
                            ->label('')
                            ->state(fn (SupportTicket $record): array => $record->recentOrders()
                                ->map(fn (Order $order): array => [
                                    'plan' => ucfirst($order->plan->value),
                                    'status' => str_replace('_', ' ', $order->status->value),
                                    'amount' => $order->amount.' '.strtoupper($order->currency),
                                    'created' => $order->created_at->toFormattedDateString(),
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('plan'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('amount'),
                                TextEntry::make('created'),
                            ])
                            ->columns(4)
                            ->placeholder('No orders yet.'),
                    ]),
                Section::make('Thread')
                    ->schema([
                        RepeatableEntry::make('thread')
                            ->label('')
                            ->state(fn (SupportTicket $record): array => $record->messages()
                                ->orderBy('created_at')
                                ->orderBy('id')
                                ->get()
                                ->map(fn ($message): array => [
                                    'author' => $message->author_type->value === 'admin' ? 'Support' : $record->user->name,
                                    'author_type' => $message->author_type->value,
                                    'body' => $message->body,
                                    'attachments' => collect($message->attachments ?? [])
                                        ->pluck('name')
                                        ->implode(', '),
                                    'created' => $message->created_at->toDayDateTimeString(),
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('author')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Support' ? 'info' : 'gray'),
                                TextEntry::make('created'),
                                TextEntry::make('body')
                                    ->columnSpanFull(),
                                TextEntry::make('attachments')
                                    ->label('Attachments')
                                    ->visible(fn (?string $state): bool => filled($state))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
