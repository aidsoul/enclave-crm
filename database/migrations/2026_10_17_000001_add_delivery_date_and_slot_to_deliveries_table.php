<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('delivery_cost');
            $table->string('delivery_slot', 50)->nullable()->after('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'delivery_slot']);
        });
    }
};
