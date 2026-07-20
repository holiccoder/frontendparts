<?php

namespace App\Services\Sequences;

use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Notifications\PaidOnboardingNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B3 — paid onboarding (SPEC §16.2): Day 3 scaffolding/GitHub tips, Day 7
 * feedback ask, anchored on the user's FIRST paid activation (the earliest
 * order that ever became Active, using its starts_at with created_at as
 * fallback — the webhook stamps starts_at at activation).
 *
 * Day 0 ("license + quickstart") is deliberately NOT a step here: it is the
 * transactional WelcomeToProNotification sent once by OrderObserver on
 * activation (SPEC §16.1) — same mail, single send point, no double-send.
 *
 * Audience: users still holding a paid entitlement at send time, and never
 * users with an earlier purchase (renewals don't re-onboard).
 */
class PaidOnboardingSequence implements SequenceDefinition
{
    public const KEY = 'b3-paid-onboarding';

    private const OFFSETS = [3, 7];

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
        return array_map(
            fn (int $days): SequenceStep => new SequenceStep("day-{$days}", $days),
            self::OFFSETS,
        );
    }

    public function dueUsers(SequenceStep $step): Builder
    {
        $windowStart = now()->subDays($step->offsetDays)->startOfDay();
        $windowEnd = now()->subDays($step->offsetDays)->endOfDay();

        return User::query()
            ->whereHas('orders', fn ($query) => $query
                ->whereIn('status', [OrderStatus::Active, OrderStatus::PastDue])
                ->whereRaw('COALESCE(starts_at, created_at) BETWEEN ? AND ?', [
                    $windowStart->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ]))
            ->whereDoesntHave('orders', fn ($query) => $query
                ->whereIn('status', [
                    OrderStatus::Active,
                    OrderStatus::PastDue,
                    OrderStatus::Cancelled,
                    OrderStatus::Expired,
                ])
                ->whereRaw('COALESCE(starts_at, created_at) < ?', [$windowStart->toDateTimeString()]));
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        return $this->entitlements->for($user)->isPaid();
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        return new PaidOnboardingNotification($step->key);
    }
}
