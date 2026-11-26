<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['owner', 'manager', 'courier']);
            $table->string('system_name');
            $table->string('wipe_confirm_code_hash')->nullable();
            $table->string('telegram_user_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('role');
            $table->index('is_active');
            $table->index('telegram_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
