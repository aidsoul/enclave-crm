<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS leads_site_external_id_unique ON leads (site_id, external_id) WHERE external_id IS NOT NULL;');
            DB::statement('CREATE INDEX IF NOT EXISTS leads_name_trgm_gin_idx ON leads USING gin (name gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS leads_phone_trgm_gin_idx ON leads USING gin (phone gin_trgm_ops);');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS leads_site_external_id_unique;');
            DB::statement('DROP INDEX IF EXISTS leads_name_trgm_gin_idx;');
            DB::statement('DROP INDEX IF EXISTS leads_phone_trgm_gin_idx;');
        }
    }
};
