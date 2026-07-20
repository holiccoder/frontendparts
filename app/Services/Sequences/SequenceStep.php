<?php

namespace App\Services\Sequences;

/**
 * One sendable step of a lifecycle sequence (SPEC §16.2).
 *
 * For drip sequences (B1/B3) the key is stable per step (`day-2`, `day-3`)
 * and offsetDays is the anchor-relative delay. For periodic/triggered
 * sequences (B2/B4) the key is window-stamped by the definition
 * (`trigger-2026-W30`, `weekly:2026-07-20`) so the unique
 * (user, sequence, step) index naturally allows one send per window.
 */
final readonly class SequenceStep
{
    public function __construct(
        public string $key,
        public int $offsetDays = 0,
    ) {}
}
