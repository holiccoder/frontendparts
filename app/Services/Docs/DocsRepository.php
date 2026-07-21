<?php

namespace App\Services\Docs;

use App\Models\DocsPage;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * File-based documentation store (SPEC §13.2): markdown lives in the repo
 * under `docs/content/{section}/{page}.md` (a section may also provide an
 * `index.md`) so docs stay code-first, PR-reviewable and versioned with the
 * product — the same philosophy as the component library.
 *
 * Rendering uses league/commonmark (shipped with laravel/framework). Heading
 * permalinks run with `insert: none` so headings get stable `id` anchors
 * without visible anchor markup; the right-hand TOC is extracted from the
 * rendered HTML afterwards.
 *
 * Search runs through Laravel Scout (SPEC §13.2 — "search (basic at launch
 * → Meilisearch at P3)"): pages are synced from markdown into the
 * `docs_pages` table on demand (fingerprint-guarded, so a sync only runs
 * after the content actually changes), then matching is delegated to the
 * configured engine — `collection` locally, Meilisearch in production.
 * Ranking stays app-side so title hits outrank body hits in nav order on
 * every engine.
 */
class DocsRepository
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            // Escape (not strip) raw HTML so prose like `array<T>` survives.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'insert' => 'none',
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'min_heading_level' => 1,
                'max_heading_level' => 6,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Resolve one docs page. Returns null when the keys are not kebab-case,
     * when the resolved path escapes the content root, or when the markdown
     * file does not exist — the controller turns that into a 404.
     *
     * @return array{section: string, page: string, title: string, description: string, html: string, toc: list<array{level: int, id: string, text: string}>}|null
     */
    public function find(string $section, string $page): ?array
    {
        $path = $this->path($section, $page);

        if ($path === null) {
            return null;
        }

        $markdown = (string) file_get_contents($path);
        [$frontMatter, $body] = $this->extractFrontMatter($markdown);

        $html = (string) $this->converter->convert($body);

        return [
            'section' => $section,
            'page' => $page,
            'title' => $frontMatter['title']
                ?? $this->firstHeading($body)
                ?? config("docs_nav.{$section}.pages.{$page}")
                ?? 'Documentation',
            'description' => $frontMatter['description'] ?? '',
            'html' => $html,
            'toc' => $this->toc($html),
        ];
    }

    /**
     * The first configured page — target of the `/docs` redirect.
     *
     * @return array{section: string, page: string}|null
     */
    public function firstPage(): ?array
    {
        return $this->allPages()[0] ?? null;
    }

    /**
     * Sidebar tree for the docs layout, with URLs and active flags.
     *
     * @return list<array{key: string, title: string, url: string, active: bool, pages: list<array{key: string, title: string, url: string, active: bool}>}>
     */
    public function navTree(?string $activeSection = null, ?string $activePage = null): array
    {
        $tree = [];

        foreach ($this->sections() as $section => $definition) {
            $pages = [];

            foreach ($definition['pages'] as $page => $title) {
                $pages[] = [
                    'key' => (string) $page,
                    'title' => (string) $title,
                    'url' => route('docs.show', ['section' => $section, 'page' => $page]),
                    'active' => $section === $activeSection && $page === $activePage,
                ];
            }

            $tree[] = [
                'key' => (string) $section,
                'title' => (string) $definition['title'],
                'url' => $pages[0]['url'] ?? route('docs.index'),
                'active' => $section === $activeSection,
                'pages' => $pages,
            ];
        }

        return $tree;
    }

    /**
     * Previous/next page in the flattened nav ordering (crossing section
     * boundaries), for the footer links.
     *
     * @return array{prev: array{title: string, url: string}|null, next: array{title: string, url: string}|null}
     */
    public function neighbours(string $section, string $page): array
    {
        $flat = $this->flatten();

        $index = null;

        foreach ($flat as $i => $entry) {
            if ($entry['section'] === $section && $entry['page'] === $page) {
                $index = $i;
                break;
            }
        }

        $link = fn (?array $entry): ?array => $entry === null ? null : [
            'title' => $entry['title'],
            'url' => $entry['url'],
        ];

        return [
            'prev' => $index !== null && $index > 0 ? $link($flat[$index - 1]) : null,
            'next' => $index !== null && $index < count($flat) - 1 ? $link($flat[$index + 1]) : null,
        ];
    }

    /**
     * Every configured page — feeds the sitemap and the content test sweep.
     *
     * @return list<array{section: string, page: string}>
     */
    public function allPages(): array
    {
        return array_map(
            fn (array $entry): array => ['section' => $entry['section'], 'page' => $entry['page']],
            $this->flatten(),
        );
    }

    /**
     * Cache key of the synced content fingerprint (see syncPages()).
     */
    private const SYNC_FINGERPRINT_CACHE_KEY = 'docs.search.fingerprint';

    /**
     * Full-text search over every configured page (SPEC §13.2 — Meilisearch
     * at P3, via Laravel Scout). The markdown pages are first synced into
     * the docs_pages table, then the configured Scout engine matches
     * candidate pages: the collection engine does case-insensitive
     * substring matching over title/body/keys, Meilisearch adds all-terms
     * matching and typo tolerance. Results are re-ranked app-side — title
     * matches before body matches, nav order within a bucket — each
     * carrying a snippet around the first body hit.
     *
     * @return list<array{section: string, page: string, title: string, url: string, snippet: string}>
     */
    public function search(string $query): array
    {
        $this->syncPages();

        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            return [];
        }

        $phrase = mb_strtolower($query);
        $terms = array_values(array_filter(explode(' ', $phrase)));

        $matches = fn (string $haystack): array => [
            'phrase' => str_contains($haystack, $phrase),
            'terms' => $terms !== [] && count(array_filter($terms, fn (string $term): bool => str_contains($haystack, $term))) === count($terms),
        ];

        $navOrder = [];

        foreach ($this->flatten() as $order => $entry) {
            $navOrder[$entry['section'].'/'.$entry['page']] = $order;
        }

        $ranked = [];

        foreach (DocsPage::search($query)->get() as $docsPage) {
            $inTitle = $matches(mb_strtolower($docsPage->title));
            $inBody = $matches(mb_strtolower($docsPage->body));

            $rank = match (true) {
                $inTitle['phrase'] => 0,
                $inTitle['terms'] => 1,
                $inBody['phrase'] => 2,
                $inBody['terms'] => 3,
                // Engine-only hit (e.g. Meilisearch typo tolerance): ranked
                // last rather than dropped, so fuzzier engines stay useful.
                default => 4,
            };

            $ranked[] = [
                'rank' => $rank,
                'order' => $navOrder[$docsPage->section.'/'.$docsPage->page] ?? PHP_INT_MAX,
                'result' => [
                    'section' => $docsPage->section,
                    'page' => $docsPage->page,
                    'title' => $docsPage->title,
                    'url' => $docsPage->publicUrl(),
                    'snippet' => $this->snippet($docsPage->body, $phrase, $terms),
                ],
            ];
        }

        usort($ranked, fn (array $a, array $b): int => [$a['rank'], $a['order']] <=> [$b['rank'], $b['order']]);

        return array_values(array_map(fn (array $hit): array => $hit['result'], $ranked));
    }

    /**
     * Sync markdown pages into the docs_pages search table (SPEC §13.2):
     * upsert every configured page and prune rows whose page vanished.
     * Guarded by a content fingerprint (nav config + each file's mtime and
     * size) so the sync only runs when the content actually changed — once
     * per deploy in production, once per fixture in tests. Model events
     * push the changed rows to the configured Scout engine.
     */
    private function syncPages(): void
    {
        $entries = $this->flatten();

        $fingerprint = md5((string) json_encode([
            'content_path' => config('docs.content_path'),
            'pages' => array_map(function (array $entry): array {
                $path = $this->path($entry['section'], $entry['page']);

                return [
                    'section' => $entry['section'],
                    'page' => $entry['page'],
                    'title' => $entry['title'],
                    'mtime' => $path === null ? null : filemtime($path),
                    'size' => $path === null ? null : filesize($path),
                ];
            }, $entries),
        ]));

        if (Cache::get(self::SYNC_FINGERPRINT_CACHE_KEY) === $fingerprint) {
            return;
        }

        $keep = [];

        foreach ($entries as $entry) {
            $path = $this->path($entry['section'], $entry['page']);

            if ($path === null) {
                continue;
            }

            [$frontMatter, $body] = $this->extractFrontMatter((string) file_get_contents($path));

            $page = DocsPage::query()->updateOrCreate(
                ['section' => $entry['section'], 'page' => $entry['page']],
                [
                    'title' => $frontMatter['title'] ?? $this->firstHeading($body) ?? $entry['title'],
                    'description' => $frontMatter['description'] ?? '',
                    'body' => $this->stripMarkdown($body),
                ],
            );

            $keep[] = $page->id;
        }

        DocsPage::query()->whereNotIn('id', $keep)->get()->each->delete();

        Cache::forever(self::SYNC_FINGERPRINT_CACHE_KEY, $fingerprint);
    }

    /**
     * Nav config as `section => ['title' => …, 'pages' => [page => title]]`.
     *
     * @return array<string, array{title: string, pages: array<string, string>}>
     */
    private function sections(): array
    {
        /** @var array<string, array{title: string, pages: array<string, string>}> $sections */
        $sections = (array) config('docs_nav', []);

        return $sections;
    }

    /**
     * The nav config flattened in declaration order.
     *
     * @return list<array{section: string, page: string, title: string, url: string}>
     */
    private function flatten(): array
    {
        $flat = [];

        foreach ($this->sections() as $section => $definition) {
            foreach ($definition['pages'] as $page => $title) {
                $flat[] = [
                    'section' => (string) $section,
                    'page' => (string) $page,
                    'title' => (string) $title,
                    'url' => route('docs.show', ['section' => $section, 'page' => $page]),
                ];
            }
        }

        return $flat;
    }

    /**
     * Resolve `{section}/{page}` to a markdown file inside the content root,
     * or null. Kebab-case keys plus a realpath containment check keep the
     * lookup path-traversal safe (defense in depth behind the route regex).
     */
    private function path(string $section, string $page): ?string
    {
        if (! $this->isKebabCase($section) || ! $this->isKebabCase($page)) {
            return null;
        }

        $base = (string) config('docs.content_path', base_path('docs/content'));
        $candidate = $base.DIRECTORY_SEPARATOR.$section.DIRECTORY_SEPARATOR.$page.'.md';

        if (! is_file($candidate)) {
            return null;
        }

        $realBase = realpath($base);
        $realCandidate = realpath($candidate);

        if ($realBase === false || $realCandidate === false || ! str_starts_with($realCandidate, $realBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    private function isKebabCase(string $key): bool
    {
        return preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $key) === 1;
    }

    /**
     * Reduce markdown to searchable plain text: fence markers, emphasis,
     * heading/list/table punctuation and HTML go away; link/image syntax
     * keeps its label; code contents stay searchable.
     */
    private function stripMarkdown(string $markdown): string
    {
        $text = (string) preg_replace('/^\s*(```|~~~).*$/m', ' ', $markdown);
        $text = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = (string) preg_replace('/^\s*(?:[-*+]|\d+[.)])\s+/m', ' ', $text);
        $text = strip_tags($text);
        $text = str_replace(['`', '*', '_', '~', '#', '>', '|', ':'], ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * A short excerpt around the first body hit (the phrase when present,
     * otherwise the first term), or the opening of the page for title-only
     * matches. Ellipses mark truncation on either side.
     *
     * @param  list<string>  $terms
     */
    private function snippet(string $text, string $phrase, array $terms, int $length = 160): string
    {
        $lower = mb_strtolower($text);
        $position = mb_strpos($lower, $phrase);

        if ($position === false) {
            foreach ($terms as $term) {
                $position = mb_strpos($lower, $term);

                if ($position !== false) {
                    break;
                }
            }
        }

        if ($position === false) {
            return mb_strimwidth($text, 0, $length, '…');
        }

        $start = max(0, $position - 60);
        $excerpt = mb_substr($text, $start, $length + ($position - $start));

        return ($start > 0 ? '…' : '').$excerpt.(mb_strlen($text) > $start + mb_strlen($excerpt) ? '…' : '');
    }

    /**
     * Minimal front-matter: an optional leading `---` block of simple
     * `key: value` lines (no YAML package). Only `title` and `description`
     * are consumed.
     *
     * @return array{0: array{title?: string, description?: string}, 1: string}
     */
    private function extractFrontMatter(string $markdown): array
    {
        if (preg_match('/\A---[ \t]*\r?\n(.*?)\r?\n---[ \t]*\r?\n/s', $markdown, $matches) !== 1) {
            return [[], $markdown];
        }

        $data = [];

        foreach (preg_split('/\r?\n/', trim($matches[1])) ?: [] as $line) {
            if (preg_match('/\A([A-Za-z0-9_-]+):\s*(.*?)\s*\z/', $line, $kv) !== 1) {
                continue;
            }

            $value = $kv[2];

            if (strlen($value) >= 2 && $value[0] === $value[strlen($value) - 1] && in_array($value[0], ['"', "'"], true)) {
                $value = substr($value, 1, -1);
            }

            if ($value !== '') {
                $data[strtolower($kv[1])] = $value;
            }
        }

        return [$data, substr($markdown, strlen($matches[0]))];
    }

    /**
     * The first ATX `# Heading` outside fenced code blocks — the fallback
     * page title when front-matter has none.
     */
    private function firstHeading(string $markdown): ?string
    {
        $inFence = false;

        foreach (preg_split('/\r?\n/', $markdown) ?: [] as $line) {
            if (preg_match('/^(```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if (! $inFence && preg_match('/^#\s+(.+?)\s*$/', $line, $matches) === 1) {
                return trim($matches[1], " \t#");
            }
        }

        return null;
    }

    /**
     * h2/h3 entries (level, anchor id, plain text) for the right-hand TOC,
     * read from the rendered HTML so ids always match the permalink slugs.
     *
     * @return list<array{level: int, id: string, text: string}>
     */
    private function toc(string $html): array
    {
        if (! str_contains($html, '<h2') && ! str_contains($html, '<h3')) {
            return [];
        }

        $dom = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];

        foreach ((new DOMXPath($dom))->query('//h2|//h3') ?: [] as $heading) {
            /** @var \DOMElement $heading */
            $id = $heading->getAttribute('id');

            if ($id === '') {
                continue;
            }

            $toc[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'id' => $id,
                'text' => trim((string) $heading->textContent),
            ];
        }

        return $toc;
    }
}
