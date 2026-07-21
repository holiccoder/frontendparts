<?php

namespace App\Notifications;

use App\Models\AffiliatePayout;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payout sent (SPEC §17.6 — transactional, queued): sent to the affiliate
 * when the admin marks their payout batch paid with the provider reference
 * (PayPal / Wise). Carries the amount, the rail and the reference so the
 * affiliate can reconcile the transfer.
 */
class AffiliatePayoutSentNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public AffiliatePayout $payout,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->payout->amount.' '.strtoupper($this->payout->currency);

        return (new MailMessage)
            ->subject("Your {$amount} affiliate payout is on its way")
            ->greeting("Hi {$notifiable->name},")
            ->line("We just sent your affiliate payout of **{$amount}** via {$this->rail()}.")
            ->line("Payment reference: `{$this->payout->reference}`")
            ->line('Depending on the provider, the transfer can take a moment to show up in your account.')
            ->line('[Open your affiliate dashboard]('.route('dashboard.affiliate').') to see the payout in your history.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Affiliate payout sent')
            ->body($this->payout->amount.' '.strtoupper($this->payout->currency).' sent via '.$this->rail().' — ref '.$this->payout->reference)
            ->icon('heroicon-o-banknotes')
            ->getDatabaseMessage();
    }

    private function rail(): string
    {
        return match ($this->payout->method['method'] ?? null) {
            'wise' => 'Wise',
            default => 'PayPal',
        };
    }
}
