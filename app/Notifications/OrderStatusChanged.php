<?php

namespace App\Notifications;

use App\Models\Order;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $previousStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order status updated')
            ->line(sprintf(
                'Order #%d status changed from "%s" to "%s".',
                $this->order->id,
                $this->previousStatus,
                $this->order->status->value,
            ))
            ->action('View order', url('/admin/orders/'.$this->order->id))
            ->line('Thank you for using FrontendParts.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Order status updated')
            ->body(sprintf(
                'Order #%d: %s → %s',
                $this->order->id,
                $this->previousStatus,
                $this->order->status->value,
            ))
            ->icon('heroicon-o-credit-card')
            ->getDatabaseMessage();
    }
}
