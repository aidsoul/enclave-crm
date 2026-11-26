<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_price', 10, 2);
            $table->decimal('purchase_price', 10, 2);
            $table->enum('status', ['created', 'accepted', 'assigned', 'at_warehouse', 'cancelled', 'completed'])->default('created');
            $table->boolean('is_edited')->default(false);
            $table->jsonb('extra_data')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['manager_id', 'created_at']);
            $table->index(['courier_id', 'status']);
            $table->index('courier_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
