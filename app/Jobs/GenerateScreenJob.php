<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Spatie\Browsershot\Browsershot;
use Wnx\SidecarBrowsershot\BrowsershotLambda;

class GenerateScreenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $deviceId,
        private readonly string $markup
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $device = Device::find($this->deviceId);
        $uuid = Uuid::uuid4()->toString();
        $pngPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.png');
        $bmpPath = Storage::disk('public')->path('/images/generated/'.$uuid.'.bmp');

        // Generate PNG
        if (config('app.puppeteer_mode') === 'sidecar-aws') {
            try {
                BrowsershotLambda::html($this->markup)
                    ->windowSize($device->width ?? 800, $device->height ?? 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        } else {
            try {
                Browsershot::html($this->markup)
                    ->setOption('args', config('app.puppeteer_docker') ? ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'] : [])
                    ->windowSize($device->width ?? 800, $device->height ?? 480)
                    ->save($pngPath);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to generate PNG: '.$e->getMessage(), 0, $e);
            }
        }

        try {
            $this->convertToBmpImageMagick($pngPath, $bmpPath);
        } catch (\ImagickException $e) {
            throw new \RuntimeException('Failed to convert image to BMP: '.$e->getMessage(), 0, $e);
        }
        $device->update(['current_screen_image' => $uuid]);
        \Log::info("Device $this->deviceId: updated with new image: $uuid");

        $this->cleanupFolder();
    }

    /**
     * @throws \ImagickException
     */
    private function convertToBmpImageMagick(string $pngPath, string $bmpPath): void
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

    private function cleanupFolder(): void
    {
        $activeImageUuids = Device::pluck('current_screen_image')->filter()->toArray();

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
