<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * B7 — cancellation confirmation (SPEC §16.2): sent once when a
 * user-initiated cancellation is finalized. Carries the access-until date
 * (the order keeps entitling until ends_at, SPEC §7.3) and a signed
 * reactivation link.
 *
 * Classification: TRANSACTIONAL (SPEC §16.1) — it confirms an account
 * change the user just requested, like the order and refund mails, so it
 * is mandatory and carries no unsubscribe footer.
 *
 * Reactivation mechanism: the signed link points at `billing.reactivate`,
 * which forwards to checkout for the same plan — cancelling a Paddle
 * subscription cannot be undone from our side, so reactivation is a fresh
 * checkout (documented in ReactivateOrderController).
 */
class CancellationConfirmedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public Order $order,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $plan = ucfirst($this->order->plan->value);
        $accessUntil = $this->accessUntil();

        return (new MailMessage)
            ->subject("Your {$plan} subscription is cancelled")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$plan} subscription has been cancelled and will not renew.")
            ->line("**Access until: {$accessUntil}** — everything your plan includes stays unlocked for you until then.")
            ->line('Changed your mind? You can reactivate the same plan any time:')
            ->action('Reactivate my subscription', $this->reactivationUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Subscription cancelled')
            ->body('Your access runs until '.$this->accessUntil().'.')
            ->icon('heroicon-o-credit-card')
            ->getDatabaseMessage();
    }

    private function accessUntil(): string
    {
        return $this->order->ends_at !== null
            ? $this->order->ends_at->toFormattedDateString()
            : 'the end of the current billing period';
    }

    private function reactivationUrl(): string
    {
        return URL::signedRoute('billing.reactivate', ['order' => $this->order->id]);
    }
}
