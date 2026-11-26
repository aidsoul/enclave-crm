<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade')->unique();
            $table->string('type')->default('courier');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('courier_comment')->nullable();
            $table->text('pickup_point')->nullable();
            $table->text('delivery_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
