<?php

namespace App\Console\Commands;

use App\Enums\ComponentStatus;
use App\Models\Component;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * CLI publish workflow (SPEC §8.5), the batch counterpart of the Filament
 * PublishComponentAction. Publishes one component (`library:publish
 * elements/button-01`) or every component (`--all`) that passes the
 * canPublish() artifact gate (both-framework previews + 3-width
 * screenshots). Components failing the gate are skipped with a warning;
 * published records store the full QA checklist (all five checks accepted,
 * CLI provenance noted) as the publish audit trail.
 */
#[Signature('library:publish {slug? : Full component slug (e.g. elements/button-01)} {--all : Publish every component passing the QA gate}')]
#[Description('Publish components that pass the canPublish() artifact gate, storing the QA checklist (SPEC §8.5)')]
class LibraryPublishCommand extends Command
{
    /**
     * The QA checklist stored on publish — mirrors the Filament publish
     * modal's five accepted checks, with CLI provenance noted.
     *
     * @var array<string, mixed>
     */
    private const QA_CHECKLIST = [
        'viewports' => true,
        'visual_parity' => true,
        'data_separated' => true,
        'license_clean' => true,
        'accessibility' => true,
        'note' => 'author-verified via CLI',
    ];

    public function handle(): int
    {
        $components = $this->targetComponents();

        if ($components === null) {
            return self::FAILURE;
        }

        if ($components->isEmpty()) {
            $this->warn('No components found to publish.');

            return self::SUCCESS;
        }

        $published = 0;
        $skipped = 0;

        foreach ($components as $component) {
            if (! $component->canPublish()) {
                $this->line("  <comment>⚠</comment> {$component->slug} — skipped (previews/screenshots missing; run library:sync and let builds finish)");
                $skipped++;

                continue;
            }

            $component->update([
                'status' => ComponentStatus::Published,
                'qa_checklist' => self::QA_CHECKLIST,
                'review_note' => null,
            ]);

            $this->line("  <info>✓</info> {$component->slug}");
            $published++;
        }

        $this->newLine();
        $this->info("Published {$published}, skipped {$skipped}.");

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Component>|null null when the arguments are invalid
     */
    private function targetComponents(): ?Collection
    {
        if ((bool) $this->option('all')) {
            return Component::query()->orderBy('slug')->get();
        }

        $slug = $this->argument('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $this->error('Provide a component slug or pass --all.');

            return null;
        }

        $component = Component::query()->where('slug', $slug)->first();

        if ($component === null) {
            $this->error("Component '{$slug}' not found.");

            return null;
        }

        return collect([$component]);
    }
}
