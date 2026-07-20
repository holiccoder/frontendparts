<?php

namespace App\Services\Sequences;

use App\Models\SequenceSend;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;

/**
 * Lifecycle email engine (SPEC §16.2): walks every registered sequence's
 * steps, resolves due candidates, and sends at most one notification per
 * (user, sequence, step) — ever for drips, per window for triggers and
 * digests.
 *
 * Guarantees, centralized here so definitions can't accidentally drop them:
 * - Preferences: every send is gated on NotificationPreferences for the
 *   sequence's marketing category; transactional mail never flows through
 *   this engine at all.
 * - Idempotency: the unique (user_id, sequence, step) row is created
 *   before sending; only the creating run sends.
 * - Audience: definitions re-check their gate per user at send time, so a
 *   user who upgraded since the candidate query ran is still skipped.
 */
class SequenceRunner
{
    public function __construct(
        private readonly SequenceRegistry $registry,
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * Run all sequences once.
     *
     * @return array<string, int> sent counts keyed by sequence key
     */
    public function run(): array
    {
        $summary = [];

        foreach ($this->registry->all() as $sequence) {
            $sent = 0;

            foreach ($sequence->steps() as $step) {
                $sequence->dueUsers($step)
                    ->whereDoesntHave('sequenceSends', fn ($query) => $query
                        ->where('sequence', $sequence->key())
                        ->where('step', $step->key))
                    ->chunkById(200, function ($users) use ($sequence, $step, &$sent): void {
                        foreach ($users as $user) {
                            if ($this->send($sequence, $step, $user)) {
                                $sent++;
                            }
                        }
                    });
            }

            $summary[$sequence->key()] = $sent;
        }

        return $summary;
    }

    private function send(SequenceDefinition $sequence, SequenceStep $step, User $user): bool
    {
        if (! $this->preferences->allows($user, $sequence->category())) {
            return false;
        }

        if (! $sequence->audienceIncludes($step, $user)) {
            return false;
        }

        $notification = $sequence->notification($step, $user);

        if ($notification === null) {
            return false;
        }

        $send = SequenceSend::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'sequence' => $sequence->key(),
                'step' => $step->key,
            ],
            ['sent_at' => now()],
        );

        if (! $send->wasRecentlyCreated) {
            return false;
        }

        $user->notify($notification);

        return true;
    }
}
