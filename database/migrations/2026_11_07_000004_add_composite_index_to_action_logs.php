<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->index(['action_type', 'created_at'], 'action_logs_action_type_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex('action_logs_action_type_created_at_index');
        });
    }
};
