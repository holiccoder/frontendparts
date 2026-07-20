<?php

namespace Tests\Feature\Docs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Launch content (SPEC §13.2): every page configured in
 * `config/docs_nav.php` really renders, is listed in the sitemap, and only
 * links to docs pages that exist. The per-page sweep is parametrized over
 * the nav tree so future pages are auto-covered, and the reverse check
 * guarantees every markdown file under docs/content is declared in the
 * nav, so content and navigation can never drift apart.
 */
class DocsContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_launch_pages_return_200()
    {
        $pages = $this->configuredPages();

        $this->assertNotEmpty($pages);

        foreach ($pages as $docsPage) {
            $response = $this->get(route('docs.show', $docsPage));

            $response->assertOk();

            $page = $response->viewData('page');
            $props = $page['props'];

            $this->assertSame('docs/show', $page['component']);
            $this->assertSame($docsPage['section'], $props['doc']['section']);
            $this->assertSame($docsPage['page'], $props['doc']['page']);
            $this->assertNotSame('', trim((string) $props['doc']['html']));
            $this->assertNotSame('', trim((string) $props['doc']['title']));
            $this->assertNotSame('', trim((string) $props['meta']['description']), "Missing meta description on {$docsPage['section']}/{$docsPage['page']}");
        }
    }

    public function test_docs_included_in_sitemap()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();

        $content = $response->getContent();

        foreach ($this->configuredPages() as $docsPage) {
            $this->assertStringContainsString(
                '<loc>'.route('docs.show', $docsPage).'</loc>',
                $content,
            );
        }
    }

    public function test_no_broken_internal_docs_links()
    {
        $hrefs = [];

        foreach ($this->configuredPages() as $docsPage) {
            $response = $this->get(route('docs.show', $docsPage))->assertOk();

            $html = (string) $response->viewData('page')['props']['doc']['html'];

            preg_match_all('#href="(/docs/[^"]*)"#', $html, $matches);

            foreach ($matches[1] as $href) {
                $hrefs[$href] = true;
            }
        }

        $this->assertNotEmpty($hrefs, 'Launch docs should cross-link to each other.');

        foreach (array_keys($hrefs) as $href) {
            $path = (string) parse_url($href, PHP_URL_PATH);
            $target = trim(substr($path, strlen('/docs/')), '/');
            [$section, $page] = array_pad(explode('/', $target), 2, null);

            // The link must point at a page configured in the nav…
            $this->assertNotNull($page, "Link {$href} does not target a docs page.");
            $this->assertArrayHasKey($section, config('docs_nav'), "Unknown docs section in link {$href}.");
            $this->assertArrayHasKey($page, config("docs_nav.{$section}.pages"), "Unknown docs page in link {$href}.");

            // …and it must resolve.
            $this->get($path)->assertOk();
        }
    }

    /**
     * The real nav tree as `{section}/{page}` cases, read straight from the
     * config file (data providers run before the app boots).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function docsNavPages(): array
    {
        /** @var array<string, array{title: string, pages: array<string, string>}> $nav */
        $nav = require dirname(__DIR__, 3).'/config/docs_nav.php';

        $pages = [];

        foreach ($nav as $section => $definition) {
            foreach ($definition['pages'] as $page => $title) {
                $pages["{$section}/{$page}"] = [(string) $section, (string) $page];
            }
        }

        return $pages;
    }

    #[DataProvider('docsNavPages')]
    public function test_all_sections_return_200(string $section, string $page)
    {
        $this->assertFileExists(base_path("docs/content/{$section}/{$page}.md"));

        $this->get(route('docs.show', ['section' => $section, 'page' => $page]))
            ->assertOk()
            ->assertHeaderMissing('X-SSR-Skipped');
    }

    public function test_every_content_file_is_declared_in_the_nav()
    {
        /** @var array<string, array{title: string, pages: array<string, string>}> $nav */
        $nav = require base_path('config/docs_nav.php');

        $files = File::glob(base_path('docs/content/*/*.md'));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $page = basename($file, '.md');
            $section = basename(dirname($file));

            $this->assertArrayHasKey(
                $page,
                $nav[$section]['pages'] ?? [],
                "docs/content/{$section}/{$page}.md is not declared in config/docs_nav.php",
            );
        }
    }

    /**
     * Every {section, page} pair declared in the docs nav config.
     *
     * @return list<array{section: string, page: string}>
     */
    private function configuredPages(): array
    {
        $pages = [];

        foreach ((array) config('docs_nav') as $section => $definition) {
            foreach (array_keys((array) ($definition['pages'] ?? [])) as $page) {
                $pages[] = ['section' => (string) $section, 'page' => (string) $page];
            }
        }

        return $pages;
    }
}
