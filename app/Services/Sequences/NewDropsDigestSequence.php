<?php

namespace App\Services\Sequences;

use App\Enums\DigestFrequency;
use App\Enums\NotificationCategory;
use App\Models\Blog;
use App\Models\Component;
use App\Models\User;
use App\Notifications\NewDropsDigestNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B4 — new-drops digest (SPEC §16.2/§16.3, retention-critical): new
 * published components + blog highlights, weekly or monthly per the user's
 * digest_frequency preference ('off' = no digest).
 *
 * Schedule (SPEC leaves the calendar day open — chosen and documented):
 * weekly digests go out on Mondays covering the previous 7 days, monthly
 * digests on the 1st of the month covering the previous month. The step key
 * embeds the period start (`weekly:2026-07-20`, `monthly:2026-08-01`) so
 * the unique progress index allows exactly one send per user per period.
 * An empty period (no new components and no posts) sends nothing.
 */
class NewDropsDigestSequence implements SequenceDefinition
{
    public const KEY = 'b4-new-drops-digest';

    public function __construct(
        private readonly NotificationPreferences $preferences,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Digest;
    }

    /**
     * @return list<SequenceStep>
     */
    public function steps(): array
    {
        return [
            new SequenceStep('weekly:'.now()->startOfWeek()->toDateString()),
            new SequenceStep('monthly:'.now()->startOfMonth()->toDateString()),
        ];
    }

    public function dueUsers(SequenceStep $step): Builder
    {
        $due = match ($this->frequency($step)) {
            DigestFrequency::Weekly => now()->isMonday(),
            DigestFrequency::Monthly => now()->day === 1,
            default => false,
        };

        // Frequency matching happens per user in audienceIncludes(); here
        // we only decide whether today is a send day for this period.
        return $due ? User::query() : User::query()->whereRaw('1 = 0');
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        // The engine already gates on the Digest category (frequency !==
        // off); here we match the period to the user's chosen cadence.
        return $this->preferences->digestFrequency($user) === $this->frequency($step);
    }

    private function frequency(SequenceStep $step): ?DigestFrequency
    {
        [$frequency] = explode(':', $step->key, 2);

        return DigestFrequency::tryFrom($frequency);
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        $frequency = $this->frequency($step);

        if ($frequency === null) {
            return null;
        }

        $since = $frequency === DigestFrequency::Monthly ? now()->subMonth() : now()->subWeek();

        $components = Component::query()
            ->published()
            ->with('usageCategory')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $posts = Blog::query()
            ->published()
            ->where('published_at', '>=', $since)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        if ($components->isEmpty() && $posts->isEmpty()) {
            return null;
        }

        return new NewDropsDigestNotification($components, $posts, $frequency->value);
    }
}
