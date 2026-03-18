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
        Schema::create('device_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('make');
            $table->string('model');
            $table->string('kind');
            $table->double('value');
            $table->string('unit');
            $table->string('source');
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
            $table->index(['device_id', 'kind', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sensors');
    }
};
