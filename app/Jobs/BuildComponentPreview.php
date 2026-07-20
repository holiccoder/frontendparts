<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the self-contained preview HTML artifacts for one component
 * (SPEC §5.2). Stub for now — the build pipeline is task 1.5.
 */
class BuildComponentPreview implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $frameworks
     */
    public function __construct(
        public int $componentId,
        public array $frameworks,
    ) {}

    public function handle(): void
    {
        // Implemented in task 1.5 (preview build pipeline).
    }
}
