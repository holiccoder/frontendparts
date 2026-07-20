<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComponentCardResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\Blog\PostContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public blog (SPEC §13.1, §15.1): `/blog` index, `/blog/{slug}` article
 * and `/blog/category/{slug}` category pages — all SSR and SEO-indexed.
 * Only live posts are reachable: drafts and scheduled posts (future
 * `published_at`) 404 on direct access.
 */
class BlogController extends Controller
{
    public function __construct(private readonly PostContent $content) {}

    public function index(): Response
    {
        $posts = Blog::query()
            ->published()
            ->with('categories')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Blog $post): array => $this->card($post));

        return Inertia::render('blog/index', [
            'posts' => $posts,
            'categories' => $this->categoryList(),
            'meta' => [
                'title' => 'Blog — FrontendParts',
                'description' => 'Design teardowns and industry × usage keyword articles, recreated as production-ready React & Vue components.',
                'canonical' => route('blog.index'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    public function category(string $slug): Response
    {
        $category = BlogCategory::query()->where('slug', $slug)->firstOrFail();

        $posts = $category->posts()
            ->published()
            ->with('categories')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Blog $post): array => $this->card($post));

        return Inertia::render('blog/category', [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'posts' => $posts,
            'categories' => $this->categoryList(),
            'meta' => [
                'title' => "{$category->name} — FrontendParts Blog",
                'description' => $category->description
                    ?: "Articles about {$category->name} on the FrontendParts blog.",
                'canonical' => route('blog.category', ['slug' => $category->slug]),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $post = Blog::query()
            ->published()
            ->where('slug', $slug)
            ->with(['author', 'categories', 'tags'])
            ->firstOrFail();

        ['html' => $html, 'toc' => $toc] = $this->content->render($post->body);

        $relatedComponents = $post->relatedComponents()
            ->published()
            ->with('usageCategory')
            ->limit(6)
            ->get();

        $description = $post->meta_description
            ?: ($post->excerpt ?: Str::limit(trim(strip_tags($post->body)), 160));

        $ogImage = $this->featuredImageUrl($post) ?? URL::to('/brand/logo.png');
        $canonical = $post->publicUrl();

        return Inertia::render('blog/show', [
            'post' => [
                ...$this->card($post),
                'body_html' => $html,
                'toc' => $toc,
                'author' => $post->author?->name,
                'tags' => $post->tags->map(fn ($tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])->values()->all(),
                'published_at_iso' => $post->published_at?->toIso8601String(),
                'updated_at_iso' => $post->updated_at?->toIso8601String(),
            ],
            'relatedPosts' => $this->relatedPosts($post),
            'relatedComponents' => ComponentCardResource::collection($relatedComponents)->resolve(request()),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $description,
                'image' => $ogImage,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->author?->name ?? 'FrontendParts',
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'FrontendParts',
                ],
                'mainEntityOfPage' => $canonical,
            ],
            'meta' => [
                'title' => $post->meta_title ?: "{$post->title} — FrontendParts Blog",
                'description' => $description,
                'canonical' => $canonical,
                'og_image' => $ogImage,
                'og_type' => 'article',
            ],
        ]);
    }

    /**
     * Card shape shared by the index, category and related-posts lists.
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
            'featured_image' => $this->featuredImageUrl($post),
            'categories' => $post->categories->map(fn (BlogCategory $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'url' => route('blog.category', ['slug' => $category->slug]),
            ])->values()->all(),
        ];
    }

    /**
     * Related posts from the same categories; falls back to the latest
     * other posts when the post sits in an otherwise empty category.
     *
     * @return list<array<string, mixed>>
     */
    private function relatedPosts(Blog $post): array
    {
        $categoryIds = $post->categories->modelKeys();

        $related = Blog::query()
            ->published()
            ->whereKeyNot($post->id)
            ->when(
                $categoryIds !== [],
                fn (Builder $query) => $query->whereHas(
                    'categories',
                    fn (Builder $q) => $q->whereIn('blog_categories.id', $categoryIds),
                ),
            )
            ->with('categories')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        if ($related->isEmpty()) {
            $related = Blog::query()
                ->published()
                ->whereKeyNot($post->id)
                ->with('categories')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();
        }

        return $related->map(fn (Blog $relatedPost): array => $this->card($relatedPost))->values()->all();
    }

    /**
     * Categories that hold at least one live post, for the index sidebar.
     *
     * @return list<array<string, mixed>>
     */
    private function categoryList(): array
    {
        return BlogCategory::query()
            ->whereHas('posts', fn (Builder $query) => $query->published())
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->orderBy('name')
            ->get()
            ->map(fn (BlogCategory $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'posts_count' => $category->posts_count,
                'url' => route('blog.category', ['slug' => $category->slug]),
            ])
            ->values()
            ->all();
    }

    private function featuredImageUrl(Blog $post): ?string
    {
        if ($post->featured_image === null) {
            return null;
        }

        return Storage::disk('public')->url($post->featured_image);
    }
}
