<?php

namespace App\Services\Sequences;

use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DunningNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B6 — dunning (SPEC §16.2): 5 touches over ~15 days while an order is
 * PastDue, anchored on orders.past_due_at (stamped by OrderObserver when
 * the order enters PastDue — from the Paddle webhook or an admin edit).
 *
 * Classification: TRANSACTIONAL (see DunningNotification) — payment-failure
 * mail is account-essential and bypasses marketing preferences, which the
 * engine honors because NotificationPreferences::allows() is always true
 * for Transactional.
 *
 * Recovery stops the sequence: once no PastDue order remains (payment
 * recovered → Active, or the order was cancelled/refunded), both the
 * candidate query and the send-time audience gate exclude the user, so no
 * further touches go out.
 */
class DunningSequence implements SequenceDefinition
{
    public const KEY = 'b6-dunning';

    /**
     * Touch cadence across the ~15-day window (SPEC §16.2): Paddle's own
     * card retries cover day 0, so touch-1 lands the day after the failure.
     */
    private const OFFSETS = [1, 4, 8, 11, 15];

    public function key(): string
    {
        return self::KEY;
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Transactional;
    }

    /**
     * @return list<SequenceStep>
     */
    public function steps(): array
    {
        return array_map(
            fn (int $index, int $days): SequenceStep => new SequenceStep('touch-'.($index + 1), $days),
            array_keys(self::OFFSETS),
            self::OFFSETS,
        );
    }

    public function dueUsers(SequenceStep $step): Builder
    {
        $windowStart = now()->subDays($step->offsetDays)->startOfDay();
        $windowEnd = now()->subDays($step->offsetDays)->endOfDay();

        return User::query()
            ->whereHas('orders', fn ($query) => $query
                ->where('status', OrderStatus::PastDue)
                ->whereBetween('past_due_at', [
                    $windowStart->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ]));
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        return $this->pastDueOrder($user) !== null;
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        $order = $this->pastDueOrder($user);

        return $order === null ? null : new DunningNotification($step->key, $order);
    }

    /**
     * The order currently in dunning (latest PastDue wins if a user somehow
     * has more than one).
     */
    private function pastDueOrder(User $user): ?Order
    {
        return $user->orders()
            ->where('status', OrderStatus::PastDue)
            ->orderByDesc('past_due_at')
            ->orderByDesc('id')
            ->first();
    }
}
