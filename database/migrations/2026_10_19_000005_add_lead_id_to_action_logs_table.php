<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('order_id')->constrained('leads')->nullOnDelete();
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropIndex(['lead_id']);
            $table->dropColumn('lead_id');
        });
    }
};
