<?php

namespace App\Notifications;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * New-ticket alert (SPEC §13.3, §16.1): sent on-demand to the support inbox
 * address (`mail.admin.address`) whenever a user opens a ticket. Mail-only —
 * the recipient is an on-demand route, so no database channel. Carries the
 * Filament thread link so the admin can jump straight into the inbox.
 */
class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New support ticket: {$this->ticket->subject}")
            ->line(sprintf(
                '%s opened a %s ticket: "%s".',
                $this->ticket->user->name,
                $this->ticket->category->value,
                $this->ticket->subject,
            ))
            ->action(
                'Open thread',
                SupportTicketResource::getUrl('view', ['record' => $this->ticket]),
            );
    }
}
