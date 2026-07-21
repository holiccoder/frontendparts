<?php

namespace App\Services\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Generates a visual variation of an existing library component (task 5.4,
 * features.ai_variants): same props/params API and sample-data shape, new
 * styling. The model answers with a variant name, a one-line change summary
 * for the admin review notification, and full replacement entry sources for
 * both frameworks. Nothing it returns is published — the variant lands as
 * an in-review component behind the normal human QA gate.
 */
class ComponentVariantAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public readonly string $componentName,
        public readonly string $level,
        public readonly string $paramsSchema,
    ) {}

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You are a senior UI engineer creating a style variation of an existing FrontendParts component.',
            "Component: \"{$this->componentName}\" (level: {$this->level}). The prompt carries its current React (TSX) and Vue (SFC) entry sources.",
            '',
            'Produce a variant with a clearly different visual style (layout, spacing, color usage, typography, borders, shadows) while keeping EXACTLY the same public API:',
            '- same exported props / defineProps names and types',
            '- same default slot structure and data binding',
            '- same params.json contract, which is:',
            $this->paramsSchema,
            '',
            'Rules:',
            '- Keep both implementations framework-idiomatic: React as a single default-exported TSX function component, Vue as a single <script setup lang="ts"> SFC.',
            '- Do not add new runtime dependencies; inline styles or utility classes already used in the source only.',
            '- name: a short display name for the variant (the original name plus a style qualifier, e.g. "Pricing Card Minimal").',
            '- summary: one sentence describing what visually changed, for the admin reviewing the variant.',
            '- react_code / vue_code: the complete replacement entry source for each framework, with no annotation docblock (the pipeline adds it).',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Display name of the variant.')
                ->required(),
            'summary' => $schema->string()
                ->description('One sentence describing the visual change.')
                ->required(),
            'react_code' => $schema->string()
                ->description('Complete React (TSX) entry source of the variant.')
                ->required(),
            'vue_code' => $schema->string()
                ->description('Complete Vue (SFC) entry source of the variant.')
                ->required(),
        ];
    }
}
