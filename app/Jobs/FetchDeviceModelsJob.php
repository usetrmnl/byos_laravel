<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DeviceModel;
use App\Models\DevicePalette;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class FetchDeviceModelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const API_URL = '/api/models';

    private const PALETTES_API_URL = 'http://usetrmnl.com/api/palettes';

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->processPalettes();

            $response = Http::timeout(30)->get(config('services.trmnl.base_url').self::API_URL);

            if (! $response->successful()) {
                Log::error('Failed to fetch device models from API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $data = $response->json('data', []);

            if (! is_array($data)) {
                Log::error('Invalid response format from device models API', [
                    'response' => $response->json(),
                ]);

                return;
            }

            $this->processDeviceModels($data);

            Log::info('Successfully fetched and updated device models', [
                'count' => count($data),
            ]);

        } catch (Exception $e) {
            Log::error('Exception occurred while fetching device models', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process palettes from API and update/create records.
     */
    private function processPalettes(): void
    {
        try {
            $response = Http::timeout(30)->get(self::PALETTES_API_URL);

            if (! $response->successful()) {
                Log::error('Failed to fetch palettes from API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $data = $response->json('data', []);

            if (! is_array($data)) {
                Log::error('Invalid response format from palettes API', [
                    'response' => $response->json(),
                ]);

                return;
            }

            foreach ($data as $paletteData) {
                try {
                    $this->updateOrCreatePalette($paletteData);
                } catch (Exception $e) {
                    Log::error('Failed to process palette', [
                        'palette_data' => $paletteData,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Successfully fetched and updated palettes', [
                'count' => count($data),
            ]);

        } catch (Exception $e) {
            Log::error('Exception occurred while fetching palettes', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Update or create a palette record.
     */
    private function updateOrCreatePalette(array $paletteData): void
    {
        $name = $paletteData['id'] ?? null;

        if (! $name) {
            Log::warning('Palette data missing id field', [
                'palette_data' => $paletteData,
            ]);

            return;
        }

        $attributes = [
            'name' => $name,
            'description' => $paletteData['name'] ?? '',
            'grays' => $paletteData['grays'] ?? 2,
            'colors' => $paletteData['colors'] ?? null,
            'framework_class' => $paletteData['framework_class'] ?? '',
            'source' => 'api',
        ];

        DevicePalette::updateOrCreate(
            ['name' => $name],
            $attributes
        );
    }

    /**
     * Process the device models data and update/create records.
     */
    private function processDeviceModels(array $deviceModels): void
    {
        foreach ($deviceModels as $modelData) {
            try {
                $this->updateOrCreateDeviceModel($modelData);
            } catch (Exception $e) {
                Log::error('Failed to process device model', [
                    'model_data' => $modelData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update or create a device model record.
     */
    private function updateOrCreateDeviceModel(array $modelData): void
    {
        $name = $modelData['name'] ?? null;

        if (! $name) {
            Log::warning('Device model data missing name field', [
                'model_data' => $modelData,
            ]);

            return;
        }

        $attributes = [
            'label' => $modelData['label'] ?? '',
            'description' => $modelData['description'] ?? '',
            'width' => $modelData['width'] ?? 0,
            'height' => $modelData['height'] ?? 0,
            'colors' => $modelData['colors'] ?? 0,
            'bit_depth' => $modelData['bit_depth'] ?? 0,
            'scale_factor' => $modelData['scale_factor'] ?? 1,
            'rotation' => $modelData['rotation'] ?? 0,
            'mime_type' => $modelData['mime_type'] ?? '',
            'offset_x' => $modelData['offset_x'] ?? 0,
            'offset_y' => $modelData['offset_y'] ?? 0,
            'published_at' => $modelData['published_at'] ?? null,
            'kind' => $modelData['kind'] ?? null,
            'source' => 'api',
        ];

        // Set palette_id to the first palette from the model's palettes array
        $firstPaletteId = $this->getFirstPaletteId($modelData);
        if ($firstPaletteId) {
            $attributes['palette_id'] = $firstPaletteId;
        }

        $attributes['css_name'] = $this->parseCssNameFromApi($modelData['css'] ?? null);
        $attributes['css_variables'] = $this->parseCssVariablesFromApi($modelData['css'] ?? null);

        DeviceModel::updateOrCreate(
            ['name' => $name],
            $attributes
        );
    }

    /**
     * Extract css_name from API css payload (strip "screen--" prefix from classes.device).
     */
    private function parseCssNameFromApi(mixed $css): ?string
    {
        $deviceClass = is_array($css) ? Arr::get($css, 'classes.device') : null;

        return (is_string($deviceClass) ? Str::after($deviceClass, 'screen--') : null) ?: null;
    }

    /**
     * Extract css_variables from API css payload (convert [[key, value], ...] to associative array).
     */
    private function parseCssVariablesFromApi(mixed $css): ?array
    {
        $pairs = is_array($css) ? Arr::get($css, 'variables', []) : [];
        if (! is_array($pairs)) {
            return null;
        }

        $validPairs = Arr::where($pairs, fn (mixed $pair): bool => is_array($pair) && isset($pair[0], $pair[1]));
        $variables = Arr::pluck($validPairs, 1, 0);

        return $variables !== [] ? $variables : null;
    }

    /**
     * Get the first palette ID from model data.
     */
    private function getFirstPaletteId(array $modelData): ?int
    {
        $paletteName = null;

        // Check for palette_ids array
        if (isset($modelData['palette_ids']) && is_array($modelData['palette_ids']) && $modelData['palette_ids'] !== []) {
            $paletteName = $modelData['palette_ids'][0];
        }

        // Check for palettes array (array of objects with id)
        if (! $paletteName && isset($modelData['palettes']) && is_array($modelData['palettes']) && $modelData['palettes'] !== []) {
            $firstPalette = $modelData['palettes'][0];
            if (is_array($firstPalette) && isset($firstPalette['id'])) {
                $paletteName = $firstPalette['id'];
            }
        }

        if (! $paletteName) {
            return null;
        }

        // Look up palette by name to get the integer ID
        $palette = DevicePalette::where('name', $paletteName)->first();

        return $palette?->id;
    }
}
