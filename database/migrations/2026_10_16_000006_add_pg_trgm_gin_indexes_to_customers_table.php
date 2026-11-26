<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            DB::statement('CREATE INDEX IF NOT EXISTS customers_name_trgm_gin_idx ON customers USING gin (name gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS customers_phone_trgm_gin_idx ON customers USING gin (phone gin_trgm_ops);');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS customers_name_trgm_gin_idx;');
            DB::statement('DROP INDEX IF EXISTS customers_phone_trgm_gin_idx;');
        }
    }
};
