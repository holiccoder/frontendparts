<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Category;
use App\Models\Component;
use App\Notifications\Concerns\QueuesNotification;
use App\Notifications\Concerns\SendsMarketingMail;
use App\Notifications\Contracts\MarketingNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B9 — category-interest mail (SPEC §16.4): the personalized successor of
 * B1 day-4's catalog-wide "popular components" — the most-used published
 * components of the one usage category the user keeps browsing (see
 * CategoryInterestSequence; throttled to at most one send per 14 days).
 */
class CategoryInterestNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    /**
     * @param  Collection<int, Component>  $components
     */
    public function __construct(
        public Category $category,
        public Collection $components,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::ProductUpdates;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Popular {$this->category->name} components on FrontendParts")
            ->greeting("Hi {$notifiable->name},")
            ->line("You've been exploring {$this->category->name} components lately — here are the ones builders use the most:");

        foreach ($this->components as $component) {
            $message->line("- [{$component->name}]({$component->publicUrl()})");
        }

        $message->action("Browse {$this->category->name}", route('components.usage', $this->category->slug));

        return $this->withUnsubscribeFooter($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Popular {$this->category->name} components")
            ->body("The {$this->components->count()} most-used {$this->category->name} components, picked from your recent browsing.")
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }
}
