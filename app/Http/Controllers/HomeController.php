<?php

namespace App\Http\Controllers;

use App\Enums\BillingPeriod;
use App\Enums\CategoryType;
use App\Enums\OrderPlan;
use App\Http\Resources\ComponentCardResource;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home page (SPEC §15.1): hero, featured components, industries grid,
 * how-it-works, pricing teaser, latest drops and blog teaser.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $featured = Component::query()
            ->published()
            ->with('usageCategory')
            ->orderByDesc('preview_built_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $latestDrops = Component::query()
            ->published()
            ->with('usageCategory')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $industries = Category::query()
            ->where('type', CategoryType::Industry)
            ->visible()
            ->withCount(['components' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->limit(12)
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

        $pricing = collect([OrderPlan::Starter, OrderPlan::Pro])
            ->mapWithKeys(function (OrderPlan $plan): array {
                $price = $plan->price(BillingPeriod::Monthly);

                return [$plan->value => $price === null ? null : [
                    'amount' => $price->amount,
                    'currency' => $price->currency,
                ]];
            })
            ->all();

        $posts = Blog::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Blog $post): array => [
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'published_at' => $post->published_at?->toDateString(),
            ])
            ->all();

        return Inertia::render('home', [
            'featuredComponents' => ComponentCardResource::collection($featured)->resolve(request()),
            'industries' => $industries,
            'pricing' => $pricing,
            'latestComponents' => ComponentCardResource::collection($latestDrops)->resolve(request()),
            'posts' => $posts,
            'meta' => [
                'title' => 'FrontendParts — Production-ready React & Vue sections, recreated from the best sites',
                'description' => 'A paid-quality catalog of website sections, blocks and pages for React and Vue — with live previews, clean code and one-click copy.',
                'canonical' => URL::to('/'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }
}
