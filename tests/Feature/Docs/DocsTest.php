<?php

namespace Tests\Feature\Docs;

use App\Services\Docs\DocsRepository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Markdown docs renderer (SPEC §13.2): fixture content + fixture nav config
 * so renderer behavior is tested independently of the launch content batch.
 */
class DocsTest extends TestCase
{
    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing/docs-content');

        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/guide');
        File::ensureDirectoryExists($this->contentPath.'/api');

        file_put_contents($this->contentPath.'/guide/one.md', <<<'MD'
---
title: Page One
description: First fixture page.
---

# Page One

Intro paragraph with **bold** text.

## Alpha section

Some text.

### Alpha detail

More text.

## Beta section

```php
echo "not a heading";
```
MD);

        file_put_contents($this->contentPath.'/guide/two.md', "# Page Two\n\n## Middle\n");
        file_put_contents($this->contentPath.'/guide/three.md', "# Page Three\n");
        file_put_contents($this->contentPath.'/api/index.md', "# API Overview\n");

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

    public function test_renders_markdown_file_as_ssr_page()
    {
        $response = $this->get('/docs/guide/one');

        $response->assertOk()->assertHeaderMissing('X-SSR-Skipped');

        $page = $response->viewData('page');
        $props = $page['props'];

        $this->assertSame('docs/show', $page['component']);

        // Front-matter wins for title + description.
        $this->assertSame('Page One', $props['doc']['title']);
        $this->assertSame('First fixture page.', $props['doc']['description']);
        $this->assertSame('guide', $props['doc']['section']);
        $this->assertSame('one', $props['doc']['page']);

        // Markdown is rendered to HTML, code blocks included.
        $this->assertStringContainsString('<strong>bold</strong>', $props['doc']['html']);
        $this->assertStringContainsString('<pre><code class="language-php">', $props['doc']['html']);

        // Headings carry slug anchors and feed the right-hand TOC (h2/h3).
        $this->assertStringContainsString('<h2 id="alpha-section">', $props['doc']['html']);
        $this->assertSame(
            [
                ['level' => 2, 'id' => 'alpha-section', 'text' => 'Alpha section'],
                ['level' => 3, 'id' => 'alpha-detail', 'text' => 'Alpha detail'],
                ['level' => 2, 'id' => 'beta-section', 'text' => 'Beta section'],
            ],
            $props['doc']['toc'],
        );

        // SEO: unique title + description via meta props.
        $this->assertStringContainsString('Page One', $props['meta']['title']);
        $this->assertStringContainsString('First fixture page.', $props['meta']['description']);
        $this->assertSame(route('docs.show', ['section' => 'guide', 'page' => 'one']), $props['meta']['canonical']);
    }

    public function test_title_falls_back_to_first_h1_without_front_matter()
    {
        $props = $this->get('/docs/guide/two')->assertOk()->viewData('page')['props'];

        $this->assertSame('Page Two', $props['doc']['title']);
        $this->assertSame('', $props['doc']['description']);
        $this->assertStringContainsString('Page Two', $props['meta']['title']);
    }

    public function test_unknown_page_404()
    {
        $this->get('/docs/guide/missing')->assertNotFound();
        $this->get('/docs/unknown/one')->assertNotFound();
    }

    public function test_sidebar_tree_props()
    {
        $props = $this->get('/docs/guide/two')->assertOk()->viewData('page')['props'];

        $nav = $props['nav'];

        $this->assertCount(2, $nav);

        $this->assertSame('guide', $nav[0]['key']);
        $this->assertSame('Guide', $nav[0]['title']);
        $this->assertTrue($nav[0]['active']);
        $this->assertSame(route('docs.show', ['section' => 'guide', 'page' => 'one']), $nav[0]['url']);

        $this->assertSame(['one', 'two', 'three'], array_column($nav[0]['pages'], 'key'));
        $this->assertFalse($nav[0]['pages'][0]['active']);
        $this->assertTrue($nav[0]['pages'][1]['active']);
        $this->assertSame(
            route('docs.show', ['section' => 'guide', 'page' => 'two']),
            $nav[0]['pages'][1]['url'],
        );

        $this->assertSame('api', $nav[1]['key']);
        $this->assertFalse($nav[1]['active']);
        $this->assertFalse($nav[1]['pages'][0]['active']);
    }

    public function test_prev_next_links()
    {
        // Middle of a section: prev = page 1, next = page 3.
        $props = $this->get('/docs/guide/two')->assertOk()->viewData('page')['props'];

        $this->assertSame(
            ['title' => 'Page One', 'url' => route('docs.show', ['section' => 'guide', 'page' => 'one'])],
            $props['pagination']['prev'],
        );
        $this->assertSame(
            ['title' => 'Page Three', 'url' => route('docs.show', ['section' => 'guide', 'page' => 'three'])],
            $props['pagination']['next'],
        );

        // First page overall has no prev; last page of a section crosses into the next section.
        $first = $this->get('/docs/guide/one')->assertOk()->viewData('page')['props'];
        $this->assertNull($first['pagination']['prev']);
        $this->assertSame('Page Two', $first['pagination']['next']['title']);

        $crossing = $this->get('/docs/guide/three')->assertOk()->viewData('page')['props'];
        $this->assertSame(
            ['title' => 'API Overview', 'url' => route('docs.show', ['section' => 'api', 'page' => 'index'])],
            $crossing['pagination']['next'],
        );

        $last = $this->get('/docs/api/index')->assertOk()->viewData('page')['props'];
        $this->assertNull($last['pagination']['next']);
        $this->assertSame('Page Three', $last['pagination']['prev']['title']);
    }

    public function test_path_traversal_rejected()
    {
        // Encoded traversal and non-kebab keys never reach the filesystem.
        $this->get('/docs/..%2f..%2fetc')->assertNotFound();
        $this->get('/docs/guide/..%2Fone')->assertNotFound();
        $this->get('/docs/guide/Page_One')->assertNotFound();

        $docs = app(DocsRepository::class);

        $this->assertNull($docs->find('../guide', 'one'));
        $this->assertNull($docs->find('guide', '..%2fone'));
        $this->assertNull($docs->find('guide', '../../etc/passwd'));
        $this->assertNull($docs->find('', ''));
    }

    public function test_docs_index_redirects_to_first_configured_page()
    {
        $this->get('/docs')->assertRedirect(route('docs.show', ['section' => 'guide', 'page' => 'one']));
    }
}
