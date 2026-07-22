<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Resources\ComponentCardResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Industry index + curated per-industry landing pages (SPEC §15.1).
 */
class IndustryController extends Controller
{
    public function index(Request $request): Response
    {
        $industries = Category::query()
            ->where('type', CategoryType::Industry)
            ->withCount(['components' => fn (Builder $query) => $query->published()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'components_count' => $category->components_count,
                'description' => config("catalog_copy.industries.{$category->slug}"),
                'url' => route('industries.show', ['industry' => $category->slug]),
            ])
            ->values()
            ->all();

        return $this->cachedResponse(Inertia::render('industries/index', [
            'industries' => $industries,
            'meta' => [
                'title' => 'Components by industry — SaaS, ecommerce, fintech & more',
                'description' => 'Curated React and Vue component collections for 12 industries, recreated from the best sites in each vertical.',
                'canonical' => route('industries.index'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }

    public function show(Request $request, string $industry): Response
    {
        $category = Category::query()
            ->where('type', CategoryType::Industry)
            ->where('slug', $industry)
            ->firstOrFail();

        $components = $category->components()
            ->published()
            ->with('usageCategory')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return $this->cachedResponse(Inertia::render('industries/show', [
            'industry' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => config("catalog_copy.industries.{$category->slug}")
                    ?? "Production-ready components for {$category->name} products, recreated from the best sites in the vertical.",
            ],
            'components' => ComponentCardResource::collection($components),
            'meta' => [
                'title' => "{$category->name} components for React & Vue",
                'description' => config("catalog_copy.industries.{$category->slug}")
                    ?? "Production-ready components for {$category->name} products, recreated from the best sites in the vertical.",
                'canonical' => route('industries.show', ['industry' => $category->slug]),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }
}
