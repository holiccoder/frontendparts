<?php

namespace App\Services\Catalog;

use App\Models\Blog;
use App\Models\Component;
use Illuminate\Database\Eloquent\Collection;

/**
 * Site-wide search (SPEC §15.1, FR-1.3): Scout-driven matching over
 * published components (name, slug, tags, usage + industry categories) and
 * live blog posts (title, excerpt, body), swapped in for the launch LIKE
 * implementation per the Phase-5.1 Meilisearch plan (SPEC §13.2).
 *
 * The configured engine owns matching: `collection` in local dev/tests
 * (substring match over the flattened searchable payload, honoring
 * shouldBeSearchable at query time) and Meilisearch in production (ranked,
 * typo-tolerant matching over the same payload, with published gating
 * enforced by shouldBeSearchable at indexing time). Result ordering is
 * engine-native — relevance under Meilisearch, newest-first otherwise.
 */
class SiteSearch
{
    /**
     * Result caps per group — the launch catalog is small enough that a
     * capped, ordered list beats pagination on a grouped results page.
     */
    public const COMPONENT_LIMIT = 12;

    public const POST_LIMIT = 6;

    /**
     * @return Collection<int, Component>
     */
    public function components(string $query): Collection
    {
        return Component::search($query)
            ->take(self::COMPONENT_LIMIT)
            ->get()
            ->load('usageCategory');
    }

    /**
     * @return Collection<int, Blog>
     */
    public function posts(string $query): Collection
    {
        return Blog::search($query)
            ->take(self::POST_LIMIT)
            ->get()
            ->load('categories');
    }
}
