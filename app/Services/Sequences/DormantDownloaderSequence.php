<?php

namespace App\Services\Sequences;

use App\Enums\ComponentEventType;
use App\Enums\NotificationCategory;
use App\Models\Component;
use App\Models\User;
use App\Notifications\DormantDownloaderNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * B10 — dormant-downloader re-engagement (SPEC §16.4 P3 "behavioral
 * personalization (B2-style triggers beyond blur-gate)"): a free user who
 * extracted value (downloaded or copied at least 2 components) and then
 * went quiet gets a "what's new since you left" mail listing the components
 * published since their last activity.
 *
 * Signal (SPEC §16.4 lists no concrete candidates — chosen and documented):
 * 2+ lifetime download/copy events (event count, matching B2's event-count
 * semantics — a repeat download is still an act of value extraction) and no
 * events of any type within the last 14 days. The two conditions together
 * pin the last activity at 14+ days ago.
 *
 * Audience: free-entitled users only, re-checked at send time. The content
 * is pure "what's new", not upgrade-flavored, but paid users already get
 * the B4 new-drops digest covering the same ground — narrowing to free
 * users keeps the two triggers (B9/B10) a clean partition of the free
 * audience and avoids digest overlap.
 *
 * Throttle: SPEC gives no resend cadence — chosen rule (documented): at
 * most one B10 mail per 14 days per user (ISO-week-stamped step key +
 * rolling 14-day gate in audienceIncludes(), same pattern as B2/B9). When
 * nothing new was published since the user's last activity the send is
 * suppressed without recording progress.
 */
class DormantDownloaderSequence implements SequenceDefinition
{
    public const KEY = 'b10-dormant-downloader';

    private const MIN_DOWNLOADS = 2;

    private const INACTIVE_DAYS = 14;

    private const THROTTLE_DAYS = 14;

    private const COMPONENT_LIMIT = 6;

    public function __construct(
        private readonly EntitlementService $entitlements = new EntitlementService,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::ProductUpdates;
    }

    /**
     * @return list<SequenceStep>
     */
    public function steps(): array
    {
        return [new SequenceStep('trigger-'.now()->format('o-\WW'))];
    }

    public function dueUsers(SequenceStep $step): Builder
    {
        return User::query()
            ->whereHas('componentEvents', fn ($query) => $query
                ->whereIn('type', [ComponentEventType::Download, ComponentEventType::Copy]), '>=', self::MIN_DOWNLOADS)
            ->whereDoesntHave('componentEvents', fn ($query) => $query
                ->where('created_at', '>=', now()->subDays(self::INACTIVE_DAYS)));
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        if ($this->entitlements->for($user)->isPaid()) {
            return false;
        }

        return ! $user->sequenceSends()
            ->where('sequence', self::KEY)
            ->where('sent_at', '>=', now()->subDays(self::THROTTLE_DAYS))
            ->exists();
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        // dueUsers() guarantees at least one event, so max() is non-null.
        $lastActivityAt = Carbon::parse($user->componentEvents()->max('created_at'));

        $components = Component::query()
            ->published()
            ->with('usageCategory')
            ->where('created_at', '>', $lastActivityAt)
            ->orderByDesc('created_at')
            ->limit(self::COMPONENT_LIMIT)
            ->get();

        if ($components->isEmpty()) {
            return null;
        }

        return new DormantDownloaderNotification($components, $lastActivityAt);
    }
}
