<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Blog;
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
 * B4 — new-drops digest (SPEC §16.2/§16.3): components published in the
 * period plus blog highlights, weekly or monthly per the user's
 * digest_frequency preference. Blog highlights deep-link to the public
 * article pages (`/blog/{slug}`, SPEC §13.1).
 */
class NewDropsDigestNotification extends Notification implements MarketingNotification, ShouldQueue
{
    use QueuesNotification;
    use SendsMarketingMail;

    /**
     * @param  Collection<int, Component>  $components
     * @param  Collection<int, Blog>  $posts
     */
    public function __construct(
        public Collection $components,
        public Collection $posts,
        public string $frequency,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Digest;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = $this->frequency === 'monthly' ? 'month' : 'week';

        $message = (new MailMessage)
            ->subject("New on FrontendParts this {$period}")
            ->greeting("Hi {$notifiable->name},");

        if ($this->components->isNotEmpty()) {
            $message->line("**New components this {$period}**");

            foreach ($this->components as $component) {
                $message->line("- [{$component->name}]({$component->publicUrl()})");
            }
        }

        if ($this->posts->isNotEmpty()) {
            $message->line('**From the blog**');

            foreach ($this->posts as $post) {
                $message->line("- [{$post->title}]({$post->publicUrl()}) — {$post->excerpt}");
            }
        }

        $message->action('Browse the catalog', route('components.index'));

        return $this->withUnsubscribeFooter($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $period = $this->frequency === 'monthly' ? 'month' : 'week';

        return FilamentNotification::make()
            ->title("New on FrontendParts this {$period}")
            ->body("{$this->components->count()} new component(s), {$this->posts->count()} blog post(s).")
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }
}
