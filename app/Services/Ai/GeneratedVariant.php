<?php

namespace App\Services\Ai;

/**
 * One AI-generated component variation (task 5.4, features.ai_variants): a
 * display name, a short human-readable summary of what changed (surfaced
 * to the admin in the review notification), and replacement entry sources
 * for both library frameworks. The params/data contract is inherited from
 * the original component — a variation must keep the same API.
 */
class GeneratedVariant
{
    public function __construct(
        public readonly string $name,
        public readonly string $summary,
        public readonly string $reactCode,
        public readonly string $vueCode,
    ) {}
}
