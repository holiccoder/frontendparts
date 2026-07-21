<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\ComponentLevel;
use App\Http\Resources\ComponentCardResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Category;
use App\Services\Ai\AiGateway;
use App\Services\Ai\SearchIntent;
use App\Services\Catalog\SiteSearch;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/search?q=` (SPEC §15.1, FR-1.3): public SSR site search over the
 * component catalog and the blog, grouped Components / Blog. Results are
 * deep-linkable via the query string; the page itself is noindex like any
 * search-results page. Matching lives in SiteSearch so the Phase-3
 * Meilisearch upgrade swaps one class.
 *
 * AI-assisted mode (task 5.4, features.ai_search): submitting the form with
 * the "AI-assisted" checkbox adds `ai=1`; the natural-language query goes
 * through the AiGateway once per submit (rate-limited per IP) and the
 * returned intent scopes the component results, labeled as AI-assisted in
 * the UI. Flag off, key missing, rate limit hit, or AI failure all degrade
 * to plain results — search users never see an AI error.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly SiteSearch $search,
        private readonly AiGateway $ai,
    ) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ai' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $aiRequested = $request->boolean('ai');

        $ai = [
            'available' => (bool) app(Settings::class)->get('features.ai_search'),
            'requested' => $aiRequested,
            'active' => false,
            'limited' => false,
            'keywords' => null,
            'filters' => [],
        ];

        $intent = $ai['available'] && $aiRequested && $query !== ''
            ? $this->intent($request, $query, $ai)
            : null;

        if ($intent !== null) {
            $components = $this->search->componentsForIntent($intent);
            $ai['active'] = true;
            $ai['keywords'] = $intent->keywords;
            $ai['filters'] = $this->filterLabels($intent);
        } else {
            $components = $query === '' ? collect() : $this->search->components($query);
        }

        $posts = $query === '' ? collect() : $this->search->posts($query);

        return Inertia::render('search', [
            'query' => $query,
            'components' => ComponentCardResource::collection($components)->resolve($request),
            'posts' => $posts->map(fn (Blog $post): array => $this->card($post))->values()->all(),
            'ai' => $ai,
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
     * Resolve the AI intent for one AI-assisted submit. The per-IP rate
     * limiter guards the paid provider call; over-limit requests fall back
     * to plain results with `ai.limited` set for the UI.
     *
     * @param  array{available: bool, requested: bool, active: bool, limited: bool, keywords: string|null, filters: list<string>}  $ai
     */
    private function intent(Request $request, string $query, array &$ai): ?SearchIntent
    {
        $allowed = RateLimiter::attempt(
            'ai-search:'.($request->ip() ?? 'unknown'),
            (int) config('ai.features.search_rate_limit', 10),
            fn (): bool => true,
            decaySeconds: 60,
        );

        if (! $allowed) {
            $ai['limited'] = true;

            return null;
        }

        return $this->ai->interpretSearchQuery($query, $this->taxonomy());
    }

    /**
     * The taxonomy advertised to the model: live usage + industry category
     * slugs and the four component levels.
     *
     * @return array{usage: list<string>, industries: list<string>, levels: list<string>}
     */
    private function taxonomy(): array
    {
        return [
            'usage' => Category::query()->where('type', CategoryType::Usage->value)->orderBy('sort_order')->pluck('slug')->all(),
            'industries' => Category::query()->where('type', CategoryType::Industry->value)->orderBy('sort_order')->pluck('slug')->all(),
            'levels' => array_map(fn (ComponentLevel $level): string => $level->value, ComponentLevel::cases()),
        ];
    }

    /**
     * Human-readable labels for the applied filters, for the "AI-assisted
     * results" strip on the page.
     *
     * @return list<string>
     */
    private function filterLabels(SearchIntent $intent): array
    {
        $names = Category::query()
            ->whereIn('slug', [...$intent->usageCategories, ...$intent->industries])
            ->pluck('name')
            ->all();

        foreach ($intent->levels as $level) {
            $names[] = Str::headline($level);
        }

        return $names;
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
