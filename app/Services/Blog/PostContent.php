<?php

namespace App\Services\Blog;

use DOMDocument;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Blog article body renderer (SPEC §13.1): post bodies are stored as
 * markdown and rendered server-side for the SSR article page. Same
 * pipeline as the docs store — league/commonmark with heading ids and no
 * visible permalink markup — so the per-article TOC anchors always match
 * the rendered headings.
 */
class PostContent
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
     * Render one post body to HTML plus the h2/h3 table of contents.
     *
     * @return array{html: string, toc: list<array{level: int, id: string, text: string}>}
     */
    public function render(string $markdown): array
    {
        $html = (string) $this->converter->convert($markdown);

        return [
            'html' => $html,
            'toc' => $this->toc($html),
        ];
    }

    /**
     * h2/h3 entries (level, anchor id, plain text) read from the rendered
     * HTML so ids always match the heading anchors.
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
