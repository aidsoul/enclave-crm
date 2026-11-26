<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $proofDir = storage_path('app/public/delivery-proofs');
        if (! is_dir($proofDir)) {
            @mkdir($proofDir, 0775, true);
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('photo_proof_path')->nullable()->after('courier_comment');
            $table->timestamp('photo_proof_at')->nullable()->after('photo_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['photo_proof_path', 'photo_proof_at']);
        });
    }
};
