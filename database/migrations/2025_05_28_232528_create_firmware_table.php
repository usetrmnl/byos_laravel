<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmware', function (Blueprint $table) {
            $table->id();
            $table->string('version_tag');
            $table->string('url')->nullable();
            $table->boolean('latest')->default(false);
            $table->string('storage_location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmware');
    }
};
