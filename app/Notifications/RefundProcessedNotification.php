<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Refund-processed mail (SPEC §16.1): confirms the refund was handed to
 * Paddle and that library access has ended. Payment records stay with
 * Paddle (merchant of record).
 */
class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public Order $order,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $plan = ucfirst($this->order->plan->value);
        $amount = trim($this->order->amount.' '.strtoupper($this->order->currency));

        return (new MailMessage)
            ->subject('Your refund has been processed')
            ->greeting("Hi {$notifiable->name},")
            ->line("We've processed the refund for your {$plan} order (#{$this->order->id}) — {$amount} is on its way back to your original payment method.")
            ->line('Your library access has ended; any code you previously downloaded remains yours to use under the license terms.')
            ->action('Browse the free library', route('components.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Refund processed')
            ->body("Order #{$this->order->id} was refunded.")
            ->icon('heroicon-o-arrow-uturn-left')
            ->getDatabaseMessage();
    }
}
