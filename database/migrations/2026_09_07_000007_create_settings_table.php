<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('scope', 20)->default('global');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role', 50)->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX settings_global_unique ON settings (key) WHERE scope = 'global'");
            DB::statement("CREATE UNIQUE INDEX settings_role_unique ON settings (key, role) WHERE scope = 'role'");
            DB::statement("CREATE UNIQUE INDEX settings_user_unique ON settings (key, user_id) WHERE scope = 'user'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
