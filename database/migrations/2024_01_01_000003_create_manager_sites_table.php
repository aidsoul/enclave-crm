<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_sites', function (Blueprint $table) {
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->primary(['manager_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_sites');
    }
};
