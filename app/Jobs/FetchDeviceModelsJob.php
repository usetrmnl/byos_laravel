<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DeviceModel;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FetchDeviceModelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const API_URL = 'https://usetrmnl.com/api/models';

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
            $response = Http::timeout(30)->get(self::API_URL);

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
            'source' => 'api',
        ];

        DeviceModel::updateOrCreate(
            ['name' => $name],
            $attributes
        );
    }
}
