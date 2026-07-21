<?php

namespace App\Notifications;

use App\Models\AffiliateCommission;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Referral conversion credited (SPEC §17.6 — transactional, queued): sent
 * to the affiliate when an attributed order is paid and the pending
 * commission is created. Fired at the OrderObserver seam on creation only
 * — replayed webhooks return the existing commission and never re-mail.
 */
class AffiliateConversionCreditedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public AffiliateCommission $commission,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->commission->amount.' '.strtoupper($this->commission->currency);

        return (new MailMessage)
            ->subject("You earned a {$amount} commission")
            ->greeting("Hi {$notifiable->name},")
            ->line("A purchase through your referral link just converted — **{$amount}** in commission was credited to your affiliate account.")
            ->line('It is pending while the refund window and holding period run, then it becomes payable and rolls into the next monthly payout batch.')
            ->line('[Open your affiliate dashboard]('.route('dashboard.affiliate').') to track your earnings.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Affiliate commission credited')
            ->body('A referred purchase converted — '.$this->commission->amount.' '.strtoupper($this->commission->currency).' credited (pending).')
            ->icon('heroicon-o-banknotes')
            ->getDatabaseMessage();
    }
}
