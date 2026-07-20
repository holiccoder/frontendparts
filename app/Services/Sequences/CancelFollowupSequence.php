<?php

namespace App\Services\Sequences;

use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CancelFollowupNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * B7 — cancel-flow followups (SPEC §16.2): Day 7 reactivation and Day 30
 * win-back, anchored on orders.cancelled_at.
 *
 * Audience: users whose LATEST order is the cancelled one. A user who
 * reactivated (a newer Active order exists) is excluded — win-back mail
 * after a successful reactivation would be noise.
 *
 * Classification: MARKETING (product_updates — see
 * CancelFollowupNotification), so the engine's preference gate applies and
 * the mails carry the one-click unsubscribe footer.
 */
class CancelFollowupSequence implements SequenceDefinition
{
    public const KEY = 'b7-cancel-followup';

    private const OFFSETS = [7, 30];

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
                ->where('status', OrderStatus::Cancelled)
                ->whereBetween('cancelled_at', [
                    $windowStart->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ]));
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        return $this->latestOrder($user)?->status === OrderStatus::Cancelled;
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        $order = $this->latestOrder($user);

        if ($order === null || $order->status !== OrderStatus::Cancelled) {
            return null;
        }

        return new CancelFollowupNotification($step->key, $order);
    }

    /**
     * Latest order wins (same convention as EntitlementService).
     */
    private function latestOrder(User $user): ?Order
    {
        return $user->orders()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
