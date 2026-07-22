<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\Docs\DocsRepository;
use App\Services\Legal\LegalPages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

/**
 * `/sitemap.xml`: the home page and pricing page, every configured docs
 * page, the live blog URLs (index, categories, posts), and the legal
 * pages (SSR + indexed like the rest of the public zone).
 */
class SitemapController extends Controller
{
    public function __construct(
        private readonly DocsRepository $docs,
        private readonly LegalPages $legal,
    ) {}

    public function __invoke(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('pricing'), 'lastmod' => null],
        ];

        foreach ($this->legal->slugs() as $slug) {
            $urls[] = ['loc' => route("legal.{$slug}"), 'lastmod' => null];
        }

        foreach ($this->docs->allPages() as $docsPage) {
            $urls[] = ['loc' => route('docs.show', $docsPage), 'lastmod' => null];
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
