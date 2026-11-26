<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('failed_jobs') && ! Schema::hasColumn('failed_jobs', 'id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;');
                DB::statement('ALTER TABLE failed_jobs ADD COLUMN id BIGSERIAL PRIMARY KEY;');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS failed_jobs_uuid_unique ON failed_jobs (uuid);');
            } else {
                Schema::table('failed_jobs', function (Blueprint $table) {
                    $table->bigIncrements('id')->first();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('failed_jobs') && Schema::hasColumn('failed_jobs', 'id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS failed_jobs_uuid_unique;');
                DB::statement('ALTER TABLE failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;');
                DB::statement('ALTER TABLE failed_jobs DROP COLUMN IF EXISTS id;');
                DB::statement('ALTER TABLE failed_jobs ADD PRIMARY KEY (uuid);');
            } else {
                Schema::table('failed_jobs', function (Blueprint $table) {
                    $table->dropColumn('id');
                });
            }
        }
    }
};
