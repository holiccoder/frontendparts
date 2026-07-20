<?php

namespace App\Notifications\Contracts;

use App\Enums\NotificationCategory;

/**
 * Marker for marketing (non-transactional) mail (SPEC §16.1 vs §16.3).
 * Every marketing notification declares the preference category it bills
 * against, carries a one-click unsubscribe footer (see
 * Concerns\SendsMarketingMail), and is only sent after
 * NotificationPreferences::allows() passes — either by the sequence
 * engine or by any future direct sender (dunning, win-back).
 * Transactional notifications never implement this interface.
 */
interface MarketingNotification
{
    public function preferenceCategory(): NotificationCategory;
}
