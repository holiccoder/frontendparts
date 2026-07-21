<?php

namespace App\Services\Ai;

use App\Models\Component;
use App\Services\Ai\Agents\CatalogSearchIntentAgent;
use App\Services\Ai\Agents\ComponentVariantAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

/**
 * Single seam for every AI call in the application (task 5.4) — no other
 * class talks to an AI provider. Built on the laravel/ai SDK: provider,
 * key, endpoint and model all resolve from config/ai.php (AI_PROVIDER /
 * AI_MODEL / the per-provider key entries), so production swaps providers
 * by env alone and tests fake at the agent level.
 *
 * Failure contract: search interpretation is fail-soft (returns null so the
 * search page silently falls back to plain results); variant generation is
 * fail-loud (throws RuntimeException so the queued job can notify the
 * admin). Nothing here ever lets an AI error reach a public user.
 */
class AiGateway
{
    /**
     * True when the configured provider has credentials. Both features
     * check this before prompting so a missing key degrades quietly.
     */
    public function configured(): bool
    {
        $key = config('ai.providers.'.$this->provider().'.key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * Interpret a natural-language catalog query against the live taxonomy.
     * Returns null when unconfigured, when the call fails, or when the
     * model answers with no usable signal — callers must treat null as
     * "run the plain search".
     *
     * @param  array{usage: list<string>, industries: list<string>, levels: list<string>}  $taxonomy
     */
    public function interpretSearchQuery(string $query, array $taxonomy): ?SearchIntent
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = (new CatalogSearchIntentAgent($taxonomy))->prompt(
                $query,
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            );
        } catch (Throwable $exception) {
            Log::warning('AI search intent failed; falling back to plain search.', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $intent = $this->mapSearchIntent($response, $taxonomy);

        return $intent->isEmpty() ? null : $intent;
    }

    /**
     * Generate a visual variation of a component (same params contract,
     * different styling) from its current entry sources.
     *
     * @param  array{react: string, vue: string}  $sources
     * @param  array<string, mixed>  $params
     *
     * @throws RuntimeException when unconfigured, the call fails, or the answer is unusable
     */
    public function generateComponentVariant(Component $component, array $sources, array $params): GeneratedVariant
    {
        if (! $this->configured()) {
            throw new RuntimeException('No AI provider key is configured.');
        }

        $agent = new ComponentVariantAgent(
            componentName: $component->name,
            level: $component->level->value,
            paramsSchema: json_encode($params === [] ? new \stdClass : $params, JSON_PRETTY_PRINT) ?: '{}',
        );

        try {
            $response = $agent->prompt(
                implode("\n\n", [
                    'Current React (index.tsx) source:',
                    $sources['react'],
                    'Current Vue (index.vue) source:',
                    $sources['vue'],
                ]),
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            );
        } catch (Throwable $exception) {
            throw new RuntimeException("AI variant generation failed: {$exception->getMessage()}", previous: $exception);
        }

        return $this->mapVariant($response);
    }

    /**
     * Map the structured answer onto a SearchIntent, dropping any slug the
     * model invented (hallucinated filters are silently discarded).
     *
     * @param  array{usage: list<string>, industries: list<string>, levels: list<string>}  $taxonomy
     */
    private function mapSearchIntent(StructuredAgentResponse $response, array $taxonomy): SearchIntent
    {
        $data = $response->toArray();

        return new SearchIntent(
            keywords: trim((string) ($data['keywords'] ?? '')),
            usageCategories: $this->onlyKnown($data['usage_categories'] ?? [], $taxonomy['usage']),
            industries: $this->onlyKnown($data['industries'] ?? [], $taxonomy['industries']),
            levels: $this->onlyKnown($data['levels'] ?? [], $taxonomy['levels']),
        );
    }

    /**
     * @throws RuntimeException when the answer misses required fields
     */
    private function mapVariant(StructuredAgentResponse $response): GeneratedVariant
    {
        $data = $response->toArray();

        $name = trim((string) ($data['name'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        $reactCode = trim((string) ($data['react_code'] ?? ''));
        $vueCode = trim((string) ($data['vue_code'] ?? ''));

        if ($name === '' || $reactCode === '' || $vueCode === '') {
            throw new RuntimeException('AI variant generation returned an incomplete answer.');
        }

        return new GeneratedVariant($name, $summary === '' ? 'AI-generated visual variation.' : $summary, $reactCode, $vueCode);
    }

    /**
     * Intersect the model's picks with the advertised taxonomy.
     *
     * @param  list<string>  $known
     * @return list<string>
     */
    private function onlyKnown(mixed $values, array $known): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_intersect(
            array_filter(array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values)),
            $known,
        ));
    }

    /**
     * Provider name from config/ai.php features block, falling back to the
     * SDK default provider.
     */
    private function provider(): string
    {
        return (string) (config('ai.features.provider') ?: config('ai.default', 'openai'));
    }

    private function model(): ?string
    {
        $model = config('ai.features.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    private function timeout(): int
    {
        return (int) (config('ai.features.timeout') ?? 30);
    }
}
