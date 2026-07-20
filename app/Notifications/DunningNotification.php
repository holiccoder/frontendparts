<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B6 — dunning (SPEC §16.2): 5 touches over ~15 days while an order is
 * PastDue, every touch deep-linking the billing page where the user updates
 * their payment method.
 *
 * Classification: TRANSACTIONAL (SPEC §16.1), although it is scheduled
 * through the lifecycle engine. A payment-failure notice concerns an
 * existing purchase and the user's continued access — account-essential
 * mail in the same class as order and license emails, so it must reach
 * users who opted out of marketing (§16.3 "transactional mandatory"). It
 * therefore does not implement MarketingNotification and carries no
 * unsubscribe footer. Paddle's own card retries and receipts are untouched
 * (§16.1 note) — these mails only point at the update-payment page.
 */
class DunningNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public string $step,
        public Order $order,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Hi {$notifiable->name},");

        foreach ($this->lines() as $line) {
            $message->line($line);
        }

        // Every touch deep-links the one canonical update-payment page.
        return $message->action('Update payment method', route('settings.billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->subject())
            ->body($this->lines()[0])
            ->icon('heroicon-o-credit-card')
            ->getDatabaseMessage();
    }

    private function subject(): string
    {
        return match ($this->step) {
            'touch-1' => 'Your payment failed — we will retry shortly',
            'touch-2' => 'Your payment is still failing',
            'touch-3' => 'Action needed: your library access is at risk',
            'touch-4' => 'Final reminder: update your payment method',
            'touch-5' => 'Last chance to keep your FrontendParts access',
            default => 'Payment issue with your subscription',
        };
    }

    /**
     * Escalating urgency across the 15-day schedule (SPEC §16.2).
     *
     * @return list<string>
     */
    private function lines(): array
    {
        $plan = ucfirst($this->order->plan->value);

        return match ($this->step) {
            'touch-1' => [
                "We could not collect the payment for your {$plan} subscription. This is usually an expired card or a bank hiccup.",
                'We will retry automatically, but updating your payment method now avoids any interruption.',
            ],
            'touch-2' => [
                "The payment for your {$plan} subscription is still failing after our first retries.",
                'Please update your payment method so we can settle the balance on the next retry.',
            ],
            'touch-3' => [
                "Your {$plan} subscription has an outstanding failed payment and your library access is at risk.",
                'Update your payment method today to keep unlimited access to the full component library.',
            ],
            'touch-4' => [
                "This is a final reminder that the payment for your {$plan} subscription is still outstanding.",
                'If the balance is not settled, your access to the Pro library will end when the grace period closes.',
            ],
            'touch-5' => [
                "Your {$plan} subscription is about to lose access: the payment could not be collected within the grace period.",
                'Update your payment method now to restore the charge and keep your access without interruption.',
            ],
            default => [
                "There is a payment issue with your {$plan} subscription.",
                'Please update your payment method to avoid an interruption.',
            ],
        };
    }
}
