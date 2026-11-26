<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'orders_customer_id_status_index');
            $table->index(['created_at', 'status'], 'orders_created_at_status_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['order_id', 'type'], 'deliveries_order_id_type_index');
        });

        Schema::table('action_logs', function (Blueprint $table) {
            $table->index(['order_id', 'created_at'], 'action_logs_order_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex('action_logs_order_id_created_at_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_order_id_type_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_at_status_index');
            $table->dropIndex('orders_customer_id_status_index');
        });
    }
};
