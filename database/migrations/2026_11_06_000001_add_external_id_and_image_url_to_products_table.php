<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('site_id');
            $table->text('image_url')->nullable()->after('description');
            $table->text('url')->nullable()->after('image_url');

            $table->unique(['site_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'external_id']);
            $table->dropColumn(['external_id', 'image_url', 'url']);
        });
    }
};
