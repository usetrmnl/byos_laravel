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
            $table->foreignId('palette_id')->nullable()->after('source')->constrained('device_palettes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropForeign(['palette_id']);
            $table->dropColumn('palette_id');
        });
    }
};
