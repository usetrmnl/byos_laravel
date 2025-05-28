<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('update_firmware_id')->nullable()->constrained('firmware')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['update_firmware_id']);
            $table->dropColumn('update_firmware_id');
        });
    }
};
