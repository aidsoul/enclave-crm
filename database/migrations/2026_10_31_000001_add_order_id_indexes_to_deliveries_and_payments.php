<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });
    }
};
