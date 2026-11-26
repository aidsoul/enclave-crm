<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sku', 100)->nullable()->after('name');
            $table->index('sku');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('type');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropColumn('sku');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn('city');
        });
    }
};
