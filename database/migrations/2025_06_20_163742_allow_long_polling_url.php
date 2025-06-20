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
        Schema::table("plugins", function (Blueprint $table) {
            $table->string('polling_url', 1024)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("plugins", function (Blueprint $table) {
            // old default string length value in Illuminate
            $table->string('polling_url', 255)->nullable()->change();
        });
    }
};
