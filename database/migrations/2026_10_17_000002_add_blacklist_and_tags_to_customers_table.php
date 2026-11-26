<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->index();
            $table->string('blacklist_reason')->nullable();
            $table->jsonb('tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['is_blacklisted']);
            $table->dropColumn(['is_blacklisted', 'blacklist_reason', 'tags']);
        });
    }
};
