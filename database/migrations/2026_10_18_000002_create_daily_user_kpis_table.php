<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_user_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('role');

            $table->unsignedInteger('accepted_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('in_progress_orders')->default(0);
            $table->unsignedBigInteger('total_response_time_seconds')->default(0);
            $table->unsignedInteger('response_orders_count')->default(0);

            $table->unsignedInteger('completed_deliveries')->default(0);
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('terminal_amount', 12, 2)->default(0);
            $table->decimal('other_amount', 12, 2)->default(0);
            $table->decimal('payout_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
            $table->index(['role', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_user_kpis');
    }
};
