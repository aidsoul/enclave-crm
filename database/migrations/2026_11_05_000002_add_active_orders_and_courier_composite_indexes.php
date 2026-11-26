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
            $table->index(['manager_id', 'status'], 'orders_manager_id_status_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['user_id', 'delivery_date'], 'deliveries_user_id_delivery_date_index');
        });

        DB::statement("CREATE INDEX IF NOT EXISTS orders_active_flow_index ON orders (status, updated_at DESC) WHERE status NOT IN ('completed', 'cancelled')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS orders_active_flow_index');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_user_id_delivery_date_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_manager_id_status_index');
        });
    }
};
