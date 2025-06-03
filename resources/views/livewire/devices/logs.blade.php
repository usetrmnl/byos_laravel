<?php

use App\Models\Device;
use App\Models\DeviceLog;
use Livewire\Volt\Component;

new class extends Component {
    public Device $device;
    public $logs;

    public function mount(Device $device)
    {
        abort_unless(auth()->user()->devices->contains($device), 403);
        $this->device = $device;
        $this->logs = $device->logs()->latest('device_timestamp')->take(50)->get();
    }
}

?>

<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold dark:text-gray-100">Device Logs - {{ $device->name }}</h2>
            </div>

            <table class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-800/10 dark:divide-white/20 text-zinc-800" data-flux-table="">
                <thead data-flux-columns="">
                    <tr>
                        <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column="">
                            <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Device Time</div>
                        </th>
                        <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column="">
                            <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Log Level</div>
                        </th>
                        <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column="">
                            <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Device Status</div>
                        </th>
                        <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column="">
                            <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Message</div>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-rows="">
                    @foreach ($logs as $log)
                        <tr data-flux-row="">
                            <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300">
                                {{ \Carbon\Carbon::createFromTimestamp($log->log_entry['creation_timestamp'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300">
                                <div class="inline-flex items-center font-medium whitespace-nowrap -mt-1 -mb-1 text-xs py-1 px-2 rounded-md
                                    @if(str_contains(strtolower($log->log_entry['log_message']), 'error'))
                                        bg-red-400/15 text-red-700 dark:bg-red-400/40 dark:text-red-200
                                    @elseif(str_contains(strtolower($log->log_entry['log_message']), 'warning'))
                                        bg-yellow-400/15 text-yellow-700 dark:bg-yellow-400/40 dark:text-yellow-200
                                    @else
                                        bg-zinc-400/15 text-zinc-700 dark:bg-zinc-400/40 dark:text-zinc-200
                                    @endif">
                                    {{ str_contains(strtolower($log->log_entry['log_message']), 'error') ? 'Error' :
                                       (str_contains(strtolower($log->log_entry['log_message']), 'warning') ? 'Warning' : 'Info') }}
                                </div>
                            </td>
                            <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300">
                                <div class="flex items-center gap-2">
                                    <div class="inline-flex items-center font-medium whitespace-nowrap -mt-1 -mb-1 text-xs py-1 px-2 rounded-md bg-zinc-400/15 text-zinc-700 dark:bg-zinc-400/40 dark:text-zinc-200">
                                        {{ $log->log_entry['device_status_stamp']['wifi_status'] ?? 'Unknown' }}
                                        @if(isset($log->log_entry['device_status_stamp']['wifi_rssi_level']))
                                            ({{ $log->log_entry['device_status_stamp']['wifi_rssi_level'] }}dBm)
                                        @endif
                                    </div>
                                    @if(isset($log->log_entry['device_status_stamp']))
                                        <flux:modal.trigger name="device-status-{{ $log->id }}">
                                            <flux:button icon="information-circle" variant="ghost" size="xs" />
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300">
                                <div class="flex items-center gap-2">
                                    <span>{{ $log->log_entry['log_message'] }}</span>
                                    <flux:modal.trigger name="log-details-{{ $log->id }}">
                                        <flux:button icon="information-circle" variant="ghost" size="xs" />
                                    </flux:modal.trigger>
                                </div>
                            </td>
                        </tr>

                        @if(isset($log->log_entry['device_status_stamp']))
                            <flux:modal name="device-status-{{ $log->id }}" class="md:w-96">
                                <div class="space-y-6">
                                    <div>
                                        <flux:heading size="lg">Device Status Details</flux:heading>
                                    </div>

                                    <dl class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">WiFi Status:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['wifi_status'] ?? 'Unknown' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">WiFi RSSI:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['wifi_rssi_level'] ?? 'Unknown' }} dBm</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Refresh Rate:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['refresh_rate'] ?? 'Unknown' }}s</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Time Since Sleep:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['time_since_last_sleep_start'] ?? 'Unknown' }}s</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Firmware Version:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['current_fw_version'] ?? 'Unknown' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Special Function:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['special_function'] ?? 'None' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Battery Voltage:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['battery_voltage'] ?? 'Unknown' }}V</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Wakeup Reason:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['wakeup_reason'] ?? 'Unknown' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Free Heap:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['free_heap_size'] ?? 'Unknown' }} bytes</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">Max Alloc Size:</dt>
                                            <dd>{{ $log->log_entry['device_status_stamp']['max_alloc_size'] ?? 'Unknown' }} bytes</dd>
                                        </div>
                                    </dl>

                                    <div class="flex">
                                        <flux:spacer/>
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Close</flux:button>
                                        </flux:modal.close>
                                    </div>
                                </div>
                            </flux:modal>
                        @endif

                        <flux:modal name="log-details-{{ $log->id }}" class="md:w-192">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">Log Details</flux:heading>
                                </div>

                                <dl class="text-sm space-y-1">
                                    <div class="flex justify-between">
                                        <dt class="text-zinc-500">Source File:</dt>
                                        <dd>{{ $log->log_entry['log_sourcefile'] ?? 'Unknown' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-zinc-500">Line Number:</dt>
                                        <dd>{{ $log->log_entry['log_codeline'] ?? 'Unknown' }}</dd>
                                    </div>
                                    @if(isset($log->log_entry['additional_info']))
                                        <div class="mt-2">
                                            <dt class="text-zinc-500 mb-1">Additional Info</dt>
                                            <dd class="space-y-1">
                                                @foreach($log->log_entry['additional_info'] as $key => $value)
                                                    <div class="flex justify-between">
                                                        <span class="text-zinc-500">{{ str_replace('_', ' ', ucfirst($key)) }}:</span>
                                                        <span>{{ is_null($value) ? 'None' : $value }}</span>
                                                    </div>
                                                @endforeach
                                            </dd>
                                        </div>
                                    @endif
                                </dl>

                                <div class="flex">
                                    <flux:spacer/>
                                    <flux:modal.close>
                                        <flux:button variant="ghost">Close</flux:button>
                                    </flux:modal.close>
                                </div>
                            </div>
                        </flux:modal>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
