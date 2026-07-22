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
 * the provider that collected the payment (Paddle adjustments API for
 * international, Alipay/WeChat refund APIs for domestic, SPEC §7.5) and
 * that paid access has ended. Paddle payment records stay with Paddle
 * (merchant of record). Domestic buyers get the zh template (SPEC §16.3);
 * everyone else stays in the app locale.
 */
class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public Order $order,
    ) {
        $this->locale = $this->order->isDomestic() ? 'zh' : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = trim($this->order->amount.' '.strtoupper($this->order->currency));

        return (new MailMessage)
            ->subject(__('Your refund has been processed'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__("We've processed the refund for your :plan order (#:id) — :amount is on its way back to your original payment method.", [
                'plan' => ucfirst($this->order->plan->value),
                'id' => (string) $this->order->id,
                'amount' => $amount,
            ]))
            ->line(__('Your paid access has ended; anything you created stays in your account.'))
            ->action(__('Back to pricing'), route('pricing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('Refund processed'))
            ->body(__('Order #:id was refunded.', ['id' => (string) $this->order->id]))
            ->icon('heroicon-o-arrow-uturn-left')
            ->getDatabaseMessage();
    }
}
