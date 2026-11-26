<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->decimal('default_purchase_percent', 5, 2)->nullable()->after('domain');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('default_purchase_percent');
        });
    }
};
