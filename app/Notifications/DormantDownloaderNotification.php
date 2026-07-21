<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Component;
use App\Notifications\Concerns\QueuesNotification;
use App\Notifications\Concerns\SendsMarketingMail;
use App\Notifications\Contracts\MarketingNotification;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B10 — dormant-downloader mail (SPEC §16.4): "what's new since you left"
 * for lapsed free users — the components published after their last
 * activity (see DormantDownloaderSequence; throttled to at most one send
 * per 14 days).
 */
class DormantDownloaderNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    /**
     * @param  Collection<int, Component>  $components
     */
    public function __construct(
        public Collection $components,
        public CarbonInterface $lastActivityAt,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::ProductUpdates;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('New components since your last visit')
            ->greeting("Hi {$notifiable->name},")
            ->line("You've been away since {$this->lastActivityAt->toFormattedDateString()} — and the library kept growing. Here is what dropped since then:");

        foreach ($this->components as $component) {
            $message->line("- [{$component->name}]({$component->publicUrl()})");
        }

        $message->action('See what is new', route('components.index'));

        return $this->withUnsubscribeFooter($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New components since your last visit')
            ->body("{$this->components->count()} new component(s) dropped while you were away.")
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }
}
