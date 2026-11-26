<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('action_type', ['create', 'update', 'delete', 'status_change', 'assign', 'panic']);
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at');

            $table->index('order_id');
            $table->index(['actor_id', 'created_at']);
            $table->index('action_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
    }
};
