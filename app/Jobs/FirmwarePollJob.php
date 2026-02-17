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
use Log;

class FirmwarePollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private bool $download = false) {}

    public function handle(): void
    {
        try {
            $firmwareEndpoint = config('services.trmnl.base_url').'/api/firmware/latest';

            $response = Http::get($firmwareEndpoint)->json();

            if (! is_array($response) || ! isset($response['version']) || ! isset($response['url'])) {
                Log::error('Invalid firmware response format received');

                return;
            }

            $latestFirmware = Firmware::updateOrCreate(
                ['version_tag' => $response['version']],
                [
                    'url' => $response['url'],
                    'latest' => true,
                ]
            );

            Firmware::where('id', '!=', $latestFirmware->id)->update(['latest' => false]);

            if ($this->download && $latestFirmware->url && $latestFirmware->storage_location === null) {
                FirmwareDownloadJob::dispatchSync($latestFirmware);
            }

        } catch (ConnectionException $e) {
            Log::error('Firmware download failed: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('Unexpected error in firmware polling: '.$e->getMessage());
        }
    }
}
