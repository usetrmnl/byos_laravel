<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Plugin;
use App\Services\ImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateScreenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $deviceId,
        private readonly ?int $pluginId,
        private readonly string $markup
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $newImageUuid = ImageGenerationService::generateImage($this->markup, $this->deviceId);

        Device::find($this->deviceId)->update(['current_screen_image' => $newImageUuid]);

        if ($this->pluginId) {
            // cache current image
            Plugin::find($this->pluginId)->update(['current_image' => $newImageUuid]);
        }

        ImageGenerationService::cleanupFolder();
    }
}
