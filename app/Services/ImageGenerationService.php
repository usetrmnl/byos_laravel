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
use InvalidArgumentException;
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
        $device = Device::with(['deviceModel', 'palette', 'deviceModel.palette', 'user'])->find($deviceId);
        $uuid = self::generateImageFromModel(
            markup: $markup,
            deviceModel: $device->deviceModel,
            user: $device->user,
            palette: $device->palette ?? $device->deviceModel?->palette,
            device: $device
        );

        $device->update(['current_screen_image' => $uuid]);
        Log::info("Device $device->id: updated with new image: $uuid");

        return $uuid;
    }

    /**
     * Generate an image from markup using a DeviceModel
     *
     * @param  string  $markup  The HTML markup to render
     * @param  DeviceModel|null  $deviceModel  The device model to use for image generation
     * @param  \App\Models\User|null  $user  Optional user for timezone settings
     * @param  \App\Models\DevicePalette|null  $palette  Optional palette, falls back to device model's palette
     * @param  Device|null  $device  Optional device for legacy devices without DeviceModel
     * @return string The UUID of the generated image
     */
    public static function generateImageFromModel(
        string $markup,
        ?DeviceModel $deviceModel = null,
        ?\App\Models\User $user = null,
        ?\App\Models\DevicePalette $palette = null,
        ?Device $device = null
    ): string {
        $uuid = Uuid::uuid4()->toString();

        try {
            // Get image generation settings from DeviceModel or Device (for legacy devices)
            $imageSettings = $deviceModel instanceof DeviceModel
                ? self::getImageSettingsFromModel($deviceModel)
                : ($device instanceof Device ? self::getImageSettings($device) : self::getImageSettingsFromModel(null));

            $fileExtension = $imageSettings['mime_type'] === 'image/bmp' ? 'bmp' : 'png';
            $outputPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.'.$fileExtension);

            // Create custom Browsershot instance if using AWS Lambda
            $browsershotInstance = null;
            if (config('app.puppeteer_mode') === 'sidecar-aws') {
                $browsershotInstance = new BrowsershotLambda();
            }

            $browserStage = new BrowserStage($browsershotInstance);
            $browserStage->html($markup);

            // Set timezone from user or fall back to app timezone
            $timezone = $user->timezone ?? config('app.timezone');
            $browserStage->timezone($timezone);

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

            // Get palette from parameter or fallback to device model's default palette
            $colorPalette = null;
            if ($palette && $palette->colors) {
                $colorPalette = $palette->colors;
            } elseif ($deviceModel?->palette && $deviceModel->palette->colors) {
                $colorPalette = $deviceModel->palette->colors;
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

            // Apply color palette if available
            if ($colorPalette) {
                $imageStage->colormap($colorPalette);
            }

            // Apply dithering if requested by markup
            $shouldDither = self::markupContainsDitherImage($markup);
            if ($shouldDither) {
                $imageStage->dither();
            }

            (new TrmnlPipeline())->pipe($browserStage)
                ->pipe($imageStage)
                ->process();

            if (! file_exists($outputPath)) {
                throw new RuntimeException('Image file was not created: '.$outputPath);
            }

            if (filesize($outputPath) === 0) {
                throw new RuntimeException('Image file is empty: '.$outputPath);
            }

            Log::info("Generated image: $uuid");

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
            return self::getImageSettingsFromModel($device->deviceModel);
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
     * Get image generation settings from a DeviceModel
     */
    private static function getImageSettingsFromModel(?DeviceModel $deviceModel): array
    {
        if ($deviceModel instanceof DeviceModel) {
            return [
                'width' => $deviceModel->width,
                'height' => $deviceModel->height,
                'colors' => $deviceModel->colors,
                'bit_depth' => $deviceModel->bit_depth,
                'scale_factor' => $deviceModel->scale_factor,
                'rotation' => $deviceModel->rotation,
                'mime_type' => $deviceModel->mime_type,
                'offset_x' => $deviceModel->offset_x,
                'offset_y' => $deviceModel->offset_y,
                'image_format' => self::determineImageFormatFromModel($deviceModel),
                'use_model_settings' => true,
            ];
        }

        // Default settings if no device model provided
        return [
            'width' => 800,
            'height' => 480,
            'colors' => 2,
            'bit_depth' => 1,
            'scale_factor' => 1.0,
            'rotation' => 0,
            'mime_type' => 'image/png',
            'offset_x' => 0,
            'offset_y' => 0,
            'image_format' => ImageFormat::AUTO->value,
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

    /**
     * Detect whether the provided HTML markup contains an <img> tag with class "image-dither".
     */
    private static function markupContainsDitherImage(string $markup): bool
    {
        if (mb_trim($markup) === '') {
            return false;
        }

        // Find <img ... class="..."> (or with single quotes) and inspect class tokens
        $imgWithClassPattern = '/<img\b[^>]*\bclass\s*=\s*(["\'])(.*?)\1[^>]*>/i';
        if (! preg_match_all($imgWithClassPattern, $markup, $matches)) {
            return false;
        }

        foreach ($matches[2] as $classValue) {
            // Look for class token 'image-dither' or 'image--dither'
            if (preg_match('/(?:^|\s)image--?dither(?:\s|$)/', $classValue)) {
                return true;
            }
        }

        return false;
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
            // Image webhook plugins have finalized images that shouldn't be reset
            if ($plugin->plugin_type === 'image_webhook') {
                return;
            }
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

    /**
     * Get device-specific default image path for setup or sleep mode
     */
    public static function getDeviceSpecificDefaultImage(Device $device, string $imageType): ?string
    {
        // Validate image type
        if (! in_array($imageType, ['setup-logo', 'sleep', 'error'])) {
            return null;
        }

        // If device has a DeviceModel, try to find device-specific image
        if ($device->deviceModel) {
            $model = $device->deviceModel;
            $extension = $model->mime_type === 'image/bmp' ? 'bmp' : 'png';
            $filename = "{$model->width}_{$model->height}_{$model->bit_depth}_{$model->rotation}.{$extension}";
            $deviceSpecificPath = "images/default-screens/{$imageType}_{$filename}";

            if (Storage::disk('public')->exists($deviceSpecificPath)) {
                return $deviceSpecificPath;
            }
        }

        // Fallback to original hardcoded images
        $fallbackPath = "images/{$imageType}.bmp";
        if (Storage::disk('public')->exists($fallbackPath)) {
            return $fallbackPath;
        }

        // Try PNG fallback
        $fallbackPathPng = "images/{$imageType}.png";
        if (Storage::disk('public')->exists($fallbackPathPng)) {
            return $fallbackPathPng;
        }

        return null;
    }

    /**
     * Generate a default screen image from Blade template
     */
    public static function generateDefaultScreenImage(Device $device, string $imageType, ?string $pluginName = null): string
    {
        // Validate image type
        if (! in_array($imageType, ['setup-logo', 'sleep', 'error'])) {
            throw new InvalidArgumentException("Invalid image type: {$imageType}");
        }

        $uuid = Uuid::uuid4()->toString();

        try {
            // Load device with relationships
            $device->load(['palette', 'deviceModel.palette', 'user']);

            // Get image generation settings from DeviceModel if available, otherwise use device settings
            $imageSettings = self::getImageSettings($device);

            $fileExtension = $imageSettings['mime_type'] === 'image/bmp' ? 'bmp' : 'png';
            $outputPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.'.$fileExtension);

            // Generate HTML from Blade template
            $html = self::generateDefaultScreenHtml($device, $imageType, $pluginName);

            // Create custom Browsershot instance if using AWS Lambda
            $browsershotInstance = null;
            if (config('app.puppeteer_mode') === 'sidecar-aws') {
                $browsershotInstance = new BrowsershotLambda();
            }

            $browserStage = new BrowserStage($browsershotInstance);
            $browserStage->html($html);

            // Set timezone from user or fall back to app timezone
            $timezone = $device->user->timezone ?? config('app.timezone');
            $browserStage->timezone($timezone);

            if (config('app.puppeteer_window_size_strategy') === 'v2') {
                $browserStage
                    ->width($imageSettings['width'])
                    ->height($imageSettings['height']);
            } else {
                $browserStage->useDefaultDimensions();
            }

            if (config('app.puppeteer_wait_for_network_idle')) {
                $browserStage->setBrowsershotOption('waitUntil', 'networkidle0');
            }

            if (config('app.puppeteer_docker')) {
                $browserStage->setBrowsershotOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']);
            }

            // Get palette from device or fallback to device model's default palette
            $palette = $device->palette ?? $device->deviceModel?->palette;
            $colorPalette = null;

            if ($palette && $palette->colors) {
                $colorPalette = $palette->colors;
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

            // Apply color palette if available
            if ($colorPalette) {
                $imageStage->colormap($colorPalette);
            }

            (new TrmnlPipeline())->pipe($browserStage)
                ->pipe($imageStage)
                ->process();

            if (! file_exists($outputPath)) {
                throw new RuntimeException('Image file was not created: '.$outputPath);
            }

            if (filesize($outputPath) === 0) {
                throw new RuntimeException('Image file is empty: '.$outputPath);
            }

            Log::info("Device $device->id: generated default screen image: $uuid for type: $imageType");

            return $uuid;

        } catch (Exception $e) {
            Log::error('Failed to generate default screen image: '.$e->getMessage());
            throw new RuntimeException('Failed to generate default screen image: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate HTML from Blade template for default screens
     */
    private static function generateDefaultScreenHtml(Device $device, string $imageType, ?string $pluginName = null): string
    {
        // Map image type to template name
        $templateName = match ($imageType) {
            'setup-logo' => 'default-screens.setup',
            'sleep' => 'default-screens.sleep',
            'error' => 'default-screens.error',
            default => throw new InvalidArgumentException("Invalid image type: {$imageType}")
        };

        // Determine device properties from DeviceModel or device settings
        $deviceVariant = $device->deviceModel?->css_name ?? $device->deviceVariant();
        $deviceOrientation = $device->rotate > 0 ? 'portrait' : 'landscape';
        $colorDepth = $device->colorDepth() ?? '1bit';
        $scaleLevel = $device->scaleLevel();
        $darkMode = $imageType === 'sleep'; // Sleep mode uses dark mode, setup uses light mode

        // Build view data
        $viewData = [
            'noBleed' => false,
            'darkMode' => $darkMode,
            'deviceVariant' => $deviceVariant,
            'deviceOrientation' => $deviceOrientation,
            'colorDepth' => $colorDepth,
            'scaleLevel' => $scaleLevel,
            'cssVariables' => $device->deviceModel?->css_variables ?? [],
        ];

        // Add plugin name for error screens
        if ($imageType === 'error' && $pluginName !== null) {
            $viewData['pluginName'] = $pluginName;
        }

        // Render the Blade template
        return view($templateName, $viewData)->render();
    }
}
