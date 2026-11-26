<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('customer_id');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['delivery_date']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
        });
    }
};
