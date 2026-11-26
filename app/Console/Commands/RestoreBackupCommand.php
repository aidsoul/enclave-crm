<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore
                            {archive? : Path to or filename of the backup archive}
                            {--disk= : Storage disk where backups are stored}
                            {--latest : Automatically restore the most recent backup}
                            {--password= : Password to decrypt the archive}
                            {--force : Force the operation without confirmation}';

    protected $description = 'Restore database from a backup archive';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?: (config('backup.backup.destination.disks')[0] ?? 'local');
        $archivePath = $this->resolveArchivePath($diskName);

        if (! $archivePath || ! file_exists($archivePath)) {
            $this->error('Backup archive not found.');

            return self::FAILURE;
        }

        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        if (($dbConfig['driver'] ?? '') !== 'pgsql') {
            $this->error('Only PostgreSQL database driver is supported for backup restore.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $dbName = $dbConfig['database'];
            if (! $this->confirm("WARNING: This will overwrite the database [{$dbName}]. Continue?", false)) {
                $this->info('Restore aborted.');

                return self::SUCCESS;
            }
        }

        $tempDir = storage_path('app/backup-temp/restore-'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        try {
            $this->info("Extracting archive [{$archivePath}]...");
            if (! $this->extractArchive($archivePath, $tempDir)) {
                return self::FAILURE;
            }

            $dumpFile = $this->findDumpFile($tempDir);
            if (! $dumpFile) {
                $this->error('No database dumps found in the archive.');

                return self::FAILURE;
            }

            $sqlFile = $this->prepareSqlFile($dumpFile);
            if (! $sqlFile) {
                $this->error('Failed to prepare SQL dump file.');

                return self::FAILURE;
            }

            $this->info('Resetting public schema...');
            DB::statement('DROP SCHEMA public CASCADE');
            DB::statement('CREATE SCHEMA public');

            $this->info('Importing database dump...');
            $process = new Process([
                'psql',
                '--host='.$dbConfig['host'],
                '--port='.(string) $dbConfig['port'],
                '--username='.$dbConfig['username'],
                '--dbname='.$dbConfig['database'],
                '--file='.$sqlFile,
            ], null, [
                'PGPASSWORD' => $dbConfig['password'] ?? '',
            ]);

            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error('Failed to import database: '.$process->getErrorOutput());

                return self::FAILURE;
            }

            $this->callSilent('cache:clear');
            $this->info('Database restored successfully.');

            return self::SUCCESS;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    private function resolveArchivePath(string $diskName): ?string
    {
        $archive = $this->argument('archive');
        $disk = Storage::disk($diskName);

        if ($archive) {
            if (file_exists($archive)) {
                return $archive;
            }

            if ($disk->exists($archive)) {
                return $disk->path($archive);
            }

            $backupName = config('backup.backup.name', 'laravel-backup');
            $prefixed = $backupName.'/'.$archive;
            if ($disk->exists($prefixed)) {
                return $disk->path($prefixed);
            }

            return null;
        }

        $backupFiles = $this->getAvailableBackups($disk);

        if (empty($backupFiles)) {
            return null;
        }

        if ($this->option('latest')) {
            return $disk->path($backupFiles[0]);
        }

        $choices = [];
        foreach ($backupFiles as $file) {
            $size = round($disk->size($file) / 1024, 2).' KB';
            $date = date('Y-m-d H:i:s', $disk->lastModified($file));
            $choices[$file] = "{$file} ({$size}, {$date})";
        }

        $selected = $this->choice('Select backup archive to restore', array_values($choices), 0);
        $selectedFile = array_search($selected, $choices, true);

        return $selectedFile ? $disk->path($selectedFile) : null;
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableBackups(Filesystem $disk): array
    {
        $backupName = config('backup.backup.name', 'laravel-backup');
        $files = array_filter(
            $disk->allFiles($backupName),
            fn ($file) => str_ends_with($file, '.zip')
        );

        if (empty($files)) {
            $files = array_filter(
                $disk->allFiles(),
                fn ($file) => str_ends_with($file, '.zip')
            );
        }

        usort($files, fn ($a, $b) => $disk->lastModified($b) <=> $disk->lastModified($a));

        return array_values($files);
    }

    private function extractArchive(string $archivePath, string $destination): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            $this->error("Cannot open archive: {$archivePath}");

            return false;
        }

        $password = $this->option('password') ?: config('backup.backup.password');
        if (! empty($password)) {
            $zip->setPassword($password);
        }

        $extracted = $zip->extractTo($destination);
        $zip->close();

        if (! $extracted) {
            $this->error('Failed to extract archive. Check if the password is correct.');

            return false;
        }

        return true;
    }

    private function findDumpFile(string $dir): ?string
    {
        $files = glob($dir.'/db-dumps/*.sql*');

        if (! empty($files)) {
            return $files[0];
        }

        $allFiles = glob($dir.'/*.sql*');

        return ! empty($allFiles) ? $allFiles[0] : null;
    }

    private function prepareSqlFile(string $dumpFile): ?string
    {
        if (! str_ends_with($dumpFile, '.gz')) {
            return $dumpFile;
        }

        $sqlFile = substr($dumpFile, 0, -3);
        $gz = gzopen($dumpFile, 'rb');
        $out = fopen($sqlFile, 'wb');

        if (! $gz || ! $out) {
            return null;
        }

        while (! gzeof($gz)) {
            $chunk = gzread($gz, 4096);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }

        fclose($out);
        gzclose($gz);

        return $sqlFile;
    }
}
