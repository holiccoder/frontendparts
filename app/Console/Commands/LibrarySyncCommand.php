<?php

namespace App\Console\Commands;

use App\Services\Library\LibrarySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('library:sync')]
#[Description('Scan the component library, validate, upsert the DB and queue preview builds (SPEC §8.3)')]
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

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
