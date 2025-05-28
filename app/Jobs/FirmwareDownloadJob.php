<?php

namespace App\Jobs;

use App\Models\Firmware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FirmwareDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Firmware $firmware;

    public function __construct(Firmware $firmware)
    {
        $this->firmware = $firmware;
    }

    public function handle(): void
    {
        if (! Storage::disk('public')->exists('firmwares')) {
            Storage::disk('public')->makeDirectory('firmwares');
        }

        try {
            $filename = "FW{$this->firmware->version_tag}.bin";
            Http::sink(storage_path("app/public/firmwares/$filename"))
                ->get($this->firmware->url);

            $this->firmware->update([
                'storage_location' => "firmwares/$filename",
            ]);
        } catch (ConnectionException $e) {
            Log::error('Firmware download failed: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('An unexpected error occurred: '.$e->getMessage());
        }
    }
} 