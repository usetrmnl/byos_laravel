<?php

namespace App\Jobs;

use App\Models\PlaylistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePlaylistItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $playlistItemId,
        private readonly string $markup
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $newImageUuid = CommonFunctions::generateImage($this->markup);

        PlaylistItem::find($this->playlistItemId)->update(['current_image' => $newImageUuid]);
        \Log::info("Playlist item $this->playlistItemId: updated with new image: $newImageUuid");

        CommonFunctions::cleanupFolder();
    }
}

