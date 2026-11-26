<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

#[Group('destructive')]
class RestoreBackupCommandTest extends TestCase
{
    private string $tempTestDir;

    protected function setUp(): void
    {
        parent::setUp();

        $dbName = config('database.connections.'.config('database.default').'.database');
        if ($dbName !== 'crm_testing' || ! app()->environment('testing')) {
            $this->markTestSkipped('RestoreBackupCommandTest can ONLY be run against crm_testing database in testing environment.');
        }

        $this->tempTestDir = storage_path('app/testing-restore-'.uniqid());
        File::makeDirectory($this->tempTestDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempTestDir)) {
            File::deleteDirectory($this->tempTestDir);
            $this->artisan('migrate');
        }
        parent::tearDown();
    }

    public function test_fails_when_archive_does_not_exist(): void
    {
        $this->artisan('backup:restore non_existing_archive_123.zip --force')
            ->expectsOutput('Backup archive not found.')
            ->assertExitCode(1);
    }

    public function test_aborts_when_user_declines_confirmation(): void
    {
        $zipPath = $this->createDummyZip();

        $this->artisan('backup:restore', ['archive' => $zipPath])
            ->expectsConfirmation('WARNING: This will overwrite the database [crm_testing]. Continue?', 'no')
            ->expectsOutput('Restore aborted.')
            ->assertExitCode(0);
    }

    public function test_successfully_restores_from_valid_archive(): void
    {
        $zipPath = $this->createDummyZip();

        $this->artisan('backup:restore', ['archive' => $zipPath, '--force' => true])
            ->expectsOutput('Database restored successfully.')
            ->assertExitCode(0);

        $exists = DB::select("SELECT to_regclass('public.restore_test_table') as table_name");
        $this->assertNotEmpty($exists);
        $this->assertNotNull($exists[0]->table_name);

        DB::statement('DROP TABLE IF EXISTS public.restore_test_table CASCADE');
    }

    private function createDummyZip(): string
    {
        $sql = "CREATE TABLE public.restore_test_table (id serial primary key, name text); INSERT INTO public.restore_test_table (name) VALUES ('restored');";
        $gzPath = $this->tempTestDir.'/postgresql-test.sql.gz';

        $gz = gzopen($gzPath, 'wb9');
        $this->assertNotFalse($gz);
        gzwrite($gz, $sql);
        gzclose($gz);

        $zipPath = $this->tempTestDir.'/test_backup.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFile($gzPath, 'db-dumps/postgresql-test.sql.gz'));
        $zip->close();

        return $zipPath;
    }
}
