<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->enum('role', ['owner', 'manager', 'courier']);
            $table->string('system_name')->nullable();
            $table->unsignedInteger('max_activations');
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
