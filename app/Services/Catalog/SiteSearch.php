<?php

namespace App\Services\Catalog;

use App\Models\Blog;
use App\Models\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Site-wide search (SPEC §15.1, FR-1.3 — DB-driven at launch): LIKE-based
 * matching over published components (name, slug, tags, usage + industry
 * categories) and live blog posts (title, excerpt, body). Drafts, in-review
 * components and scheduled posts stay invisible because both queries run on
 * the models' published scopes.
 *
 * Deliberately small and self-contained: the Meilisearch/Scout swap
 * (Phase 5.1, SPEC §13.2) replaces this one class behind SearchController.
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
        $like = '%'.$query.'%';

        return Component::query()
            ->published()
            ->with('usageCategory')
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $like))
                    ->orWhereHas('usageCategory', fn (Builder $category) => $category->where('name', 'like', $like))
                    ->orWhereHas('industries', fn (Builder $category) => $category->where('name', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->limit(self::COMPONENT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Blog>
     */
    public function posts(string $query): Collection
    {
        $like = '%'.$query.'%';

        return Blog::query()
            ->published()
            ->with('categories')
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('body', 'like', $like);
            })
            ->orderByDesc('published_at')
            ->limit(self::POST_LIMIT)
            ->get();
    }
}
