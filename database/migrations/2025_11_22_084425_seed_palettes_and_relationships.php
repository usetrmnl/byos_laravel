<?php

use App\Models\DeviceModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed palettes from hardcoded data
        // name = identifier, description = human-readable name
        $palettes = [
            [
                'name' => 'bw',
                'description' => 'Black & White',
                'grays' => 2,
                'colors' => null,
                'framework_class' => 'screen--1bit',
                'source' => 'api',
            ],
            [
                'name' => 'gray-4',
                'description' => '4 Grays',
                'grays' => 4,
                'colors' => null,
                'framework_class' => 'screen--2bit',
                'source' => 'api',
            ],
            [
                'name' => 'gray-16',
                'description' => '16 Grays',
                'grays' => 16,
                'colors' => null,
                'framework_class' => 'screen--4bit',
                'source' => 'api',
            ],
            [
                'name' => 'gray-256',
                'description' => '256 Grays',
                'grays' => 256,
                'colors' => null,
                'framework_class' => 'screen--4bit',
                'source' => 'api',
            ],
            [
                'name' => 'color-6a',
                'description' => '6 Colors',
                'grays' => 2,
                'colors' => json_encode(['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#000000', '#FFFFFF']),
                'framework_class' => '',
                'source' => 'api',
            ],
            [
                'name' => 'color-7a',
                'description' => '7 Colors',
                'grays' => 2,
                'colors' => json_encode(['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FFA500']),
                'framework_class' => '',
                'source' => 'api',
            ],
        ];

        $now = now();
        $paletteIdMap = [];

        foreach ($palettes as $paletteData) {
            $paletteName = $paletteData['name'];
            $paletteData['created_at'] = $now;
            $paletteData['updated_at'] = $now;

            DB::table('device_palettes')->updateOrInsert(
                ['name' => $paletteName],
                $paletteData
            );

            // Get the ID of the palette (either newly created or existing)
            $paletteRecord = DB::table('device_palettes')->where('name', $paletteName)->first();
            $paletteIdMap[$paletteName] = $paletteRecord->id;
        }

        // Set default palette_id on DeviceModel based on first palette_ids entry
        $models = [
            ['name' => 'og_png', 'palette_name' => 'bw'],
            ['name' => 'og_plus', 'palette_name' => 'gray-4'],
            ['name' => 'amazon_kindle_2024', 'palette_name' => 'gray-256'],
            ['name' => 'amazon_kindle_paperwhite_6th_gen', 'palette_name' => 'gray-256'],
            ['name' => 'amazon_kindle_paperwhite_7th_gen', 'palette_name' => 'gray-256'],
            ['name' => 'inkplate_10', 'palette_name' => 'gray-4'],
            ['name' => 'amazon_kindle_7', 'palette_name' => 'gray-256'],
            ['name' => 'inky_impression_7_3', 'palette_name' => 'color-7a'],
            ['name' => 'kobo_libra_2', 'palette_name' => 'gray-16'],
            ['name' => 'amazon_kindle_oasis_2', 'palette_name' => 'gray-256'],
            ['name' => 'kobo_aura_one', 'palette_name' => 'gray-16'],
            ['name' => 'kobo_aura_hd', 'palette_name' => 'gray-16'],
            ['name' => 'inky_impression_13_3', 'palette_name' => 'color-6a'],
            ['name' => 'm5_paper_s3', 'palette_name' => 'gray-16'],
            ['name' => 'amazon_kindle_scribe', 'palette_name' => 'gray-256'],
            ['name' => 'seeed_e1001', 'palette_name' => 'gray-4'],
            ['name' => 'seeed_e1002', 'palette_name' => 'gray-4'],
            ['name' => 'waveshare_4_26', 'palette_name' => 'gray-4'],
            ['name' => 'waveshare_7_5_bw', 'palette_name' => 'bw'],
        ];

        foreach ($models as $modelData) {
            $deviceModel = DeviceModel::where('name', $modelData['name'])->first();
            if ($deviceModel && ! $deviceModel->palette_id && isset($paletteIdMap[$modelData['palette_name']])) {
                $deviceModel->update(['palette_id' => $paletteIdMap[$modelData['palette_name']]]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove palette_id from device models but keep palettes
        DeviceModel::query()->update(['palette_id' => null]);
    }
};
