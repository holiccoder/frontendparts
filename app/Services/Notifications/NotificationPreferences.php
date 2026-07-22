<?php

namespace App\Services\Notifications;

use App\Enums\DigestFrequency;
use App\Enums\NotificationCategory;
use App\Models\User;

/**
 * The ONE place marketing-vs-transactional send decisions are made
 * (SPEC §16.1/§16.3). The sequence engine, the preference center, the
 * signed unsubscribe link and every future marketing sender (dunning,
 * win-back, …) must consult this service instead of reading
 * users.notification_preferences directly.
 *
 * Storage: JSON column on users (nullable JSON); null
 * means defaults — opted in to everything with a weekly digest.
 * Transactional mail (order, ticket, security, receipts) is mandatory:
 * it is not stored, cannot be disabled, and allows() always returns true
 * for it.
 */
class NotificationPreferences
{
    /**
     * Defaults for a user who never touched the preference center
     * (SPEC §16.3 — digest weekly until the user says otherwise).
     *
     * @var array{product_updates: bool, blog: bool, digest_frequency: string}
     */
    private const DEFAULTS = [
        'product_updates' => true,
        'blog' => true,
        'digest_frequency' => DigestFrequency::Weekly->value,
    ];

    /**
     * Resolved preferences with defaults merged in and unknown keys dropped.
     *
     * @return array{product_updates: bool, blog: bool, digest_frequency: string}
     */
    public function for(User $user): array
    {
        $stored = $user->notification_preferences ?? [];

        return [
            'product_updates' => (bool) ($stored['product_updates'] ?? self::DEFAULTS['product_updates']),
            'blog' => (bool) ($stored['blog'] ?? self::DEFAULTS['blog']),
            'digest_frequency' => $this->digestFrequency($user)->value,
        ];
    }

    /**
     * The send gate. Transactional is always allowed (SPEC §16.3
     * "transactional mandatory"); marketing categories consult the flags.
     */
    public function allows(User $user, NotificationCategory $category): bool
    {
        return match ($category) {
            NotificationCategory::Transactional => true,
            NotificationCategory::ProductUpdates => $this->for($user)['product_updates'],
            NotificationCategory::Blog => $this->for($user)['blog'],
            NotificationCategory::Digest => $this->digestFrequency($user) !== DigestFrequency::Off,
        };
    }

    public function digestFrequency(User $user): DigestFrequency
    {
        $stored = $user->notification_preferences ?? [];

        return DigestFrequency::tryFrom((string) ($stored['digest_frequency'] ?? '')) ?? DigestFrequency::Weekly;
    }

    /**
     * Whether any marketing category is still enabled.
     */
    public function wantsMarketing(User $user): bool
    {
        return $this->allows($user, NotificationCategory::ProductUpdates)
            || $this->allows($user, NotificationCategory::Blog)
            || $this->allows($user, NotificationCategory::Digest);
    }

    /**
     * Persist preference-center choices; unknown keys (including any
     * attempt to disable transactional mail) are ignored by the caller's
     * validated input and dropped here as well.
     *
     * @param  array{product_updates?: bool, blog?: bool, digest_frequency?: string}  $preferences
     */
    public function update(User $user, array $preferences): void
    {
        $resolved = $this->for($user);

        if (array_key_exists('product_updates', $preferences)) {
            $resolved['product_updates'] = (bool) $preferences['product_updates'];
        }

        if (array_key_exists('blog', $preferences)) {
            $resolved['blog'] = (bool) $preferences['blog'];
        }

        if (isset($preferences['digest_frequency'])
            && DigestFrequency::tryFrom((string) $preferences['digest_frequency']) !== null) {
            $resolved['digest_frequency'] = (string) $preferences['digest_frequency'];
        }

        $user->forceFill(['notification_preferences' => $resolved])->save();
    }

    /**
     * One-click unsubscribe (SPEC §16.3): opts the user out of ALL marketing
     * categories at once. Transactional mail is untouched by design.
     */
    public function unsubscribeAll(User $user): void
    {
        $user->forceFill([
            'notification_preferences' => [
                ...$this->for($user),
                'product_updates' => false,
                'blog' => false,
                'digest_frequency' => DigestFrequency::Off->value,
            ],
        ])->save();
    }
}
