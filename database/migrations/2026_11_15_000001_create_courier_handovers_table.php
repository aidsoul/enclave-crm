<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_code', 32)->unique();
            $table->foreignId('from_courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 32)->default('pending'); // pending, completed, cancelled, disputed
            $table->string('reason', 64); // vehicle_breakdown, accident, health, other
            $table->text('reason_comment')->nullable();

            // Cash reconciliation
            $table->boolean('transfer_cash')->default(false);
            $table->decimal('system_cash_amount', 12, 2)->default(0);
            $table->decimal('declared_cash_amount', 12, 2)->default(0);
            $table->decimal('actual_cash_amount', 12, 2)->default(0);
            $table->decimal('discrepancy', 12, 2)->default(0);
            $table->text('discrepancy_reason')->nullable();
            $table->string('discrepancy_photo', 255)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['from_courier_id', 'status']);
            $table->index(['to_courier_id', 'status']);
            $table->index('transfer_code');
            $table->index(['transfer_cash', 'status', 'completed_at']);
            $table->index(['from_courier_id', 'transfer_cash', 'status', 'completed_at'], 'courier_handovers_from_cash_idx');
            $table->index(['to_courier_id', 'transfer_cash', 'status', 'completed_at'], 'courier_handovers_to_cash_idx');
        });

        Schema::create('courier_handover_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('courier_handovers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('previous_status', 32)->nullable();
            $table->timestamps();

            $table->unique(['handover_id', 'order_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_handover_orders');
        Schema::dropIfExists('courier_handovers');
    }
};
