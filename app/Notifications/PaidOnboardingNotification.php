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
            'day-3' => 'Get the most from your license: scaffolding & workflow tips',
            'day-7' => 'How is FrontendParts working for you?',
            default => 'FrontendParts',
        };
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return match ($this->step) {
            // Day 3 — scaffolding/GitHub tips (SPEC §16.2).
            'day-3' => [
                'A few ways to move faster with your license:',
                '- Export whole packs from a project instead of copying components one by one',
                '- Pro licenses can scaffold straight into a Next.js or Nuxt app from the dashboard',
                '- Every component ships typed props and the same code in React and Vue — pick per project, not per purchase',
            ],
            // Day 7 — feedback ask (SPEC §16.2).
            'day-7' => [
                'You have had a week with the full library — what is working, and what is missing?',
                'Feature requests go straight to the roadmap, and billing or license questions land with a human. Either way, a support ticket is the fastest route.',
            ],
            default => ['Tips and updates from FrontendParts.'],
        };
    }

    private function ctaLabel(): string
    {
        return match ($this->step) {
            'day-3' => 'Read the docs',
            'day-7' => 'Share feedback',
            default => 'Open FrontendParts',
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
