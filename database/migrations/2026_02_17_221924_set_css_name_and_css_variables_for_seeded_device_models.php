<?php

use App\Models\DeviceModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * CSS name and variables for device models created by seed_device_models (og_png until inky_impression_13_3).
     *
     * @var array<string, array{css_name: string, css_variables: array<string, string>}>
     */
    private const SEEDED_CSS = [
        'og_png' => [
            'css_name' => 'og_png',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '480px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'og_plus' => [
            'css_name' => 'ogv2',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '480px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'amazon_kindle_2024' => [
            'css_name' => 'amazon_kindle_2024',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '480px',
                '--ui-scale' => '0.8',
                '--gap-scale' => '1.0',
            ],
        ],
        'amazon_kindle_paperwhite_6th_gen' => [
            'css_name' => 'amazon_kindle_paperwhite_6th_gen',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '600px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'amazon_kindle_paperwhite_7th_gen' => [
            'css_name' => 'amazon_kindle_paperwhite_7th_gen',
            'css_variables' => [
                '--screen-w' => '905px',
                '--screen-h' => '670px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'inkplate_10' => [
            'css_name' => 'inkplate_10',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '547px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'amazon_kindle_7' => [
            'css_name' => 'amazon_kindle_7',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '600px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'inky_impression_7_3' => [
            'css_name' => 'inky_impression_7_3',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '480px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'kobo_libra_2' => [
            'css_name' => 'kobo_libra_2',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '602px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'amazon_kindle_oasis_2' => [
            'css_name' => 'amazon_kindle_oasis_2',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '602px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'kobo_aura_one' => [
            'css_name' => 'kobo_aura_one',
            'css_variables' => [
                '--screen-w' => '1040px',
                '--screen-h' => '780px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'kobo_aura_hd' => [
            'css_name' => 'kobo_aura_hd',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '600px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
        'inky_impression_13_3' => [
            'css_name' => 'inky_impression_13_3',
            'css_variables' => [
                '--screen-w' => '800px',
                '--screen-h' => '600px',
                '--ui-scale' => '1.0',
                '--gap-scale' => '1.0',
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::SEEDED_CSS as $name => $payload) {
            DeviceModel::query()
                ->where('name', $name)
                ->update([
                    'css_name' => $payload['css_name'],
                    'css_variables' => $payload['css_variables'],
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DeviceModel::query()
            ->whereIn('name', array_keys(self::SEEDED_CSS))
            ->update([
                'css_name' => null,
                'css_variables' => null,
            ]);
    }
};
