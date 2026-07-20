<?php

namespace App\Services\Sequences;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\FreeOnboardingNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B1 — free onboarding drip (SPEC §16.2): Day 2 create-first-project, Day 4
 * popular components, Day 7 upgrade pitch, Day 12 lifetime intro, anchored
 * on the user's registration date.
 *
 * Day 0 ("welcome + 3 best components") is deliberately NOT a step here: it
 * is the transactional WelcomeNotification sent by the Registered listener
 * (SPEC §16.1 "Welcome + verify address") — same mail, single send point,
 * so no double-send can happen.
 *
 * Audience: free-entitled users; the drip stops as soon as the user becomes
 * paid (re-checked per step at send time).
 */
class FreeOnboardingSequence implements SequenceDefinition
{
    public const KEY = 'b1-free-onboarding';

    private const OFFSETS = [2, 4, 7, 12];

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
        return User::query()->whereBetween('created_at', [
            now()->subDays($step->offsetDays)->startOfDay(),
            now()->subDays($step->offsetDays)->endOfDay(),
        ]);
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        return ! $this->entitlements->for($user)->isPaid();
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        return new FreeOnboardingNotification($step->key);
    }
}
