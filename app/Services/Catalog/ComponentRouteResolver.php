<?php

namespace App\Services\Catalog;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves public `/components/{usage}/{slug}` route pairs to a published
 * component. The URL slug is the basename of the stored full slug
 * (`elements/section-title-01` → `section-title-01`); resolution is scoped
 * to the usage category and 404s on zero or ambiguous matches. Shared by the
 * detail page, the modal JSON payload, and the copy/download endpoints so
 * every surface resolves components identically.
 */
class ComponentRouteResolver
{
    public function resolve(string $usage, string $slug): Component
    {
        $category = Category::query()
            ->where('type', CategoryType::Usage)
            ->where('slug', $usage)
            ->firstOrFail();

        $matches = Component::query()
            ->published()
            ->where('usage_category_id', $category->id)
            ->where(function (Builder $query) use ($slug): void {
                $query->where('slug', $slug)
                    ->orWhere('slug', 'like', '%/'.$slug);
            })
            ->limit(2)
            ->get();

        abort_unless($matches->count() === 1, 404);

        /** @var Component */
        return $matches->first();
    }
}
