<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->string('css_name')->nullable()->after('kind');
            $table->json('css_variables')->nullable()->after('css_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropColumn(['css_name', 'css_variables']);
        });
    }
};
