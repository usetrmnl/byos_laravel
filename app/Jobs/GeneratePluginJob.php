<?php

namespace App\Jobs;

use App\Models\Plugin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePluginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $pluginId,
        private readonly string $markup
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $newImageUuid = CommonFunctions::generateImage($this->markup);

        Plugin::find($this->pluginId)->update(['current_image' => $newImageUuid]);
        \Log::info("Plugin $this->pluginId: updated with new image: $newImageUuid");

        CommonFunctions::cleanupFolder();
    }
}

