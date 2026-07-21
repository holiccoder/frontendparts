<?php

namespace App\Services\Ai;

/**
 * Structured intent the AI extracts from a natural-language catalog query
 * (task 5.4, features.ai_search): refined keywords plus taxonomy filters.
 * Filters are expressed as slugs drawn from the taxonomy the caller
 * advertised to the model and are re-validated against it, so the model
 * can only narrow by values that actually exist.
 */
class SearchIntent
{
    /**
     * @param  list<string>  $usageCategories  usage-category slugs
     * @param  list<string>  $industries  industry-category slugs
     * @param  list<string>  $levels  ComponentLevel values
     */
    public function __construct(
        public readonly string $keywords,
        public readonly array $usageCategories = [],
        public readonly array $industries = [],
        public readonly array $levels = [],
    ) {}

    /**
     * True when the model produced no usable signal at all — the caller
     * treats this as "no intent" and falls back to plain search.
     */
    public function isEmpty(): bool
    {
        return trim($this->keywords) === ''
            && $this->usageCategories === []
            && $this->industries === []
            && $this->levels === [];
    }
}
