<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type')->default('callback');
            $table->string('status')->default('new');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->string('page_url', 1000)->nullable();
            $table->string('page_title')->nullable();
            $table->string('external_id')->nullable();
            $table->jsonb('extra_data')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'status']);
            $table->index('customer_id');
            $table->index('phone');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
