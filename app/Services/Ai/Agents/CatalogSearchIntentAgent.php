<?php

namespace App\Services\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Maps a natural-language catalog query onto the FrontendParts taxonomy
 * (task 5.4, features.ai_search). The live usage categories, industries and
 * component levels are baked into the instructions, and the structured
 * schema constrains the answer to those slugs; the gateway still
 * re-validates everything before scoping a query. Prompted once per
 * AI-assisted search form submit — never per keystroke.
 */
class CatalogSearchIntentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array{usage: list<string>, industries: list<string>, levels: list<string>}  $taxonomy
     */
    public function __construct(public readonly array $taxonomy) {}

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You map natural-language searches onto the FrontendParts component catalog taxonomy.',
            'FrontendParts sells production-ready UI components at four levels: element, block, section, page.',
            'Given the user query, answer with refined keywords and the taxonomy filters that best match the intent.',
            '',
            'Rules:',
            '- keywords: a short lowercase phrase (1-5 words) to match component names, slugs and tags. Strip filler ("show me", "I need", "for my client") and keep the subject ("stats", "pricing table"). Never return an empty string — fall back to the most specific noun phrase in the query.',
            '- usage_categories: only slugs from this list: ['.implode(', ', $this->taxonomy['usage']).']. Empty array when none fit.',
            '- industries: only slugs from this list: ['.implode(', ', $this->taxonomy['industries']).']. Empty array when none fit.',
            '- levels: only values from this list: ['.implode(', ', $this->taxonomy['levels']).']. Empty array when none fit.',
            '- Prefer precision: add a filter only when the query clearly implies it. A "dashboard stats" query implies usage categories like stats or dashboards, not footers.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->string()
                ->description('Refined search keywords (1-5 words), never empty.')
                ->required(),
            'usage_categories' => $this->slugList($schema, $this->taxonomy['usage'], 'usage category'),
            'industries' => $this->slugList($schema, $this->taxonomy['industries'], 'industry'),
            'levels' => $this->slugList($schema, $this->taxonomy['levels'], 'component level'),
        ];
    }

    /**
     * Array-of-slugs schema node; enum-constrained when the taxonomy list
     * is non-empty (an empty enum would be an invalid schema).
     *
     * @param  list<string>  $slugs
     */
    private function slugList(JsonSchema $schema, array $slugs, string $label): ArrayType
    {
        $items = $schema->string();

        if ($slugs !== []) {
            $items->enum($slugs);
        }

        return $schema->array()
            ->items($items)
            ->description("Slugs of matching {$label}s; empty array when none fit.")
            ->required();
    }
}
