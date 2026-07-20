<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ticket-resolved mail (SPEC §16.1): sent to the ticket owner when the admin
 * marks the ticket resolved. Links the dashboard thread page — a reply there
 * re-opens the ticket (TicketStatus transition map).
 */
class TicketResolvedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public SupportTicket $ticket,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket resolved: {$this->ticket->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your ticket \"{$this->ticket->subject}\" was marked as resolved.")
            ->line('Reply on the thread if you need anything else — replying re-opens the ticket.')
            ->action('View thread', route('dashboard.tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Ticket resolved')
            ->body("\"{$this->ticket->subject}\" was marked as resolved.")
            ->icon('heroicon-o-check-circle')
            ->getDatabaseMessage();
    }
}
