<?php

namespace App\Services\Sequences;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * A lifecycle sequence (B1–B8 family, SPEC §16.2). Definitions are pure
 * policy: who is due, whether they still qualify at send time, and which
 * notification to send. The engine (SequenceRunner) owns orchestration —
 * preference checks, duplicate suppression and progress recording — so a
 * definition can never bypass §16.3 or double-send.
 */
interface SequenceDefinition
{
    /**
     * Stable machine key, e.g. `b1-free-onboarding` (stored on
     * sequence_sends).
     */
    public function key(): string;

    /**
     * The marketing category this sequence bills against. The runner checks
     * it against NotificationPreferences before every send. Lifecycle
     * sequences are never Transactional — transactional mail is sent
     * event-driven (SPEC §16.1) and does not flow through the engine.
     */
    public function category(): NotificationCategory;

    /**
     * Steps evaluated on each run, in order.
     *
     * @return list<SequenceStep>
     */
    public function steps(): array;

    /**
     * Candidate users whose timing makes the step due on this run
     * (day-window for drips, event threshold for triggers, calendar day for
     * digests). Audience gates and preferences are applied afterwards.
     *
     * @return Builder<User>
     */
    public function dueUsers(SequenceStep $step): Builder;

    /**
     * Runtime audience gate re-checked per user at send time (e.g. B1
     * stops once the user becomes paid, B3 requires an active paid
     * entitlement, B2 throttles re-sends, B4 matches the period to the
     * user's chosen digest_frequency).
     */
    public function audienceIncludes(SequenceStep $step, User $user): bool;

    /**
     * The notification for this step and user; null suppresses the send
     * (e.g. a digest with no new content) without recording progress.
     */
    public function notification(SequenceStep $step, User $user): ?Notification;
}
