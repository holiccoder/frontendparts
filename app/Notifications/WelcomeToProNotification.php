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
 * Order-paid welcome mail (SPEC §16.1): sent when an order becomes Active.
 * Carries the license summary and first steps — Paddle, as merchant of
 * record, sends its own purchase documentation, so this email deliberately
 * contains no payment/invoice details (SPEC §16.1 note).
 */
class WelcomeToProNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function __construct(
        public Order $order,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $plan = ucfirst($this->order->plan->value);
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Welcome to {$appName} {$plan}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$plan} plan is now active — everything it includes is unlocked for you.")
            ->line('**Your plan**')
            ->line("- Plan: {$plan} ({$this->periodLabel()})")
            ->line('- Usage: unlimited personal and commercial use, including client work')
            ->line("- Access: {$this->accessLabel()}")
            ->line('**First steps**')
            ->line('- [Open your dashboard]('.route('dashboard').') to get started')
            ->line('- [Manage your orders]('.route('dashboard.orders.index').') to see renewal dates and plan status');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $plan = ucfirst($this->order->plan->value);

        return FilamentNotification::make()
            ->title('Welcome to '.config('app.name')." {$plan}")
            ->body("Your {$plan} plan is active — everything it includes is unlocked.")
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }

    private function periodLabel(): string
    {
        return match ($this->order->billing_period) {
            BillingPeriod::Lifetime => 'lifetime',
            default => ucfirst($this->order->billing_period->value).' billing',
        };
    }

    private function accessLabel(): string
    {
        if ($this->order->billing_period === BillingPeriod::Lifetime) {
            return 'never expires';
        }

        return $this->order->ends_at !== null
            ? 'current term runs until '.$this->order->ends_at->toFormattedDateString()
            : 'ongoing';
    }
}
