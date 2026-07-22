<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Lightweight database backup command that avoids an extra package dependency.
 *
 * - SQLite: copies the database file to the configured `backups` disk.
 * - MySQL: shells out to `mysqldump` if available and streams the result.
 *
 * Backups older than the configured retention window (default 14 days) are
 * pruned after a successful dump.
 */
class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
        {--disk=backups : Filesystem disk to store backups on}
        {--retention=14 : Days to keep backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Back up the application database to the configured disk.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk($this->option('disk'));
        $connection = Config::get('database.default');
        $config = Config::get("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d-His');
        $baseName = "backup-{$timestamp}";

        $dumpPath = match ($config['driver'] ?? '') {
            'sqlite' => $this->backupSqlite($disk, $config, $baseName),
            'mysql' => $this->backupMysql($disk, $config, $baseName),
            default => null,
        };

        if ($dumpPath === null) {
            $driver = $config['driver'] ?? 'unknown';
            $this->error("Database driver [{$driver}] is not supported by db:backup.");

            return self::FAILURE;
        }

        $this->info("Backup written to [{$dumpPath}].");

        $this->pruneOldBackups($disk, (int) $this->option('retention'));

        return self::SUCCESS;
    }

    /**
     * Back up a SQLite database by copying the file.
     */
    private function backupSqlite($disk, array $config, string $baseName): ?string
    {
        $database = $config['database'] ?? database_path('database.sqlite');

        if (! is_file($database)) {
            $this->error("SQLite database file not found at [{$database}].");

            return null;
        }

        $path = "backups/{$baseName}.sqlite";
        $disk->put($path, file_get_contents($database));

        return $path;
    }

    /**
     * Back up a MySQL database using mysqldump.
     */
    private function backupMysql($disk, array $config, string $baseName): ?string
    {
        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
        ];

        if (! empty($config['password'])) {
            $command[] = '--password='.($config['password'] ?? '');
        }

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        }

        $command[] = '--single-transaction';
        $command[] = '--routines';
        $command[] = '--triggers';
        $command[] = $config['database'] ?? '';

        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed: '.$process->getErrorOutput());

            return null;
        }

        $path = "backups/{$baseName}.sql";
        $disk->put($path, $process->getOutput());

        return $path;
    }

    /**
     * Delete backups older than the retention window.
     */
    private function pruneOldBackups($disk, int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays);

        foreach ($disk->files('backups') as $file) {
            if ($disk->lastModified($file) < $cutoff->getTimestamp()) {
                $disk->delete($file);
                $this->line("Pruned old backup [{$file}].");
            }
        }
    }
}
