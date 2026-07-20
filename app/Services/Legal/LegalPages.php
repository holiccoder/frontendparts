<?php

namespace App\Services\Legal;

use App\Support\Settings;
use DOMDocument;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Legal page store (SPEC §15.7): the seven must-have pages live as markdown
 * under `resources/legal/{slug}.md` — code-first and PR-reviewable like the
 * docs store (§13.2) — rendered SSR by the LegalController. Each file has
 * minimal front matter (title/description/updated); `{{ refund_window_days }}`
 * in the body is interpolated from platform settings so legal copy never
 * hardcodes a knob an admin can turn (§8.7).
 *
 * Rendering mirrors DocsRepository/PostContent: league/commonmark with
 * escaped raw HTML and invisible heading anchors; the h2/h3 TOC is read back
 * from the rendered HTML so ids always match.
 */
class LegalPages
{
    /**
     * Page registry in footer/sitemap order: slug => SEO fallback meta.
     * Front matter in the markdown file wins when present.
     *
     * @var array<string, array{title: string, description: string}>
     */
    private const PAGES = [
        'terms' => [
            'title' => 'Terms of Service',
            'description' => 'The terms that govern your FrontendParts account — subscriptions, acceptable use, termination and how the component license fits in.',
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'description' => 'What data FrontendParts collects, why, and your rights under GDPR, CCPA/CPRA and PIPL — including accounts, GitHub tokens, analytics and Paddle as merchant of record.',
        ],
        'license' => [
            'title' => 'Component License',
            'description' => 'One license for every paid plan — unlimited commercial and client work, no redistribution or resale, code you download stays yours after a lapse, single seat.',
        ],
        'refund-policy' => [
            'title' => 'Refund Policy',
            'description' => 'Every FrontendParts purchase is covered by a 14-day, no-questions-asked refund window — how to request a refund and what happens afterwards.',
        ],
        'cookie-policy' => [
            'title' => 'Cookie Policy',
            'description' => 'The cookies and browser storage FrontendParts uses — strictly necessary session and security cookies plus optional interface preferences. No advertising or third-party analytics cookies.',
        ],
        'copyright' => [
            'title' => 'Copyright & Takedown Policy',
            'description' => 'How FrontendParts sources components — recreated layouts, always attributed, never copied — and how rights holders can request a takedown with a defined response SLA.',
        ],
        'legal-notice' => [
            'title' => 'Legal Notice',
            'description' => 'Operator identity and contact details for FrontendParts, and how Paddle appears as merchant of record on your invoices.',
        ],
    ];

    private MarkdownConverter $converter;

    public function __construct(private readonly Settings $settings)
    {
        $environment = new Environment([
            // Escape (not strip) raw HTML so prose survives verbatim.
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
     * Registered page slugs in display order.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys(self::PAGES);
    }

    /**
     * Footer navigation entries for every legal page (SPEC §15.7 — the
     * footer links all seven from every public page). Shared into Inertia
     * so the public layout renders the registry verbatim.
     *
     * @return list<array{title: string, url: string}>
     */
    public function navigation(): array
    {
        return array_map(
            fn (string $slug): array => [
                'title' => self::PAGES[$slug]['title'],
                'url' => route("legal.{$slug}"),
            ],
            $this->slugs(),
        );
    }

    /**
     * Resolve one legal page to its rendered payload, or null when the slug
     * is not registered or the markdown file is missing — the controller
     * turns that into a 404.
     *
     * @return array{slug: string, title: string, description: string, updated: string|null, url: string, html: string, toc: list<array{level: int, id: string, text: string}>}|null
     */
    public function find(string $slug): ?array
    {
        if (! array_key_exists($slug, self::PAGES)) {
            return null;
        }

        $path = base_path("resources/legal/{$slug}.md");

        if (! is_file($path)) {
            return null;
        }

        [$frontMatter, $body] = $this->extractFrontMatter((string) file_get_contents($path));

        $html = (string) $this->converter->convert($this->interpolate($body));

        return [
            'slug' => $slug,
            'title' => $frontMatter['title'] ?? self::PAGES[$slug]['title'],
            'description' => $frontMatter['description'] ?? self::PAGES[$slug]['description'],
            'updated' => $frontMatter['updated'] ?? null,
            'url' => route("legal.{$slug}"),
            'html' => $html,
            'toc' => $this->toc($html),
        ];
    }

    /**
     * Settings-driven tokens in legal copy (`{{ refund_window_days }}`), so
     * an admin change to the refund window (SPEC §8.7) updates the published
     * refund policy and terms without a content edit.
     */
    private function interpolate(string $markdown): string
    {
        return str_replace(
            '{{ refund_window_days }}',
            (string) $this->settings->get('billing.refund_window_days'),
            $markdown,
        );
    }

    /**
     * Minimal front matter: an optional leading `---` block of simple
     * `key: value` lines (no YAML package). Only `title`, `description`
     * and `updated` are consumed.
     *
     * @return array{0: array{title?: string, description?: string, updated?: string}, 1: string}
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
