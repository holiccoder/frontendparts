<?php

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_connection_is_database_in_production(): void
    {
        // In production the queue worker must consume the database-backed
        // queue so jobs are durable across deploys (SPEC §4.1.4).
        Config::set('app.env', 'production');
        Config::set('queue.default', 'database');

        $this->assertSame('database', config('queue.default'));
    }

    public function test_required_scheduled_commands_present(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $schedule = $this->app->make(Schedule::class);

        $commands = collect($schedule->events())
            ->map(fn ($event): string => $event->command ?? '')
            ->values()
            ->all();

        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'mail:run-sequences')),
            'Expected mail:run-sequences to be scheduled.'
        );

        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'affiliates:mark-payable')),
            'Expected affiliates:mark-payable to be scheduled.'
        );

        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'db:backup')),
            'Expected db:backup to be scheduled.'
        );
    }
}
