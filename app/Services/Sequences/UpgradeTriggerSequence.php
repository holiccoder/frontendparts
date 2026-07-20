<?php

namespace App\Services\Sequences;

use App\Enums\ComponentEventType;
use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\UpgradeTriggerNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B2 — upgrade trigger (SPEC §16.2, behavioral 🟡): a free user who hits
 * the Pro blur-gate at least 3 times within a rolling 7-day window gets the
 * plan-comparison email.
 *
 * Throttle: SPEC gives no resend cadence — chosen rule (documented): at
 * most one B2 mail per 7 days per user. The step key is ISO-week-stamped
 * (`trigger-2026-W30`) so the unique progress index permits one send per
 * week, and audienceIncludes() enforces the rolling 7-day gap.
 */
class UpgradeTriggerSequence implements SequenceDefinition
{
    public const KEY = 'b2-upgrade-trigger';

    private const THRESHOLD = 3;

    private const WINDOW_DAYS = 7;

    private const THROTTLE_DAYS = 7;

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
        return User::query()->whereHas(
            'componentEvents',
            fn ($query) => $query
                ->where('type', ComponentEventType::GateHit)
                ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS)),
            '>=',
            self::THRESHOLD,
        );
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
        return new UpgradeTriggerNotification;
    }
}
