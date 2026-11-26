<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('new_value');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE action_logs DROP CONSTRAINT IF EXISTS action_logs_action_type_check');
            DB::statement("ALTER TABLE action_logs ADD CONSTRAINT action_logs_action_type_check CHECK (action_type::text = ANY (ARRAY['create'::text, 'update'::text, 'delete'::text, 'status_change'::text, 'assign'::text, 'panic'::text, 'auth'::text, 'export'::text]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE action_logs DROP CONSTRAINT IF EXISTS action_logs_action_type_check');
            DB::statement("ALTER TABLE action_logs ADD CONSTRAINT action_logs_action_type_check CHECK (action_type::text = ANY (ARRAY['create'::text, 'update'::text, 'delete'::text, 'status_change'::text, 'assign'::text, 'panic'::text]))");
        }

        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
