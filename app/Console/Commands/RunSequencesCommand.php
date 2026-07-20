<?php

namespace App\Console\Commands;

use App\Services\Sequences\SequenceRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:run-sequences')]
#[Description('Run lifecycle email sequences B1–B4 (SPEC §16.2): due drip steps, behavioral triggers and digests')]
class RunSequencesCommand extends Command
{
    public function handle(SequenceRunner $runner): int
    {
        $summary = $runner->run();

        foreach ($summary as $sequence => $sent) {
            $this->line("  {$sequence}: {$sent} sent");
        }

        $this->info('Sequences complete — '.array_sum($summary).' notification(s) queued.');

        return self::SUCCESS;
    }
}
