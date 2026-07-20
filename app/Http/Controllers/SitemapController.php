<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Category;
use App\Models\Component;
use App\Services\Docs\DocsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

/**
 * `/sitemap.xml` (SPEC §10.2, §15.6): static pages plus DB-driven
 * taxonomy and published component URLs, every configured docs page, and
 * the live blog URLs (index, categories, posts).
 */
class SitemapController extends Controller
{
    public function __construct(private readonly DocsRepository $docs) {}

    public function __invoke(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('components.index'), 'lastmod' => null],
            ['loc' => route('industries.index'), 'lastmod' => null],
            ['loc' => route('pricing'), 'lastmod' => null],
        ];

        foreach ($this->docs->allPages() as $docsPage) {
            $urls[] = ['loc' => route('docs.show', $docsPage), 'lastmod' => null];
        }

        $usages = Category::query()
            ->where('type', CategoryType::Usage)
            ->whereHas('usageComponents', fn (Builder $query) => $query->published())
            ->orderBy('sort_order')
            ->get();

        foreach ($usages as $usage) {
            $urls[] = [
                'loc' => route('components.usage', ['usage' => $usage->slug]),
                'lastmod' => null,
            ];
        }

        $industries = Category::query()
            ->where('type', CategoryType::Industry)
            ->whereHas('components', fn (Builder $query) => $query->published())
            ->orderBy('sort_order')
            ->get();

        foreach ($industries as $industry) {
            $urls[] = [
                'loc' => route('industries.show', ['industry' => $industry->slug]),
                'lastmod' => null,
            ];
        }

        $components = Component::query()
            ->published()
            ->with('usageCategory')
            ->orderBy('slug')
            ->get();

        foreach ($components as $component) {
            $urls[] = [
                'loc' => $component->publicUrl(),
                'lastmod' => $component->updated_at?->toDateString(),
            ];
        }

        $urls[] = ['loc' => route('blog.index'), 'lastmod' => null];

        $blogCategories = BlogCategory::query()
            ->whereHas('posts', fn (Builder $query) => $query->published())
            ->orderBy('name')
            ->get();

        foreach ($blogCategories as $blogCategory) {
            $urls[] = [
                'loc' => route('blog.category', ['slug' => $blogCategory->slug]),
                'lastmod' => null,
            ];
        }

        $posts = Blog::query()
            ->published()
            ->orderByDesc('published_at')
            ->get();

        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $post->publicUrl(),
                'lastmod' => $post->updated_at?->toDateString(),
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
