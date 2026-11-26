<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('updated_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            DB::statement('CREATE INDEX IF NOT EXISTS orders_external_id_trgm_gin_idx ON orders USING gin (external_id gin_trgm_ops);');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS orders_external_id_trgm_gin_idx;');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
        });
    }
};
