<?php

namespace App\Notifications;

use App\Enums\BillingPeriod;
use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B5 — domestic renewal reminder (SPEC §16.2): the five-touch schedule
 * (T-7 / T-3 / T-1 before expiry, expired+1 / +7 after) for manual-renewal
 * domestic subscriptions. Domestic plans are one-time payments per period
 * with no auto-deduct (SPEC §7.5), so this mail IS the renewal mechanism —
 * every touch deep-links the pricing page where the buyer scans a fresh QR.
 *
 * Sent in Chinese: zh templates ship with domestic payments (SPEC §16.3);
 * the $locale property pins the whole send (queued included) to zh, with
 * the English source strings as fallback for any untranslated key.
 *
 * Classification: TRANSACTIONAL (SPEC §16.1) although scheduled through
 * the lifecycle engine — a renewal notice concerns an existing purchase
 * and the buyer's continued access, the same account-essential class as
 * dunning, so it never implements MarketingNotification and carries no
 * unsubscribe footer.
 */
class RenewalReminderNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public string $step,
        public Order $order,
    ) {
        $this->locale = 'zh';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]));

        foreach ($this->lines() as $line) {
            $message->line($line);
        }

        // Renewal means buying the same plan again through the QR checkout.
        return $message->action(__('Renew now'), route('pricing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->subject())
            ->body($this->lines()[0])
            ->icon('heroicon-o-arrow-path')
            ->getDatabaseMessage();
    }

    private function subject(): string
    {
        return match ($this->step) {
            't-minus-7' => __('Your :app subscription expires in 7 days', ['app' => config('app.name')]),
            't-minus-3' => __('Your :app subscription expires in 3 days', ['app' => config('app.name')]),
            't-minus-1' => __('Your :app subscription expires tomorrow', ['app' => config('app.name')]),
            'expired-plus-1' => __('Your :app subscription has expired', ['app' => config('app.name')]),
            'expired-plus-7' => __('Last call: renew your :app subscription', ['app' => config('app.name')]),
            default => __('Your :app subscription renewal', ['app' => config('app.name')]),
        };
    }

    /**
     * Anchoring line (expiry date) + escalating nudge across the schedule.
     *
     * @return list<string>
     */
    private function lines(): array
    {
        $anchor = in_array($this->step, ['t-minus-7', 't-minus-3', 't-minus-1'], true)
            ? __('Your :plan subscription (:period) ends on :date.', $this->replacements())
            : __('Your :plan subscription (:period) expired on :date.', $this->replacements());

        $nudge = match ($this->step) {
            't-minus-7' => __('Domestic subscriptions do not auto-renew — renew anytime before the expiry date to keep your access without interruption.'),
            't-minus-3' => __('Only three days left — renew now so your access continues without a gap.'),
            't-minus-1' => __('Your access pauses tomorrow unless the subscription is renewed — it only takes a minute to scan the QR code again.'),
            'expired-plus-1' => __('Your access is on hold — renew to unlock everything your plan includes again immediately.'),
            'expired-plus-7' => __('Your subscription expired a week ago — renew now to pick up right where you left off.'),
            default => __('Renew anytime to keep your access without interruption.'),
        };

        return [$anchor, $nudge];
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        return [
            'plan' => ucfirst($this->order->plan->value),
            'period' => $this->periodLabel(),
            'date' => $this->order->ends_at?->toDateString() ?? '',
        ];
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
}
