<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);

            $table->foreignId('target_user_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('target_user_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->after('customer_id')->constrained('sites')->nullOnDelete();

            $table->index('target_user_id');
            $table->index('customer_id');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['site_id']);

            $table->dropIndex(['target_user_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['site_id']);

            $table->dropColumn(['target_user_id', 'customer_id', 'site_id']);

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }
};
