<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS orders_site_external_id_active_unique ON orders (site_id, external_id) WHERE external_id IS NOT NULL AND status != 'completed';");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS orders_site_external_id_active_unique;');
        }
    }
};
