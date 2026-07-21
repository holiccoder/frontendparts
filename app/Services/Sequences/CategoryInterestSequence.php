<?php

namespace App\Services\Sequences;

use App\Enums\ComponentEventType;
use App\Enums\NotificationCategory;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\User;
use App\Notifications\CategoryInterestNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B9 — category-interest trigger (SPEC §16.4 P3 "behavioral personalization
 * (B2-style triggers beyond blur-gate)"). This is the personalized successor
 * of B1 day-4 (SPEC §16.2 "popular components in browsed industries"), which
 * currently sends catalog-wide popular components: a free user who keeps
 * viewing one usage category without ever downloading gets the most-used
 * published components of exactly that category.
 *
 * Signal (SPEC §16.4 lists no concrete candidates — chosen and documented):
 * at least 5 view events in a single usage category within a rolling 14-day
 * window, and no download/copy events at all ("no download yet" — a user
 * who already downloaded has converted past browsing; lapsed downloaders
 * are covered by B10 instead).
 *
 * Throttle: SPEC gives no resend cadence — chosen rule (documented): at
 * most one B9 mail per 14 days per user. The step key is ISO-week-stamped
 * so the unique progress index permits one send per week, and
 * audienceIncludes() enforces the rolling 14-day gap (same pattern as B2's
 * 7-day throttle).
 *
 * Audience: free-entitled users only, re-checked at send time.
 */
class CategoryInterestSequence implements SequenceDefinition
{
    public const KEY = 'b9-category-interest';

    private const THRESHOLD = 5;

    private const WINDOW_DAYS = 14;

    private const THROTTLE_DAYS = 14;

    private const COMPONENT_LIMIT = 3;

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
        // Users hitting the view threshold in at least one usage category
        // within the window (one row per user+category; duplicates collapse
        // inside whereIn).
        $qualified = ComponentEvent::query()
            ->select('component_events.user_id')
            ->join('components', 'components.id', '=', 'component_events.component_id')
            ->whereNotNull('component_events.user_id')
            ->whereNotNull('components.usage_category_id')
            ->where('component_events.type', ComponentEventType::View)
            ->where('component_events.created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->groupBy('component_events.user_id', 'components.usage_category_id')
            ->havingRaw('COUNT(*) >= ?', [self::THRESHOLD]);

        return User::query()
            ->whereIn('id', $qualified)
            ->whereDoesntHave('componentEvents', fn ($query) => $query
                ->whereIn('type', [ComponentEventType::Download, ComponentEventType::Copy]));
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
        $category = $this->topCategory($user);

        if ($category === null) {
            return null;
        }

        $components = Component::query()
            ->published()
            ->where('usage_category_id', $category->id)
            ->with('usageCategory')
            ->withCount('events')
            ->orderByDesc('events_count')
            ->orderByDesc('created_at')
            ->limit(self::COMPONENT_LIMIT)
            ->get();

        if ($components->isEmpty()) {
            return null;
        }

        return new CategoryInterestNotification($category, $components);
    }

    /**
     * The usage category the user viewed most within the trigger window;
     * the latest view breaks a tie. Null when nothing qualifies (the
     * candidate query ran earlier — events may have raced).
     */
    private function topCategory(User $user): ?Category
    {
        $categoryId = ComponentEvent::query()
            ->join('components', 'components.id', '=', 'component_events.component_id')
            ->where('component_events.user_id', $user->id)
            ->whereNotNull('components.usage_category_id')
            ->where('component_events.type', ComponentEventType::View)
            ->where('component_events.created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->groupBy('components.usage_category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('MAX(component_events.created_at) DESC')
            ->limit(1)
            ->value('components.usage_category_id');

        return $categoryId === null ? null : Category::query()->find($categoryId);
    }
}
