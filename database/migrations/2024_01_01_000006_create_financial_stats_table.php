<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_purchase', 12, 2)->default(0);
            $table->decimal('courier_payouts', 12, 2)->default(0);
            $table->decimal('unforeseen_expenses', 12, 2)->default(0);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_stats');
    }
};
