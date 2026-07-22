<?php

namespace Tests\Feature\Blog;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_and_article_ssr_200()
    {
        $post = Blog::factory()->published()->create([
            'title' => 'Ten SaaS Pricing Pages',
            'body' => "## The pattern\n\nSome intro words.\n\n### The twist\n\nMore words here.",
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/index')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Ten SaaS Pricing Pages')
                ->where('posts.data.0.url', route('blog.show', ['slug' => $post->slug]))
                // The Pagination component consumes posts.meta (last_page,
                // links) — a raw paginator would serialize flat and crash it.
                ->has('posts.meta.last_page')
                ->has('posts.meta.links')
            );

        $response = $this->get("/blog/{$post->slug}");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/show')
                ->where('post.title', 'Ten SaaS Pricing Pages')
                ->where('post.body_html', fn (string $html): bool => str_contains($html, '<h2 id="the-pattern">'))
                ->has('post.toc', 2)
                ->where('post.toc.0.id', 'the-pattern')
                ->where('post.toc.1.level', 3)
            );

        // Public SSR zone: the noindex header must never appear here.
        $this->assertNull($response->headers->get('X-Robots-Tag'));

        $this->get('/blog/category/'.BlogCategory::factory()->create(['slug' => 'design-teardowns'])->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('blog/category'));
    }

    public function test_only_published_visible()
    {
        $live = Blog::factory()->published()->create();
        $draft = Blog::factory()->draft()->create();
        $scheduled = Blog::factory()->scheduled()->create();

        // Direct access to hidden posts 404s…
        $this->get("/blog/{$draft->slug}")->assertNotFound();
        $this->get("/blog/{$scheduled->slug}")->assertNotFound();
        $this->get("/blog/{$live->slug}")->assertOk();

        // …and neither leaks into the index or the feed.
        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.slug', $live->slug)
            );

        $feed = $this->get('/blog/feed')->assertOk()->getContent();

        $this->assertStringContainsString($live->title, $feed);
        $this->assertStringNotContainsString($draft->title, $feed);
        $this->assertStringNotContainsString($scheduled->title, $feed);
    }

    public function test_article_structured_data_present()
    {
        $post = Blog::factory()->published()->create([
            'title' => 'Structured Data Post',
            'published_at' => now()->subDays(3),
        ]);

        $this->get("/blog/{$post->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jsonLd.@context', 'https://schema.org')
                ->where('jsonLd.@type', 'Article')
                ->where('jsonLd.headline', 'Structured Data Post')
                ->where('jsonLd.author.name', $post->author->name)
                ->where('jsonLd.datePublished', $post->published_at->toIso8601String())
                ->where('jsonLd.mainEntityOfPage', $post->publicUrl())
            );
    }

    public function test_rss_feed_valid_xml()
    {
        $posts = Blog::factory()->count(2)->published()->create();
        Blog::factory()->draft()->create();

        $response = $this->get('/blog/feed');

        $response->assertOk();
        $this->assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'Feed must be valid XML');
        $this->assertCount(2, $xml->channel->item);

        foreach ($xml->channel->item as $item) {
            $link = (string) $item->link;
            $this->assertStringStartsWith('http', $link, 'RSS item links must be absolute');
            $this->assertNotSame('', (string) $item->pubDate);
        }

        $this->assertStringContainsString($posts->first()->title, $response->getContent());
    }

    public function test_blog_urls_in_sitemap()
    {
        $post = Blog::factory()->published()->create();
        $hidden = Blog::factory()->scheduled()->create();

        $category = BlogCategory::factory()->create();
        $category->posts()->attach($post);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('blog.index').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.$post->publicUrl().'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('blog.category', ['slug' => $category->slug]).'</loc>', $content);
        $this->assertStringNotContainsString($hidden->slug, $content);
    }
}
