<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
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
 * B1 — free onboarding drip mail (SPEC §16.2), one class covering the
 * engine-owned steps Day 2 / 4 / 7 / 12 after registration. (Day 0 is the
 * transactional WelcomeNotification sent at registration — see
 * FreeOnboardingSequence.) Sent only while the user stays free-entitled.
 */
class FreeOnboardingNotification extends Notification implements MarketingNotification, ShouldQueue
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
            'day-2' => 'Create your first project',
            'day-4' => 'Popular components this week',
            'day-7' => 'Unlock the full library',
            'day-12' => 'One payment, lifetime access',
            default => 'FrontendParts',
        };
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return match ($this->step) {
            // Day 2 — create first project/pack (SPEC §16.2).
            'day-2' => [
                'Projects are where components become a product: collect the sections you need into a pack, tweak the preview, and export clean React or Vue code in one click.',
                'Your free plan includes a project to get started — most people finish their first export in under five minutes.',
            ],
            // Day 4 — popular components in browsed industries. Industry-
            // level personalization is P3 (SPEC §16.4); for now "popular" is
            // the most-used published components catalog-wide.
            'day-4' => [
                'Here is what other builders are copying this week:',
                ...$this->popularComponents()->map(fn (Component $component): string => "- [{$component->name}]({$component->publicUrl()})"),
            ],
            // Day 7 — upgrade pitch (SPEC §16.2).
            'day-7' => [
                'You have had a week with the free library — here is what a paid plan adds:',
                '- The full catalog: every section, block and page, not just the free subset',
                '- Both React and Vue exports for every component',
                '- Unlimited projects and one-click pack exports',
            ],
            // Day 12 — lifetime intro (SPEC §16.2).
            'day-12' => [
                'Subscriptions are not for everyone — so there is a lifetime plan: one payment, every current component, and every future drop, forever.',
                'It pays for itself after a few months of a yearly plan, and fresh components land every week.',
            ],
            default => ['Fresh components and updates from FrontendParts.'],
        };
    }

    private function ctaLabel(): string
    {
        return match ($this->step) {
            'day-2' => 'Start your first project',
            'day-4' => 'Browse the catalog',
            'day-7' => 'See plans',
            'day-12' => 'See lifetime pricing',
            default => 'Open FrontendParts',
        };
    }

    private function ctaUrl(): string
    {
        return match ($this->step) {
            'day-2' => route('dashboard.projects.index'),
            'day-4' => route('components.index'),
            'day-7', 'day-12' => route('pricing'),
            default => route('home'),
        };
    }

    /**
     * Most-used published components (event volume), newest first as the
     * tie-break; falls back to the newest drops when there are no events.
     *
     * @return Collection<int, Component>
     */
    private function popularComponents(): Collection
    {
        return Component::query()
            ->published()
            ->with('usageCategory')
            ->withCount('events')
            ->orderByDesc('events_count')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
    }
}
