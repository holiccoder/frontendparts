<?php

namespace App\Enums;

/**
 * B4 new-drops digest cadence (SPEC §16.2/§16.3). Off doubles as the digest
 * opt-out, so "digest" needs no separate boolean in the preference center.
 */
enum DigestFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Off = 'off';
}
