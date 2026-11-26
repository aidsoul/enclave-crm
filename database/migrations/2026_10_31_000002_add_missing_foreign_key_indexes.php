<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index('manager_id');
            $table->index('converted_order_id');
        });

        Schema::table('manager_sites', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('manager_sites', function (Blueprint $table) {
            $table->dropIndex(['site_id']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['converted_order_id']);
            $table->dropIndex(['manager_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['site_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
