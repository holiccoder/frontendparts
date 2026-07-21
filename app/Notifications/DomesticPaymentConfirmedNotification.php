<?php

namespace App\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\DomesticChannel;
use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Domestic payment-confirmed + access-unlocked mail (SPEC §16.1): queued
 * when a domestic order becomes Active (Alipay/WeChat notify or QR-page
 * polling, via OrderObserver — the single send point for activations).
 *
 * Reconciliation with WelcomeToProNotification (SPEC §16.1 keeps the two
 * rows distinct): Paddle is merchant of record and emails its own
 * receipts, so Paddle buyers only need the EN license summary; for
 * domestic orders WE are the buyer's only payment confirmation, so this
 * mail confirms the payment (channel, CNY amount, order reference) AND
 * unlocks access in one zh message. OrderObserver branches on
 * Order::isDomestic() so an activation sends exactly ONE of the two —
 * never both.
 */
class DomesticPaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public Order $order,
    ) {
        $this->locale = 'zh';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Payment confirmed — your FrontendParts access is unlocked'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We have received your :channel payment of ¥:amount (order #:id).', [
                'channel' => $this->channelLabel(),
                'amount' => (string) $this->order->amount,
                'id' => (string) $this->order->id,
            ]))
            ->line(__('Your :plan license (:period) is now active — the full component library, in both React and Vue, is unlocked for you.', [
                'plan' => ucfirst($this->order->plan->value),
                'period' => $this->periodLabel(),
            ]))
            ->line($this->accessLine())
            ->action(__('Start building'), route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('Payment confirmed'))
            ->body(__('Your :plan access is unlocked.', ['plan' => ucfirst($this->order->plan->value)]))
            ->icon('heroicon-o-check-badge')
            ->getDatabaseMessage();
    }

    private function channelLabel(): string
    {
        return match ($this->order->domestic_channel) {
            DomesticChannel::Alipay => __('Alipay'),
            DomesticChannel::Wechat => __('WeChat Pay'),
            null => __('Alipay'),
        };
    }

    private function periodLabel(): string
    {
        return match ($this->order->billing_period) {
            BillingPeriod::Monthly => __('Monthly billing'),
            BillingPeriod::Quarterly => __('Quarterly billing'),
            BillingPeriod::Yearly => __('Yearly billing'),
            BillingPeriod::Lifetime => __('Lifetime access'),
        };
    }

    private function accessLine(): string
    {
        if ($this->order->billing_period === BillingPeriod::Lifetime) {
            return __('Your access never expires.');
        }

        return __('Your current term runs until :date.', [
            'date' => $this->order->ends_at?->toDateString() ?? '',
        ]);
    }
}
