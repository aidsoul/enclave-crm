<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique()->after('name');
        });

        // Migrate existing plaintext tokens to SHA-256 hashes
        $sites = DB::table('sites')->whereNotNull('api_token')->get();
        foreach ($sites as $site) {
            DB::table('sites')->where('id', $site->id)->update([
                'api_token_hash' => hash('sha256', (string) $site->api_token),
            ]);
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->uuid('api_token')->nullable()->unique()->after('name');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('api_token_hash');
        });
    }
};

