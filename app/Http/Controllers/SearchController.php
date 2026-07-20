<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComponentCardResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\Catalog\SiteSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/search?q=` (SPEC §15.1, FR-1.3): public SSR site search over the
 * component catalog and the blog, grouped Components / Blog. Results are
 * deep-linkable via the query string; the page itself is noindex like any
 * search-results page. Matching lives in SiteSearch so the Phase-3
 * Meilisearch upgrade swaps one class.
 */
class SearchController extends Controller
{
    public function __construct(private readonly SiteSearch $search) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        $components = $query === '' ? collect() : $this->search->components($query);
        $posts = $query === '' ? collect() : $this->search->posts($query);

        return Inertia::render('search', [
            'query' => $query,
            'components' => ComponentCardResource::collection($components)->resolve($request),
            'posts' => $posts->map(fn (Blog $post): array => $this->card($post))->values()->all(),
            'meta' => [
                'title' => ($query === '' ? 'Search' : "Search: {$query}").' — FrontendParts',
                'description' => 'Search the FrontendParts component catalog and blog — sections, blocks and pages for React and Vue.',
                'canonical' => URL::to('/search'),
                'og_image' => URL::to('/brand/logo.png'),
                // Search results pages are excluded from indexing (SPEC §15.1);
                // the legal pages stay indexed — that is 2.11's concern.
                'robots' => 'noindex',
            ],
        ]);
    }

    /**
     * Compact blog card — same shape the blog index renders (BlogPostCard).
     *
     * @return array<string, mixed>
     */
    private function card(Blog $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'url' => $post->publicUrl(),
            'published_at' => $post->published_at?->toDateString(),
            'reading_time' => $post->reading_time,
            'featured_image' => $post->featured_image === null
                ? null
                : Storage::disk('public')->url($post->featured_image),
            'categories' => $post->categories->map(fn (BlogCategory $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'url' => route('blog.category', ['slug' => $category->slug]),
            ])->values()->all(),
        ];
    }
}
