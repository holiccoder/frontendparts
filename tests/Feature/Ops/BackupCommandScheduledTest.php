<?php

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupCommandScheduledTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_is_scheduled(): void
    {
        // Console routes (where schedules are registered) are only loaded
        // when the console kernel is bootstrapped.
        $this->app->make(Kernel::class)->bootstrap();

        $schedule = $this->app->make(Schedule::class);

        $commands = collect($schedule->events())
            ->map(fn ($event): string => $event->command ?? '')
            ->values()
            ->all();

        $this->assertNotEmpty($commands);
        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'db:backup')),
            'Expected db:backup to be scheduled.'
        );
    }
}
