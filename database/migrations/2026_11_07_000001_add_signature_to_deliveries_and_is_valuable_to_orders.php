<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sigDir = storage_path('app/public/delivery-signatures');
        if (! is_dir($sigDir)) {
            @mkdir($sigDir, 0775, true);
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('photo_proof_at');
            $table->timestamp('signature_at')->nullable()->after('signature_path');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_valuable')->default(false)->after('is_edited');
            $table->index('is_valuable');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['is_valuable']);
            $table->dropColumn('is_valuable');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signature_at']);
        });
    }
};
