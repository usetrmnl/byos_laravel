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
        Schema::table('plugins', function (Blueprint $table) {
            $table->text('render_markup_half_horizontal')->nullable()->after('render_markup');
            $table->text('render_markup_half_vertical')->nullable()->after('render_markup_half_horizontal');
            $table->text('render_markup_quadrant')->nullable()->after('render_markup_half_vertical');
            $table->text('render_markup_shared')->nullable()->after('render_markup_quadrant');
            $table->text('transform_code')->nullable()->after('render_markup_shared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn([
                'render_markup_half_horizontal',
                'render_markup_half_vertical',
                'render_markup_quadrant',
                'render_markup_shared',
                'transform_code',
            ]);
        });
    }
};
