<?php

namespace App\Services\Sequences;

use App\Enums\BillingPeriod;
use App\Enums\NotificationCategory;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * B5 — domestic renewal reminders (SPEC §16.2): T-7 / T-3 / T-1 before
 * expiry and expired+1 / +7 after, anchored on orders.ends_at for DOMESTIC,
 * non-lifetime, Active orders. Domestic subscriptions are one-time payments
 * per period with no auto-deduct (SPEC §7.5), so these reminders are the
 * only renewal mechanism; PastDue and dunning never apply to domestic
 * orders. Paddle subscriptions auto-renew and are out of scope here.
 *
 * Step keys are expiry-date-stamped (`t-minus-7:2026-08-10`, the B2
 * window-stamp pattern), so the unique (user, sequence, step) progress
 * index permits one send per step PER PERIOD — a renewed subscription with
 * a new ends_at earns a fresh reminder cycle. The offset is signed (days
 * relative to expiry, negative = before), so the same window math as the
 * dunning anchor resolves the target day.
 *
 * Classification: TRANSACTIONAL (see RenewalReminderNotification) — like
 * dunning, a renewal notice concerns an existing purchase and continued
 * access, so the engine's preference gate lets it through for everyone.
 *
 * Stop condition: a user who already renewed (another Active paid order
 * outliving the expiring one — lifetime orders outlive everything) is
 * excluded by the send-time audience gate, so the expired+1/+7 nudges
 * never nag a renewed subscriber.
 *
 * Note: nothing in the app flips lapsed orders to Expired yet (the §7.3
 * state machine has no sweep), so a domestic subscription past ends_at is
 * still status Active — which is exactly what the expired+1/+7 steps key
 * on. If a sweep is introduced later, widen the status lists here.
 */
class RenewalReminderSequence implements SequenceDefinition
{
    public const KEY = 'b5-renewal-reminder';

    /**
     * Offset (days relative to expiry; negative = before) → step name.
     *
     * @var array<int, string>
     */
    private const STEPS = [
        -7 => 't-minus-7',
        -3 => 't-minus-3',
        -1 => 't-minus-1',
        1 => 'expired-plus-1',
        7 => 'expired-plus-7',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Transactional;
    }

    /**
     * Today's five steps; each key stamps the expiry date it targets on
     * this run, so progress is recorded per subscription period.
     *
     * @return list<SequenceStep>
     */
    public function steps(): array
    {
        return array_map(
            fn (int $offset, string $name): SequenceStep => new SequenceStep(
                $name.':'.now()->subDays($offset)->toDateString(),
                $offset,
            ),
            array_keys(self::STEPS),
            self::STEPS,
        );
    }

    public function dueUsers(SequenceStep $step): Builder
    {
        [$windowStart, $windowEnd] = $this->window($step);

        return User::query()
            ->whereHas('orders', fn ($query) => $query
                ->where('provider', PlanProvider::Domestic)
                ->where('status', OrderStatus::Active)
                ->where('billing_period', '!=', BillingPeriod::Lifetime)
                ->whereBetween('ends_at', [
                    $windowStart->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ]));
    }

    public function audienceIncludes(SequenceStep $step, User $user): bool
    {
        $order = $this->expiringOrder($user, $step);

        return $order !== null && ! $this->hasRenewedBeyond($user, $order);
    }

    public function notification(SequenceStep $step, User $user): ?Notification
    {
        $order = $this->expiringOrder($user, $step);

        if ($order === null || $this->hasRenewedBeyond($user, $order)) {
            return null;
        }

        return new RenewalReminderNotification(Str::before($step->key, ':'), $order);
    }

    /**
     * The domestic subscription expiring in this step's window (the latest
     * one, if the user somehow holds several).
     */
    private function expiringOrder(User $user, SequenceStep $step): ?Order
    {
        [$windowStart, $windowEnd] = $this->window($step);

        return $user->orders()
            ->where('provider', PlanProvider::Domestic)
            ->where('status', OrderStatus::Active)
            ->where('billing_period', '!=', BillingPeriod::Lifetime)
            ->whereBetween('ends_at', [
                $windowStart->toDateTimeString(),
                $windowEnd->toDateTimeString(),
            ])
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Already renewed: another Active paid order outlives the expiring one
     * (a lifetime order, ends_at = null, outlives everything).
     */
    private function hasRenewedBeyond(User $user, Order $order): bool
    {
        return $user->orders()
            ->whereKeyNot($order->getKey())
            ->where('plan', '!=', OrderPlan::Free)
            ->where('status', OrderStatus::Active)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $order->ends_at))
            ->exists();
    }

    /**
     * The calendar day this step targets: offsetDays relative to expiry —
     * negative offsets land in the future for the pre-expiry touches.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(SequenceStep $step): array
    {
        return [
            now()->subDays($step->offsetDays)->startOfDay(),
            now()->subDays($step->offsetDays)->endOfDay(),
        ];
    }
}
