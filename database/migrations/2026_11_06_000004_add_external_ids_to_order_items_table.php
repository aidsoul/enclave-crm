<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('external_product_id')->nullable()->after('product_id');
            $table->string('external_variant_id')->nullable()->after('product_variant_id');

            $table->index('external_product_id');
            $table->index('external_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['external_product_id']);
            $table->dropIndex(['external_variant_id']);
            $table->dropColumn(['external_product_id', 'external_variant_id']);
        });
    }
};
