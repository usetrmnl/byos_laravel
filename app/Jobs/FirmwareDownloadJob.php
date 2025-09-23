<?php

namespace App\Jobs;

use App\Models\Firmware;
use Exception;
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
            $response = Http::get($this->firmware->url);

            if (! $response->successful()) {
                throw new Exception('HTTP request failed with status: '.$response->status());
            }

            // Save the response content to file
            Storage::disk('public')->put("firmwares/$filename", $response->body());

            // Only update storage location if download was successful
            $this->firmware->update([
                'storage_location' => "firmwares/$filename",
            ]);
        } catch (ConnectionException $e) {
            Log::error('Firmware download failed: '.$e->getMessage());
            // Don't update storage_location on failure
        } catch (Exception $e) {
            Log::error('An unexpected error occurred: '.$e->getMessage());
            // Don't update storage_location on failure
        }
    }
}
