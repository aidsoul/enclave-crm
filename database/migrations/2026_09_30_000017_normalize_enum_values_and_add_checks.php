<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE deliveries SET type = LOWER(type) WHERE type IS NOT NULL');
        DB::statement('UPDATE payments SET method = LOWER(method) WHERE method IS NOT NULL');
        DB::statement('UPDATE payments SET status = LOWER(status) WHERE status IS NOT NULL');
        DB::statement('UPDATE order_discounts SET type = LOWER(type) WHERE type IS NOT NULL');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('type')->default('courier')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_type_check CHECK (type::text = ANY (ARRAY['courier'::text, 'pickup'::text]))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method::text = ANY (ARRAY['cash'::text, 'terminal'::text, 'erip'::text, 'credit'::text, 'installment'::text]))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'paid'::text, 'failed'::text]))");
        DB::statement("ALTER TABLE order_discounts ADD CONSTRAINT order_discounts_type_check CHECK (type::text = ANY (ARRAY['percentage'::text, 'fixed'::text, 'coupon'::text]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE deliveries DROP CONSTRAINT IF EXISTS deliveries_type_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement('ALTER TABLE order_discounts DROP CONSTRAINT IF EXISTS order_discounts_type_check');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('type')->default(null)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default(null)->change();
        });
    }
};
