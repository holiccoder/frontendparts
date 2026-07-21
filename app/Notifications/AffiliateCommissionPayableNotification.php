<?php

namespace App\Notifications;

use App\Models\AffiliateCommission;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Commission payable (SPEC §17.6 — transactional, queued): sent to the
 * affiliate when the daily `affiliates:mark-payable` command flips their
 * commission from pending to payable — the refund window + holding period
 * have elapsed and the money is now heading for the payout batch.
 */
class AffiliateCommissionPayableNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public AffiliateCommission $commission,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->commission->amount.' '.strtoupper($this->commission->currency);

        return (new MailMessage)
            ->subject("{$amount} in commission is now payable")
            ->greeting("Hi {$notifiable->name},")
            ->line("Good news — **{$amount}** in affiliate commission cleared the refund window and holding period and is now payable.")
            ->line('Once your payable balance reaches the payout threshold, it is included in the next monthly payout batch.')
            ->line('[Open your affiliate dashboard]('.route('dashboard.affiliate').') to check your balance and payout method.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Affiliate commission payable')
            ->body($this->commission->amount.' '.strtoupper($this->commission->currency).' cleared holding and is now payable.')
            ->icon('heroicon-o-banknotes')
            ->getDatabaseMessage();
    }
}
