<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin-reply mail (SPEC §16.1): sent to the ticket owner when the admin
 * replies in the thread. Links the dashboard thread page.
 */
class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public SupportTicket $ticket,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New reply on your ticket: {$this->ticket->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Our support team replied to your ticket \"{$this->ticket->subject}\".")
            ->action('View thread', route('dashboard.tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Ticket reply')
            ->body("Support replied to \"{$this->ticket->subject}\".")
            ->icon('heroicon-o-chat-bubble-left-right')
            ->getDatabaseMessage();
    }
}
