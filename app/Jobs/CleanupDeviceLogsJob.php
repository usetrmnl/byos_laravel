<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CleanupDeviceLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Device::each(function ($device) {
            $keepIds = $device->logs()->latest('device_timestamp')->take(50)->pluck('id');

            // Delete all other logs for this device
            $device->logs()
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }
}
