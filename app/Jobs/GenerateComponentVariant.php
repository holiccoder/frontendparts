<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Component;
use App\Services\Ai\AiGateway;
use App\Services\Ai\GeneratedVariant;
use App\Services\Ai\VariantComponentCreator;
use App\Services\Catalog\ComponentContent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * AI component-variant pipeline (task 5.4, features.ai_variants), queued by
 * the GenerateVariantAction so the panel returns immediately. On success the
 * variant lands as an in-review component linked to the original
 * (VariantComponentCreator) and the admin gets a database notification with
 * the model's change summary; on failure no component is created and the
 * admin gets a danger notification instead. Failures never bubble up for
 * retries — one broken generation must not stall the queue.
 */
class GenerateComponentVariant implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $componentId,
        public int $adminId,
    ) {}

    public function handle(AiGateway $ai, ComponentContent $content, VariantComponentCreator $creator): void
    {
        $component = Component::query()->find($this->componentId);
        $admin = Admin::query()->find($this->adminId);

        if ($component === null) {
            return;
        }

        try {
            $payload = $content->for($component);

            [$sources, $params, $data] = $this->sources($component, $payload);

            $variant = $ai->generateComponentVariant($component, $sources, $params);

            $created = $creator->create($component, $variant, $params, $data);
        } catch (Throwable $exception) {
            $this->notifyFailure($admin, $component, $exception);

            return;
        }

        $this->notifySuccess($admin, $component, $created, $variant);
    }

    /**
     * Extract the per-framework entry sources and the shared params/data
     * contract from the component payload. Both twins must exist locally —
     * the variant keeps the same API, so there is nothing to generate from
     * a missing source.
     *
     * @param  array{files: array{react: list<array{path: string, code: string}>, vue: list<array{path: string, code: string}>}, data: array<string, mixed>, params: array<string, mixed>}  $payload
     * @return array{0: array{react: string, vue: string}, 1: array<string, mixed>, 2: array<string, mixed>}
     *
     * @throws RuntimeException when either framework source is unavailable
     */
    private function sources(Component $component, array $payload): array
    {
        $react = $payload['files']['react'][0]['code'] ?? null;
        $vue = $payload['files']['vue'][0]['code'] ?? null;

        if (! is_string($react) || trim($react) === '' || ! is_string($vue) || trim($vue) === '') {
            throw new RuntimeException('Component source is not available in the local library tree.');
        }

        return [['react' => $react, 'vue' => $vue], $payload['params'], $payload['data']];
    }

    private function notifySuccess(?Admin $admin, Component $original, Component $variant, GeneratedVariant $generated): void
    {
        if ($admin === null) {
            return;
        }

        Notification::make()
            ->title('AI variant ready for review')
            ->body("\"{$generated->name}\" was generated from \"{$original->name}\" and is waiting in the review queue. {$generated->summary}")
            ->success()
            ->sendToDatabase($admin);
    }

    private function notifyFailure(?Admin $admin, Component $component, Throwable $exception): void
    {
        if ($admin === null) {
            return;
        }

        Notification::make()
            ->title('AI variant generation failed')
            ->body("Could not generate a variant of \"{$component->name}\": {$exception->getMessage()}")
            ->danger()
            ->sendToDatabase($admin);
    }
}
