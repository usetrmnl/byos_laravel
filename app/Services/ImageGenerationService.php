<?php

namespace App\Services;

use App\Enums\ImageFormat;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Plugin;
use Bnussbau\TrmnlPipeline\Stages\BrowserStage;
use Bnussbau\TrmnlPipeline\Stages\ImageStage;
use Bnussbau\TrmnlPipeline\TrmnlPipeline;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Wnx\SidecarBrowsershot\BrowsershotLambda;

use function config;
use function file_exists;
use function filesize;

class ImageGenerationService
{
    public static function generateImage(string $markup, $deviceId): string
    {
        $device = Device::with('deviceModel')->find($deviceId);
        $uuid = Uuid::uuid4()->toString();

        try {
            // Get image generation settings from DeviceModel if available, otherwise use device settings
            $imageSettings = self::getImageSettings($device);

            $fileExtension = $imageSettings['mime_type'] === 'image/bmp' ? 'bmp' : 'png';
            $outputPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.'.$fileExtension);

            // Create custom Browsershot instance if using AWS Lambda
            $browsershotInstance = null;
            if (config('app.puppeteer_mode') === 'sidecar-aws') {
                $browsershotInstance = new BrowsershotLambda();
            }

            $browserStage = new BrowserStage($browsershotInstance);
            $browserStage->html($markup);

            if (config('app.puppeteer_window_size_strategy') === 'v2') {
                $browserStage
                    ->width($imageSettings['width'])
                    ->height($imageSettings['height']);
            } else {
                // default behaviour for Framework v1
                $browserStage->useDefaultDimensions();
            }

            if (config('app.puppeteer_wait_for_network_idle')) {
                $browserStage->setBrowsershotOption('waitUntil', 'networkidle0');
            }

            if (config('app.puppeteer_docker')) {
                $browserStage->setBrowsershotOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']);
            }

            $imageStage = new ImageStage();
            $imageStage->format($fileExtension)
                ->width($imageSettings['width'])
                ->height($imageSettings['height'])
                ->colors($imageSettings['colors'])
                ->bitDepth($imageSettings['bit_depth'])
                ->rotation($imageSettings['rotation'])
                ->offsetX($imageSettings['offset_x'])
                ->offsetY($imageSettings['offset_y'])
                ->outputPath($outputPath);

            (new TrmnlPipeline())->pipe($browserStage)
                ->pipe($imageStage)
                ->process();

            if (! file_exists($outputPath)) {
                throw new RuntimeException('Image file was not created: '.$outputPath);
            }

            if (filesize($outputPath) === 0) {
                throw new RuntimeException('Image file is empty: '.$outputPath);
            }

            $device->update(['current_screen_image' => $uuid]);
            Log::info("Device $device->id: updated with new image: $uuid");

            return $uuid;

        } catch (Exception $e) {
            Log::error('Failed to generate image: '.$e->getMessage());
            throw new RuntimeException('Failed to generate image: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get image generation settings from DeviceModel if available, otherwise use device settings
     */
    private static function getImageSettings(Device $device): array
    {
        // If device has a DeviceModel, use its settings
        if ($device->deviceModel) {
            /** @var DeviceModel $model */
            $model = $device->deviceModel;

            return [
                'width' => $model->width,
                'height' => $model->height,
                'colors' => $model->colors,
                'bit_depth' => $model->bit_depth,
                'scale_factor' => $model->scale_factor,
                'rotation' => $model->rotation,
                'mime_type' => $model->mime_type,
                'offset_x' => $model->offset_x,
                'offset_y' => $model->offset_y,
                'image_format' => self::determineImageFormatFromModel($model),
                'use_model_settings' => true,
            ];
        }

        // Fallback to device settings
        $imageFormat = $device->image_format ?? ImageFormat::AUTO->value;
        $mimeType = self::getMimeTypeFromImageFormat($imageFormat);
        $colors = self::getColorsFromImageFormat($imageFormat);
        $bitDepth = self::getBitDepthFromImageFormat($imageFormat);

        return [
            'width' => $device->width ?? 800,
            'height' => $device->height ?? 480,
            'colors' => $colors,
            'bit_depth' => $bitDepth,
            'scale_factor' => 1.0,
            'rotation' => $device->rotate ?? 0,
            'mime_type' => $mimeType,
            'offset_x' => 0,
            'offset_y' => 0,
            'image_format' => $imageFormat,
            'use_model_settings' => false,
        ];
    }

    /**
     * Determine the appropriate ImageFormat based on DeviceModel settings
     */
    private static function determineImageFormatFromModel(DeviceModel $model): string
    {
        // Map DeviceModel settings to ImageFormat
        if ($model->mime_type === 'image/bmp' && $model->bit_depth === 1) {
            return ImageFormat::BMP3_1BIT_SRGB->value;
        }
        if ($model->mime_type === 'image/png' && $model->bit_depth === 8 && $model->colors === 2) {
            return ImageFormat::PNG_8BIT_GRAYSCALE->value;
        }
        if ($model->mime_type === 'image/png' && $model->bit_depth === 8 && $model->colors === 256) {
            return ImageFormat::PNG_8BIT_256C->value;
        }
        if ($model->mime_type === 'image/png' && $model->bit_depth === 2 && $model->colors === 4) {
            return ImageFormat::PNG_2BIT_4C->value;
        }

        // Default to AUTO for unknown combinations
        return ImageFormat::AUTO->value;
    }

    /**
     * Get MIME type from ImageFormat
     */
    private static function getMimeTypeFromImageFormat(string $imageFormat): string
    {
        return match ($imageFormat) {
            ImageFormat::BMP3_1BIT_SRGB->value => 'image/bmp',
            ImageFormat::PNG_8BIT_GRAYSCALE->value,
            ImageFormat::PNG_8BIT_256C->value,
            ImageFormat::PNG_2BIT_4C->value => 'image/png',
            ImageFormat::AUTO->value => 'image/png', // Default for AUTO
            default => 'image/png',
        };
    }

    /**
     * Get colors from ImageFormat
     */
    private static function getColorsFromImageFormat(string $imageFormat): int
    {
        return match ($imageFormat) {
            ImageFormat::BMP3_1BIT_SRGB->value,
            ImageFormat::PNG_8BIT_GRAYSCALE->value => 2,
            ImageFormat::PNG_8BIT_256C->value => 256,
            ImageFormat::PNG_2BIT_4C->value => 4,
            ImageFormat::AUTO->value => 2, // Default for AUTO
            default => 2,
        };
    }

    /**
     * Get bit depth from ImageFormat
     */
    private static function getBitDepthFromImageFormat(string $imageFormat): int
    {
        return match ($imageFormat) {
            ImageFormat::BMP3_1BIT_SRGB->value,
            ImageFormat::PNG_8BIT_GRAYSCALE->value => 1,
            ImageFormat::PNG_8BIT_256C->value => 8,
            ImageFormat::PNG_2BIT_4C->value => 2,
            ImageFormat::AUTO->value => 1, // Default for AUTO
            default => 1,
        };
    }

    public static function cleanupFolder(): void
    {
        $activeDeviceImageUuids = Device::pluck('current_screen_image')->filter()->toArray();
        $activePluginImageUuids = Plugin::pluck('current_image')->filter()->toArray();
        $activeImageUuids = array_merge($activeDeviceImageUuids, $activePluginImageUuids);

        $files = Storage::disk('public')->files('/images/generated/');
        foreach ($files as $file) {
            if (basename($file) === '.gitignore') {
                continue;
            }
            // Get filename without path and extension
            $fileUuid = pathinfo($file, PATHINFO_FILENAME);
            // If the UUID is not in use by any device, move it to archive
            if (! in_array($fileUuid, $activeImageUuids)) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    public static function resetIfNotCacheable(?Plugin $plugin): void
    {
        if ($plugin?->id) {
            // Check if any devices have custom dimensions or use non-standard DeviceModels
            $hasCustomDimensions = Device::query()
                ->where(function ($query): void {
                    $query->where('width', '!=', 800)
                        ->orWhere('height', '!=', 480)
                        ->orWhere('rotate', '!=', 0);
                })
                ->orWhereHas('deviceModel', function ($query): void {
                    // Only allow caching if all device models have standard dimensions (800x480, rotation=0)
                    $query->where(function ($subQuery): void {
                        $subQuery->where('width', '!=', 800)
                            ->orWhere('height', '!=', 480)
                            ->orWhere('rotation', '!=', 0);
                    });
                })
                ->exists();

            if ($hasCustomDimensions) {
                // TODO cache image per device
                $plugin->update(['current_image' => null]);
                Log::debug('Skip cache as devices with custom dimensions or non-standard DeviceModels exist');
            }
        }
    }
}
