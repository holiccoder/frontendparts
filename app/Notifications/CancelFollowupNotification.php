<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Order;
use App\Notifications\Concerns\QueuesNotification;
use App\Notifications\Concerns\SendsMarketingMail;
use App\Notifications\Contracts\MarketingNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * B7 — cancel-flow followups (SPEC §16.2): Day 7 reactivation nudge and
 * Day 30 win-back, anchored on the order's cancelled_at.
 *
 * Classification: MARKETING (product_updates). Unlike the cancellation
 * confirmation (transactional), these mails try to win back a churned
 * customer — commercial content under §16.3, so they carry the one-click
 * unsubscribe footer and respect the preference center.
 */
class CancelFollowupNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    public function __construct(
        public string $step,
        public Order $order,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::ProductUpdates;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Hi {$notifiable->name},");

        foreach ($this->lines() as $line) {
            $message->line($line);
        }

        $message->action($this->ctaLabel(), $this->ctaUrl());

        return $this->withUnsubscribeFooter($message, $notifiable);
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
            'day-7' => 'Your library access has ended — want it back?',
            'day-30' => 'What you missed this month at FrontendParts',
            default => 'FrontendParts',
        };
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        $plan = ucfirst($this->order->plan->value);

        return match ($this->step) {
            // Day 7 — reactivation (SPEC §16.2).
            'day-7' => [
                "Your {$plan} subscription ended a week ago and the full library is locked again.",
                'Reactivate now and pick up exactly where you left off — your projects and packs are still here.',
            ],
            // Day 30 — win-back (SPEC §16.2).
            'day-30' => [
                'It has been a month since you cancelled. Since then the library has kept growing:',
                '- New components drop every week, in both React and Vue',
                '- Pro subscribers get every new drop the day it lands',
                'Come back and see what is new — reactivation takes less than a minute.',
            ],
            default => ['We would love to have you back.'],
        };
    }

    private function ctaLabel(): string
    {
        return match ($this->step) {
            'day-7' => 'Reactivate my subscription',
            'day-30' => 'Reactivate and catch up',
            default => 'Open FrontendParts',
        };
    }

    private function ctaUrl(): string
    {
        return match ($this->step) {
            'day-7', 'day-30' => URL::signedRoute('billing.reactivate', ['order' => $this->order->id]),
            default => route('home'),
        };
    }
}
