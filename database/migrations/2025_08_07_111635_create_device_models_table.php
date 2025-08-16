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
        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('colors');
            $table->unsignedInteger('bit_depth');
            $table->float('scale_factor');
            $table->integer('rotation');
            $table->string('mime_type');
            $table->integer('offset_x')->default(0);
            $table->integer('offset_y')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_models');
    }
};
