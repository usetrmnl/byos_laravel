<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class DeviceLogFactory extends Factory
{
    protected $model = DeviceLog::class;

    public function definition(): array
    {
        return [
            'log_entry' => ["creation_timestamp"=>fake()->dateTimeBetween('-1 month', 'now')->getTimestamp(),"device_status_stamp"=>["wifi_rssi_level"=>-65,"wifi_status"=>"connected","refresh_rate"=>900,"time_since_last_sleep_start"=>901,"current_fw_version"=>"1.5.5","special_function"=>"none","battery_voltage"=>4.052,"wakeup_reason"=>"timer","free_heap_size"=>215128,"max_alloc_size"=>192500],"log_id"=>17,"log_message"=>"Error fetching API display: 7, detail: HTTP Client failed with error: connection refused(-1)","log_codeline"=>586,"log_sourcefile"=>"src\/bl.cpp","additional_info"=>["filename_current"=>"UUID.png","filename_new"=>null,"retry_attempt"=>5]],
            'device_timestamp' => fake()->dateTimeBetween('-1 month', 'now'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'device_id' => Device::first(),
        ];
    }
}
