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
 * B3 — paid onboarding mail (SPEC §16.2), covering the engine-owned steps
 * Day 3 (scaffolding/GitHub tips) and Day 7 (feedback ask) after the first
 * paid activation. (Day 0 "license + quickstart" is the transactional
 * WelcomeToProNotification — see PaidOnboardingSequence.)
 */
class PaidOnboardingNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    public function __construct(
        public string $step,
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
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }

    private function subject(): string
    {
        return match ($this->step) {
            'day-3' => 'Get the most from your plan: tips & workflow',
            'day-7' => 'How is '.config('app.name').' working for you?',
            default => config('app.name'),
        };
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return match ($this->step) {
            // Day 3 — tips.
            'day-3' => [
                'A few ways to move faster with your plan:',
                '- Explore every feature your plan unlocks from the dashboard',
                '- The docs cover setup, billing and common questions',
                '- Manage orders, receipts and renewal dates under Dashboard → Orders',
            ],
            // Day 7 — feedback ask.
            'day-7' => [
                'You have had a week with your plan — what is working, and what is missing?',
                'Feature requests go straight to the roadmap, and billing questions land with a human. Either way, a support ticket is the fastest route.',
            ],
            default => ['Tips and updates from '.config('app.name').'.'],
        };
    }

    private function ctaLabel(): string
    {
        return match ($this->step) {
            'day-3' => 'Read the docs',
            'day-7' => 'Share feedback',
            default => 'Open '.config('app.name'),
        };
    }

    private function ctaUrl(): string
    {
        return match ($this->step) {
            'day-3' => route('docs.index'),
            'day-7' => route('dashboard.tickets.create'),
            default => route('home'),
        };
    }
}
