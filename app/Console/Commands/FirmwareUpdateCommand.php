<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Firmware;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class FirmwareUpdateCommand extends Command
{
    protected $signature = 'trmnl:firmware:update';

    protected $description = 'Command description';

    public function handle(): void
    {

        $checkFirmware = select(
            label: 'Check for new firmware?',
            options: [
                'check' => 'Check. Devices will download binary from the original source.',
                'download' => 'Check & Download. Devices will download binary from BYOS.',
                'no' => 'Do not check.',
            ],
        );

        if ($checkFirmware !== 'no') {
            $this->call('trmnl:firmware:check', [
                '--download' => $checkFirmware === 'download',
            ]);
        }

        $firmwareVersion = select(
            label: 'Update to which version?',
            options: Firmware::pluck('version_tag', 'id')
        );

        $devices = multiselect(
            label: 'Which devices should be updated?',
            options: [
                'all' => 'ALL Devices',
                ...Device::all()->mapWithKeys(function ($device) {
                    // without _ returns index
                    return ["_$device->id" => "$device->name (Current version: $device->last_firmware_version)"];
                })->toArray()
            ],
            scroll: 10
        );



        if (empty($devices)) {
            $this->error('No devices selected. Aborting.');
            return;
        }

        if (in_array('all', $devices)) {
            $devices = Device::pluck('id')->toArray();
        } else {
            $devices = array_map(function($selected) {
                return (int) str_replace('_', '', $selected);
            }, $devices);
        }


        foreach ($devices as $deviceId) {
            Device::find($deviceId)->update(['update_firmware_id' => $firmwareVersion]);

            $this->info("Device with id [$deviceId] will update firmware on next request.");
        }
    }
}
