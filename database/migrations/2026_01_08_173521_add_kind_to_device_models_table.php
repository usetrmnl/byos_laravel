<?php

use App\Models\DeviceModel;
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
            $table->string('kind')->nullable()->index();
        });

        // Set existing og_png and og_plus to kind "trmnl"
        DeviceModel::whereIn('name', ['og_png', 'og_plus'])->update(['kind' => 'trmnl']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
