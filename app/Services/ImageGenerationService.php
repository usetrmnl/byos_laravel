<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Plugin;
use Illuminate\Support\Facades\Storage;
use ImagickPixel;
use Ramsey\Uuid\Uuid;
use Spatie\Browsershot\Browsershot;
use Wnx\SidecarBrowsershot\BrowsershotLambda;

class ImageGenerationService
{
    public static function generateImage(string $markup, $deviceId): string
    {
        $device = Device::find($deviceId);
        $uuid = Uuid::uuid4()->toString();
        $pngPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.png');
        $bmpPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.bmp');

        // Generate PNG
        if (config('app.puppeteer_mode') === 'sidecar-aws') {
            try {
                BrowsershotLambda::html($markup)
                    ->windowSize(800, 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        } else {
            try {
                Browsershot::html($markup)
                    ->setOption('args', config('app.puppeteer_docker') ? ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'] : [])
                    ->windowSize(800, 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        }

        if (isset($device->last_firmware_version)
            && version_compare($device->last_firmware_version, '1.5.2', '<')) {
            try {
                ImageGenerationService::convertToBmpImageMagick($pngPath, $bmpPath);
            } catch (\ImagickException $e) {
                throw new \RuntimeException('Failed to convert image to BMP: '.$e->getMessage(), 0, $e);
            }
        } else {
            try {
                ImageGenerationService::convertToPngImageMagick($pngPath, $device->width, $device->height, $device->rotate);
            } catch (\ImagickException $e) {
                throw new \RuntimeException('Failed to convert image to PNG: '.$e->getMessage(), 0, $e);
            }
        }
        $device->update(['current_screen_image' => $uuid]);
        \Log::info("Device $device->id: updated with new image: $uuid");

        return $uuid;
    }

    /**
     * @throws \ImagickException
     */
    private static function convertToBmpImageMagick(string $pngPath, string $bmpPath): void
    {
        $imagick = new \Imagick($pngPath);
        $imagick->setImageType(\Imagick::IMGTYPE_GRAYSCALE);
        $imagick->quantizeImage(2, \Imagick::COLORSPACE_GRAY, 0, true, false);
        $imagick->setImageDepth(1);
        $imagick->stripImage();
        $imagick->setFormat('BMP3');
        $imagick->writeImage($bmpPath);
        $imagick->clear();
    }

    /**
     * @throws \ImagickException
     */
    private static function convertToPngImageMagick(string $pngPath, ?int $width, ?int $height, ?int $rotate): void
    {
        $imagick = new \Imagick($pngPath);
        if ($width !== 800 || $height !== 480) {
            $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, true);
        }
        if ($rotate !== null && $rotate !== 0) {
            $imagick->rotateImage(new ImagickPixel('black'), $rotate);
        }
        $imagick->setImageType(\Imagick::IMGTYPE_GRAYSCALE);
        $imagick->quantizeImage(2, \Imagick::COLORSPACE_GRAY, 0, true, false);
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
            if (
                Device::query()
                    ->where('width', '!=', 800)
                    ->orWhere('height', '!=', 480)
                    ->orWhere('rotate', '!=', 0)
                    ->exists()
            ) {
                // TODO cache image per device
                $plugin->update(['current_image' => null]);
                \Log::debug('Skip cache as devices with other dimensions exist');
            }
        }
    }
}
