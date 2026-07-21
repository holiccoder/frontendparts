<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComponentCardResource;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Curated component bundles (SPEC §15.1 — "collections", e.g. a
 * restaurant landing kit): index + detail SSR pages. Only published
 * collections are visible — drafts 404 on direct access and never leak
 * into the index or the sitemap. Detail pages render the bundle in
 * curated pivot order.
 */
class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $collections = Collection::query()
            ->published()
            ->withCount(['components' => fn (Builder $query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Collection $collection): array => [
                'name' => $collection->name,
                'slug' => $collection->slug,
                'components_count' => $collection->components_count,
                'description' => $collection->description,
                'url' => $collection->publicUrl(),
            ])
            ->values()
            ->all();

        return $this->cachedResponse(Inertia::render('collections/index', [
            'collections' => $collections,
            'meta' => [
                'title' => 'Collections — curated component bundles',
                'description' => 'Hand-picked bundles of FrontendParts components that together ship a complete page — like a restaurant landing kit — recreated from the best sites on the web.',
                'canonical' => route('collections.index'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }

    public function show(Request $request, string $slug): Response
    {
        $collection = Collection::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $components = $collection->components()
            ->published()
            ->with('usageCategory')
            ->paginate(12)
            ->withQueryString();

        $description = $collection->description
            ?? 'A curated bundle of production-ready components for React and Vue, recreated from the best sites on the web.';

        return $this->cachedResponse(Inertia::render('collections/show', [
            'collection' => [
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $description,
            ],
            'components' => ComponentCardResource::collection($components),
            'meta' => [
                'title' => $collection->meta_title ?? "{$collection->name} — component collection",
                'description' => $collection->meta_description ?? $description,
                'canonical' => $collection->publicUrl(),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }
}
