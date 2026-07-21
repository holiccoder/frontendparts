<?php

namespace App\Console\Commands;

use App\Models\Component;
use App\Services\Library\LibrarySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('library:sync')]
#[Description('Scan the component library, validate, upsert the DB, queue preview builds and re-sync the search index (SPEC §8.3)')]
class LibrarySyncCommand extends Command
{
    public function handle(LibrarySync $sync): int
    {
        $result = $sync->run();

        foreach ($result->errors as $slug => $messages) {
            if ($messages === []) {
                $this->line("  <info>✓</info> {$slug}");

                continue;
            }

            $this->line("  <error>✗</error> {$slug}");

            foreach ($messages as $message) {
                $this->line("      <comment>{$message}</comment>");
            }
        }

        $this->newLine();
        $this->info("Scanned {$result->scanned}, upserted {$result->upserted}, failed ".count($result->failures()).'.');

        if ($result->rebuiltComponentIds !== []) {
            $this->line('Queued preview builds for '.count($result->rebuiltComponentIds).' component(s).');
        }

        // Re-sync the search index (Phase 5.1): tag/industry pivots sync
        // after the component save, so the per-model observer alone could
        // push a stale payload. Builder-level searchable() pushes published
        // components only and is a no-op on the local collection engine.
        if ($result->upserted > 0) {
            Component::query()->searchable();
            $this->line('Search index re-synced for the component catalog.');
        }

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
