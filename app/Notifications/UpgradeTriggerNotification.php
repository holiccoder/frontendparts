<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Notifications\Concerns\QueuesNotification;
use App\Notifications\Concerns\SendsMarketingMail;
use App\Notifications\Contracts\MarketingNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B2 — upgrade trigger (SPEC §16.2): plan-comparison mail sent to free
 * users who hit the Pro blur-gate at least 3 times within a rolling week
 * (throttled to at most one send per 7 days — see UpgradeTriggerSequence).
 */
class UpgradeTriggerNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::ProductUpdates;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Those Pro components you previewed — here is the plan math')
            ->greeting("Hi {$notifiable->name},")
            ->line('We noticed you opening a few Pro components behind the preview gate this week — here is how the plans compare:')
            ->line('- **Free** — the free subset of the catalog, 1 project')
            ->line('- **Starter** — the full library, both React and Vue exports, up to 3 projects')
            ->line('- **Pro** — everything in Starter, unlimited projects, plus Next.js/Nuxt scaffolding')
            ->line('Monthly, yearly and lifetime options — cancel anytime, lifetime never renews.')
            ->action('Compare plans', route('pricing'));

        return $this->withUnsubscribeFooter($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Compare plans')
            ->body('You hit the Pro preview gate a few times this week — see how the plans compare.')
            ->icon('heroicon-o-arrow-trending-up')
            ->getDatabaseMessage();
    }
}
