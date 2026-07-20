<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\CategoryType;
use App\Enums\ComponentLevel;
use App\Http\Resources\ComponentCardResource;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog index + usage landing pages (SPEC §15.1, FR-1). Filters are
 * server-side via the query string; `framework` is a display preference
 * only (every component ships both implementations) and never touches
 * the query.
 */
class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'industry' => ['sometimes', 'array'],
            'industry.*' => ['string', 'max:100'],
            'usage' => ['sometimes', 'nullable', 'string', 'max:100'],
            'level' => ['sometimes', 'nullable', Rule::enum(ComponentLevel::class)],
            'access' => ['sometimes', 'nullable', Rule::enum(AccessLevel::class)],
            'framework' => ['sometimes', 'nullable', Rule::in(['react', 'vue'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = Component::query()
            ->published()
            ->with('usageCategory');

        $industries = array_values(array_filter((array) ($validated['industry'] ?? [])));

        if ($industries !== []) {
            $query->whereHas('industries', fn (Builder $q) => $q->whereIn('slug', $industries));
        }

        if ($usage = ($validated['usage'] ?? null)) {
            $query->whereHas('usageCategory', fn (Builder $q) => $q->where('slug', $usage));
        }

        if ($level = ($validated['level'] ?? null)) {
            $query->where('level', $level);
        }

        if ($access = ($validated['access'] ?? null)) {
            $query->where('access_level', $access);
        }

        if ($search = trim((string) ($validated['q'] ?? ''))) {
            $query->where(function (Builder $q) use ($search): void {
                $like = '%'.$search.'%';

                $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like));
            });
        }

        $components = $query
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('catalog/index', [
            'components' => ComponentCardResource::collection($components),
            'filters' => $this->filterLists(),
            'active' => [
                'industry' => $industries,
                'usage' => $validated['usage'] ?? null,
                'level' => $validated['level'] ?? null,
                'access' => $validated['access'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
            'framework' => $validated['framework'] ?? 'react',
            'meta' => [
                'title' => 'Component catalog — React & Vue sections, blocks and pages',
                'description' => 'Browse every FrontendParts component: heroes, pricing, testimonials, dashboards and more — each with live preview and clean React + Vue code.',
                'canonical' => URL::to('/components'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    public function usage(string $usage): Response
    {
        $category = Category::query()
            ->where('type', CategoryType::Usage)
            ->where('slug', $usage)
            ->firstOrFail();

        $components = $category->usageComponents()
            ->published()
            ->with('usageCategory')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $relatedUsages = Category::query()
            ->where('type', CategoryType::Usage)
            ->visible()
            ->whereKeyNot($category->id)
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(fn (Category $related): array => [
                'name' => $related->name,
                'slug' => $related->slug,
                'url' => route('components.usage', ['usage' => $related->slug]),
            ])
            ->values()
            ->all();

        return Inertia::render('catalog/usage', [
            'usage' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'zone' => $category->zone,
                'description' => config("catalog_copy.usage.{$category->slug}")
                    ?? "Production-ready {$category->name} components for React and Vue, recreated from the best sites on the web.",
            ],
            'components' => ComponentCardResource::collection($components),
            'relatedUsages' => $relatedUsages,
            'meta' => [
                'title' => "{$category->name} components for React & Vue",
                'description' => config("catalog_copy.usage.{$category->slug}")
                    ?? "Production-ready {$category->name} components for React and Vue, recreated from the best sites on the web.",
                'canonical' => route('components.usage', ['usage' => $category->slug]),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    /**
     * Filter lists follow the SPEC §4.3 governance rule: a category only
     * appears once it holds at least 3 published components.
     *
     * @return array<string, mixed>
     */
    private function filterLists(): array
    {
        $publishedCount = ['components' => fn (Builder $query) => $query->published()];
        $publishedUsageCount = ['usageComponents' => fn (Builder $query) => $query->published()];

        return [
            'industries' => Category::query()
                ->where('type', CategoryType::Industry)
                ->visible()
                ->withCount($publishedCount)
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'components_count' => $category->components_count,
                ])
                ->values()
                ->all(),
            'usages' => Category::query()
                ->where('type', CategoryType::Usage)
                ->visible()
                ->withCount($publishedUsageCount)
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'zone' => $category->zone,
                    'components_count' => $category->usage_components_count,
                ])
                ->values()
                ->all(),
            'levels' => array_map(fn (ComponentLevel $level): string => $level->value, ComponentLevel::cases()),
            'access' => array_map(fn (AccessLevel $access): string => $access->value, AccessLevel::cases()),
        ];
    }
}
