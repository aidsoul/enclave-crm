<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_unread_driver_message')->default(false);
            $table->boolean('has_unread_manager_message')->default(false);
            $table->timestamp('last_message_at')->nullable();

            $table->index(['has_unread_driver_message', 'last_message_at']);
            $table->index(['has_unread_manager_message', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['has_unread_driver_message', 'last_message_at']);
            $table->dropIndex(['has_unread_manager_message', 'last_message_at']);
            $table->dropColumn(['has_unread_driver_message', 'has_unread_manager_message', 'last_message_at']);
        });
    }
};
