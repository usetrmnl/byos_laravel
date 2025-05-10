<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Plugin;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Spatie\Browsershot\Browsershot;
use Wnx\SidecarBrowsershot\BrowsershotLambda;

class CommonFunctions
{
    public static function generateImage(string $markup): string {
        $uuid = Uuid::uuid4()->toString();
        $pngPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.png');
        $bmpPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.bmp');

        // Generate PNG
        if (config('app.puppeteer_mode') === 'sidecar-aws') {
            try {
                BrowsershotLambda::html($markup)
                    ->windowSize($device->width ?? 800, $device->height ?? 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        } else {
            try {
                Browsershot::html($markup)
                    ->setOption('args', config('app.puppeteer_docker') ? ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'] : [])
                    ->windowSize($device->width ?? 800, $device->height ?? 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        }

        try {
            CommonFunctions::convertToBmpImageMagick($pngPath, $bmpPath);
        } catch (\ImagickException $e) {
            throw new \RuntimeException('Failed to convert image to BMP: '.$e->getMessage(), 0, $e);
        }
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
}
