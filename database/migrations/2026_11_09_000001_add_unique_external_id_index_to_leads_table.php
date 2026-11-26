<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                DELETE FROM leads
                WHERE id IN (
                    SELECT id FROM (
                        SELECT id, ROW_NUMBER() OVER (PARTITION BY site_id, external_id ORDER BY id DESC) as rn
                        FROM leads
                        WHERE external_id IS NOT NULL
                    ) t
                    WHERE t.rn > 1
                );
            ');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS leads_site_external_id_unique ON leads (site_id, external_id) WHERE external_id IS NOT NULL;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS leads_site_external_id_unique ON leads (site_id, external_id) WHERE external_id IS NOT NULL;');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS leads_site_external_id_unique;');
        }
    }
};
