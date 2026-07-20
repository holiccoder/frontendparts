<?php

namespace App\Enums;

/**
 * Email classification (SPEC §16.1 vs §16.3). Transactional mail is mandatory
 * and bypasses preferences entirely; the three marketing categories are
 * individually opt-out-able from /settings/notifications. Digest has no
 * boolean flag of its own — it is governed by the digest_frequency
 * preference ('off' = opted out).
 */
enum NotificationCategory: string
{
    case Transactional = 'transactional';
    case ProductUpdates = 'product_updates';
    case Blog = 'blog';
    case Digest = 'digest';
}
