<?php

use App\Enums\PaletteName;
use App\Models\DeviceModel;
use App\Models\Palette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add color_type column corresponding to \Bnussbau\TrmnlPipeline\Data\ColorType enum
        Schema::table('device_models', function (Blueprint $table): void {
            $table->string('color_type')->default('grayscale');
            $table->foreignIdFor(Palette::class)->nullable()->constrained();
        });

        // Ensure all existing records are populated with the default value
        DB::table('device_models')->whereNull('color_type')->update(['color_type' => 'grayscale']);

        // Create palette table
        Schema::create('palettes', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('description');
            $table->longText('palette');
        });

        // Insert Spectra 6 palette
        DB::table('palettes')->insert([
            'name' => 'spectra_6',
            'description' => 'Spectra 6 Color',
            'palette' => json_encode(
                array_map(
                    fn ($color) => $color->toInt(),
                    PaletteName::getPalette(PaletteName::SPECTRA_6)
                )
            ),
        ]);

        $palette = Palette::query()->where(
            column: 'name',
            operator: '=',
            value: PaletteName::SPECTRA_6->value
        )->firstOrFail();

        $deviceModels = [
            [
                'name' => 'good_display_spectra_6',
                'label' => 'Good Display Spectra 6',
                'description' => 'Dalian Good Display Spectra6 6 color 7.3 inch',
                'width' => 800,
                'height' => 480,
                'colors' => 6,
                'color_type' => 'indexed',
                'bit_depth' => 3,
                'scale_factor' => 1,
                'rotation' => 0,
                'mime_type' => 'image/png',
                'offset_x' => 0,
                'offset_y' => 0,
                'published_at' => '2025-11-01 00:00:00',
                'source' => 'api',
                'palette_id' => $palette->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Upsert by unique 'name' to avoid duplicates and keep data fresh
        DeviceModel::query()->upsert(
            $deviceModels,
            ['name'],
            [
                'label', 'description', 'width', 'height', 'colors', 'bit_depth', 'scale_factor',
                'rotation', 'mime_type', 'offset_x', 'offset_y', 'published_at', 'source',
                'created_at', 'updated_at',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop palette_id foreign key column and color_type
        Schema::table('device_models', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('palette_id');
            $table->dropColumn('color_type');
        });

        // Drop the palette table
        Schema::dropIfExists('palettes');

        $names = [
            'good_display_spectra_6',
        ];

        DeviceModel::query()->whereIn('name', $names)->delete();
    }
};
