<?php

namespace App\Console\Commands;

use App\Jobs\FirmwarePollJob;
use App\Models\Firmware;
use Illuminate\Console\Command;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class FirmwareCheckCommand extends Command
{
    protected $signature = 'trmnl:firmware:check {--download : Download the latest firmware if available}';

    protected $description = 'Checks for the latest firmware and downloads it if flag --download is passed.';

    public function handle(): void
    {
        spin(
            callback: fn () => FirmwarePollJob::dispatchSync(download: $this->option('download')),
            message: 'Checking for latest firmware...'
        );

        $latestFirmware = Firmware::getLatest();
        if ($latestFirmware) {
            table(
                rows: [
                    ['Latest Version', $latestFirmware->version_tag],
                    ['Download URL', $latestFirmware->url],
                    ['Storage Location', $latestFirmware->storage_location],
                ]
            );
        } else {
            $this->error('No firmware found.');
        }
    }
}
