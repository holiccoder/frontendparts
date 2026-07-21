<?php

namespace Tests\Feature\Docs;

use App\Models\DocsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Docs search through Scout (Phase 5.1 — SPEC §13.2 "search (basic at
 * launch → Meilisearch at P3)"): markdown pages sync from disk into the
 * docs_pages table (fingerprint-guarded), and matching runs through the
 * configured Scout engine — the collection engine here, Meilisearch in
 * production.
 */
class DocsScoutSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing/docs-scout-content');

        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/guide');
        File::ensureDirectoryExists($this->contentPath.'/api');

        file_put_contents($this->contentPath.'/guide/one.md', <<<'MD'
---
title: Page One
description: First fixture page.
---

# Page One

Intro paragraph about cats.
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

    public function test_search_syncs_markdown_pages_into_the_scout_table()
    {
        $this->get('/docs/search?q=cats')->assertOk();

        $this->assertDatabaseCount('docs_pages', 4);
        $this->assertDatabaseHas('docs_pages', [
            'section' => 'guide',
            'page' => 'one',
            'title' => 'Page One',
            'description' => 'First fixture page.',
        ]);
    }

    public function test_docs_pages_match_through_the_scout_engine()
    {
        $props = $this->get('/docs/search?q=cats')->assertOk()->viewData('page')['props'];

        $pages = array_column($props['results'], 'page');

        $this->assertContains('one', $pages);
        $this->assertContains('three', $pages);
        $this->assertNotContains('two', $pages);

        // Direct Scout query over the synced rows — same matches.
        $hits = DocsPage::search('cats')->get();

        $this->assertEqualsCanonicalizing(['one', 'three'], $hits->pluck('page')->all());
    }

    public function test_sync_prunes_pages_removed_from_the_nav()
    {
        $this->get('/docs/search?q=cats')->assertOk();
        $this->assertDatabaseHas('docs_pages', ['section' => 'guide', 'page' => 'three']);

        $nav = config('docs_nav');
        unset($nav['guide']['pages']['three']);
        config()->set('docs_nav', $nav);

        $this->get('/docs/search?q=cats')->assertOk();

        $this->assertDatabaseMissing('docs_pages', ['section' => 'guide', 'page' => 'three']);
        $this->assertDatabaseCount('docs_pages', 3);
    }

    public function test_sync_is_fingerprint_guarded()
    {
        $this->get('/docs/search?q=cats')->assertOk();
        $this->assertDatabaseCount('docs_pages', 4);

        DocsPage::query()->where('page', 'three')->delete();

        // Content unchanged → the sync does not re-run → the row stays gone.
        $this->get('/docs/search?q=cats')->assertOk();

        $this->assertDatabaseMissing('docs_pages', ['section' => 'guide', 'page' => 'three']);
        $this->assertDatabaseCount('docs_pages', 3);
    }

    public function test_sync_picks_up_changed_content()
    {
        $this->get('/docs/search?q=cats')->assertOk();

        file_put_contents($this->contentPath.'/guide/two.md', "# Beta Guide\n\nNow about cats too.\n");

        $props = $this->get('/docs/search?q=cats')->assertOk()->viewData('page')['props'];

        $this->assertContains('two', array_column($props['results'], 'page'));
    }
}
