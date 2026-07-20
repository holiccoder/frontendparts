<?php

namespace Tests\Feature\Docs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Docs search (SPEC §13.2 — basic at launch): fixture content + fixture nav
 * config so matching, ranking and snippets are tested independently of the
 * launch content batch. RefreshDatabase because every Inertia render
 * resolves the shared auth.entitlements prop from the orders/settings
 * tables.
 */
class DocsSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing/docs-search-content');

        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/guide');
        File::ensureDirectoryExists($this->contentPath.'/api');

        file_put_contents($this->contentPath.'/guide/one.md', <<<'MD'
---
title: Page One
description: First fixture page.
---

# Page One

Intro paragraph about cats, with a [kitten care](https://example.com) link.

## Alpha section

Beta lives here.

```php
echo "fence contents stay searchable";
```
MD);

        file_put_contents($this->contentPath.'/guide/two.md', "# Beta Guide\n\nSomething else entirely.\n");
        file_put_contents($this->contentPath.'/guide/three.md', "# Page Three\n\ncats and dogs\n");
        file_put_contents($this->contentPath.'/api/index.md', "# API Overview\n\nReference material.\n");

        config()->set('docs.content_path', $this->contentPath);
        config()->set('docs_nav', [
            'guide' => [
                'title' => 'Guide',
                'pages' => ['one' => 'Page One', 'two' => 'Page Two', 'three' => 'Page Three'],
            ],
            'api' => [
                'title' => 'API',
                'pages' => ['index' => 'API Overview'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);

        parent::tearDown();
    }

    public function test_search_returns_matching_pages()
    {
        $response = $this->get('/docs/search?q=cats');

        $response->assertOk()->assertHeaderMissing('X-SSR-Skipped');

        $page = $response->viewData('page');
        $props = $page['props'];

        $this->assertSame('docs/search', $page['component']);
        $this->assertSame('cats', $props['query']);

        $urls = array_column($props['results'], 'url');

        $this->assertContains(route('docs.show', ['section' => 'guide', 'page' => 'one']), $urls);
        $this->assertContains(route('docs.show', ['section' => 'guide', 'page' => 'three']), $urls);
        $this->assertNotContains(route('docs.show', ['section' => 'guide', 'page' => 'two']), $urls);

        // Results carry the resolved page title and a snippet around the hit.
        $one = collect($props['results'])->firstWhere('page', 'one');

        $this->assertSame('Page One', $one['title']);
        $this->assertSame('guide', $one['section']);
        $this->assertStringContainsStringIgnoringCase('cats', $one['snippet']);
    }

    public function test_search_ranks_title_matches_before_body_matches()
    {
        $props = $this->get('/docs/search?q=beta')->assertOk()->viewData('page')['props'];

        // "Beta Guide" (title hit) outranks "Page One" (body hit).
        $this->assertSame(
            [route('docs.show', ['section' => 'guide', 'page' => 'two']), route('docs.show', ['section' => 'guide', 'page' => 'one'])],
            array_column($props['results'], 'url'),
        );
    }

    public function test_search_matches_link_labels_and_code_contents()
    {
        $props = $this->get('/docs/search?q=kitten+care')->assertOk()->viewData('page')['props'];

        $this->assertSame(['one'], array_column($props['results'], 'page'));

        $props = $this->get('/docs/search?q=searchable')->assertOk()->viewData('page')['props'];

        $this->assertSame(['one'], array_column($props['results'], 'page'));
    }

    public function test_search_non_matching_query_returns_empty_state()
    {
        $props = $this->get('/docs/search?q=zzz-no-such-term')->assertOk()->viewData('page')['props'];

        $this->assertSame('zzz-no-such-term', $props['query']);
        $this->assertSame([], $props['results']);
    }

    public function test_search_without_query_returns_no_results()
    {
        $props = $this->get('/docs/search')->assertOk()->viewData('page')['props'];

        $this->assertSame('', $props['query']);
        $this->assertSame([], $props['results']);
    }
}
