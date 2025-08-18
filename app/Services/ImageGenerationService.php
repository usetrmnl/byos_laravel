<?php

namespace App\Services;

use App\Enums\ImageFormat;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Plugin;
use Exception;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;
use ImagickPixel;
use Log;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Wnx\SidecarBrowsershot\BrowsershotLambda;

class ImageGenerationService
{
    public static function generateImage(string $markup, $deviceId): string
    {
        $device = Device::with('deviceModel')->find($deviceId);
        $uuid = Uuid::uuid4()->toString();
        $pngPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.png');
        $bmpPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.bmp');

        // Get image generation settings from DeviceModel if available, otherwise use device settings
        $imageSettings = self::getImageSettings($device);

        // Generate PNG
        if (config('app.puppeteer_mode') === 'sidecar-aws') {
            try {
                $browsershot = BrowsershotLambda::html($markup)
                    ->windowSize(800, 480);

                if (config('app.puppeteer_wait_for_network_idle')) {
                    $browsershot->waitUntilNetworkIdle();
                }

                $browsershot->save($pngPath);
            } catch (Exception $e) {
                Log::error('Failed to generate PNG: '.$e->getMessage());
                throw new RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        } else {
            try {
                $browsershot = Browsershot::html($markup)
                    ->setOption('args', config('app.puppeteer_docker') ? ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'] : []);
                if (config('app.puppeteer_wait_for_network_idle')) {
                    $browsershot->waitUntilNetworkIdle();
                }
                if (config('app.puppeteer_window_size_strategy') == 'v2') {
                    $browsershot->windowSize($imageSettings['width'], $imageSettings['height']);
                } else {
                    $browsershot->windowSize(800, 480);
                }
                $browsershot->save($pngPath);
            } catch (Exception $e) {
                Log::error('Failed to generate PNG: '.$e->getMessage());
                throw new RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        }

        // Convert image based on DeviceModel settings or fallback to device settings
        self::convertImage($pngPath, $bmpPath, $imageSettings);

        $device->update(['current_screen_image' => $uuid]);
        Log::info("Device $device->id: updated with new image: $uuid");

        return $uuid;
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
        return [
            'width' => $device->width ?? 800,
            'height' => $device->height ?? 480,
            'colors' => 2,
            'bit_depth' => 1,
            'scale_factor' => 1.0,
            'rotation' => $device->rotate ?? 0,
            'mime_type' => 'image/png',
            'offset_x' => 0,
            'offset_y' => 0,
            'image_format' => $device->image_format,
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
     * Convert image based on the provided settings
     */
    private static function convertImage(string $pngPath, string $bmpPath, array $settings): void
    {
        $imageFormat = $settings['image_format'];
        $useModelSettings = $settings['use_model_settings'] ?? false;

        if ($useModelSettings) {
            // Use DeviceModel-specific conversion
            self::convertUsingModelSettings($pngPath, $bmpPath, $settings);
        } else {
            // Use legacy device-specific conversion
            self::convertUsingLegacySettings($pngPath, $bmpPath, $imageFormat, $settings);
        }
    }

    /**
     * Convert image using DeviceModel settings
     */
    private static function convertUsingModelSettings(string $pngPath, string $bmpPath, array $settings): void
    {
        try {
            $imagick = new Imagick($pngPath);

            // Apply scale factor if needed
            if ($settings['scale_factor'] !== 1.0) {
                $newWidth = (int) ($settings['width'] * $settings['scale_factor']);
                $newHeight = (int) ($settings['height'] * $settings['scale_factor']);
                $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1, true);
            } else {
                // Resize to model dimensions if different from generated size
                if ($imagick->getImageWidth() !== $settings['width'] || $imagick->getImageHeight() !== $settings['height']) {
                    $imagick->resizeImage($settings['width'], $settings['height'], Imagick::FILTER_LANCZOS, 1, true);
                }
            }

            // Apply rotation
            if ($settings['rotation'] !== 0) {
                $imagick->rotateImage(new ImagickPixel('black'), $settings['rotation']);
            }

            // Apply offset if specified
            if ($settings['offset_x'] !== 0 || $settings['offset_y'] !== 0) {
                $imagick->rollImage($settings['offset_x'], $settings['offset_y']);
            }

            // Handle special case for 4-color, 2-bit PNG
            if ($settings['colors'] === 4 && $settings['bit_depth'] === 2 && $settings['mime_type'] === 'image/png') {
                self::convertTo4Color2BitPng($imagick, $settings['width'], $settings['height']);
            } else {
                // Set image type and color depth based on model settings
                $imagick->setImageType(Imagick::IMGTYPE_GRAYSCALE);

                if ($settings['bit_depth'] === 1) {
                    $imagick->quantizeImage(2, Imagick::COLORSPACE_GRAY, 0, true, false);
                    $imagick->setImageDepth(1);
                } else {
                    $imagick->quantizeImage($settings['colors'], Imagick::COLORSPACE_GRAY, 0, true, false);
                    $imagick->setImageDepth($settings['bit_depth']);
                }
            }

            $imagick->stripImage();

            // Save in the appropriate format
            if ($settings['mime_type'] === 'image/bmp') {
                $imagick->setFormat('BMP3');
                $imagick->writeImage($bmpPath);
            } else {
                $imagick->setFormat('png');
                $imagick->writeImage($pngPath);
            }

            $imagick->clear();
        } catch (ImagickException $e) {
            throw new RuntimeException('Failed to convert image using model settings: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert image to 4-color, 2-bit PNG using custom colormap and dithering
     */
    private static function convertTo4Color2BitPng(Imagick $imagick, int $width, int $height): void
    {
        // Step 1: Create 4-color grayscale colormap in memory
        $colors = ['#000000', '#555555', '#aaaaaa', '#ffffff'];
        $colormap = new Imagick();

        foreach ($colors as $color) {
            $swatch = new Imagick();
            $swatch->newImage(1, 1, new ImagickPixel($color));
            $swatch->setImageFormat('png');
            $colormap->addImage($swatch);
        }

        $colormap = $colormap->appendImages(true); // horizontal
        $colormap->setType(Imagick::IMGTYPE_PALETTE);
        $colormap->setImageFormat('png');

        // Step 2: Resize to target dimensions without keeping aspect ratio
        $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, false);

        // Step 3: Apply Floyd–Steinberg dithering
        $imagick->setOption('dither', 'FloydSteinberg');

        // Step 4: Remap to our 4-color colormap
        // $imagick->remapImage($colormap, Imagick::DITHERMETHOD_FLOYDSTEINBERG);

        // Step 5: Force 2-bit grayscale PNG
        $imagick->setImageFormat('png');
        $imagick->setImageDepth(2);
        $imagick->setType(Imagick::IMGTYPE_GRAYSCALE);

        // Cleanup colormap
        $colormap->clear();
    }

    /**
     * Convert image using legacy device settings
     */
    private static function convertUsingLegacySettings(string $pngPath, string $bmpPath, string $imageFormat, array $settings): void
    {
        switch ($imageFormat) {
            case ImageFormat::BMP3_1BIT_SRGB->value:
                try {
                    self::convertToBmpImageMagick($pngPath, $bmpPath);
                } catch (ImagickException $e) {
                    throw new RuntimeException('Failed to convert image to BMP: '.$e->getMessage(), 0, $e);
                }
                break;
            case ImageFormat::PNG_8BIT_GRAYSCALE->value:
            case ImageFormat::PNG_8BIT_256C->value:
                try {
                    self::convertToPngImageMagick($pngPath, $settings['width'], $settings['height'], $settings['rotation'], quantize: $imageFormat === ImageFormat::PNG_8BIT_GRAYSCALE->value);
                } catch (ImagickException $e) {
                    throw new RuntimeException('Failed to convert image to PNG: '.$e->getMessage(), 0, $e);
                }
                break;
            case ImageFormat::AUTO->value:
            default:
                // For AUTO format, we need to check if this is a legacy device
                // This would require checking if the device has a firmware version
                // For now, we'll use the device's current logic
                try {
                    self::convertToPngImageMagick($pngPath, $settings['width'], $settings['height'], $settings['rotation']);
                } catch (ImagickException $e) {
                    throw new RuntimeException('Failed to convert image to PNG: '.$e->getMessage(), 0, $e);
                }
        }
    }

    /**
     * @throws ImagickException
     */
    private static function convertToBmpImageMagick(string $pngPath, string $bmpPath): void
    {
        $imagick = new Imagick($pngPath);
        $imagick->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        $imagick->quantizeImage(2, Imagick::COLORSPACE_GRAY, 0, true, false);
        $imagick->setImageDepth(1);
        $imagick->stripImage();
        $imagick->setFormat('BMP3');
        $imagick->writeImage($bmpPath);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     */
    private static function convertToPngImageMagick(string $pngPath, ?int $width, ?int $height, ?int $rotate, $quantize = true): void
    {
        $imagick = new Imagick($pngPath);
        if ($width !== 800 || $height !== 480) {
            $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
        }
        if ($rotate !== null && $rotate !== 0) {
            $imagick->rotateImage(new ImagickPixel('black'), $rotate);
        }

        $imagick->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        $imagick->setOption('dither', 'FloydSteinberg');

        if ($quantize) {
            $imagick->quantizeImage(2, Imagick::COLORSPACE_GRAY, 0, true, false);
        }
        $imagick->setImageDepth(8);
        $imagick->stripImage();

        $imagick->setFormat('png');
        $imagick->writeImage($pngPath);
        $imagick->clear();
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
                ->where(function ($query) {
                    $query->where('width', '!=', 800)
                        ->orWhere('height', '!=', 480)
                        ->orWhere('rotate', '!=', 0);
                })
                ->orWhereHas('deviceModel', function ($query) {
                    // Only allow caching if all device models have standard dimensions (800x480, rotation=0)
                    $query->where(function ($subQuery) {
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
