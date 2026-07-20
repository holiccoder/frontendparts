<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Resources\ComponentDetailResource;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Eloquent\Builder;

/**
 * JSON payload endpoint for the preview-modal overlay (SPEC §5.4): the same
 * ComponentDetailResource as the SSR detail page with the same published-only
 * basename resolution — but records NO view event (an overlay open is a
 * preview interaction, not a page view; views stay page-only per SPEC §8.6).
 * Rate-limited 60/minute via the route definition.
 */
class ComponentApiController extends Controller
{
    public function show(string $usage, string $slug): ComponentDetailResource
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

        /** @var Component $component */
        $component = $matches->first()->load(['usageCategory', 'industries', 'tags']);

        return new ComponentDetailResource($component);
    }
}
