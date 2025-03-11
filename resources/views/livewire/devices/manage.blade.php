<?php

use App\Models\Device;
use Livewire\Volt\Component;

new class extends Component {

    public $devices;

    public $showDeviceForm = false;

    public $name;

    public $mac_address;

    public $api_key;

    public $default_refresh_interval = 900;

    public $friendly_id;

    protected $rules = [
        'mac_address' => 'required',
        'api_key' => 'required',
        'default_refresh_interval' => 'required|integer',
    ];

    public function mount()
    {
        $this->devices = auth()->user()->devices;
        return view('livewire.devices.manage');
    }

    public function createDevice(): void
    {
        $this->validate();

        Device::create([
            'name' => $this->name,
            'mac_address' => $this->mac_address,
            'api_key' => $this->api_key,
            'default_refresh_interval' => $this->default_refresh_interval,
            'friendly_id' => $this->friendly_id,
            'user_id' => auth()->id(),
        ]);

        $this->reset();
        \Flux::modal('create-device')->close();

        $this->devices = auth()->user()->devices;
        session()->flash('message', 'Device created successfully.');
    }

    public function toggleProxyCloud(Device $device): void
    {
        abort_unless(auth()->user()->devices->contains($device), 403);
        $device->update([
            'proxy_cloud' => !$device->proxy_cloud,
        ]);

        // if ($device->proxy_cloud) {
        //     \App\Jobs\FetchProxyCloudResponses::dispatch();
        // }
    }
}

?>

<div>
    <div class="py-12">
        {{--@dump($devices)--}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold dark:text-gray-100">Devices</h2>
                <flux:modal.trigger name="create-device">
                    <flux:button icon="plus" variant="primary">Add Device</flux:button>
                </flux:modal.trigger>
            </div>
            @if (session()->has('message'))
                <div class="mb-4">
                    <flux:callout variant="success" icon="check-circle" heading=" {{ session('message') }}">
                        <x-slot name="controls">
                            <flux:button icon="x-mark" variant="ghost" x-on:click="$el.closest('[data-flux-callout]').remove()" />
                        </x-slot>
                    </flux:callout>
                </div>
            @endif

            <flux:modal name="create-device" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Add Device</flux:heading>
                    </div>

                    <form wire:submit="createDevice">
                        <div class="mb-4">
                            <flux:input label="Name" wire:model="name" id="name" class="block mt-1 w-full" type="text"
                                        name="name"
                                        autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Mac Address" wire:model="mac_address" id="mac_address"
                                        class="block mt-1 w-full"
                                        type="text" name="mac_address" autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="API Key" wire:model="api_key" id="api_key" class="block mt-1 w-full"
                                        type="text"
                                        name="api_key" autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Friendly Id" wire:model="friendly_id" id="friendly_id"
                                        class="block mt-1 w-full"
                                        type="text" name="friendly_id" autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Refresh Rate (seconds)" wire:model="default_refresh_interval"
                                        id="default_refresh_interval"
                                        class="block mt-1 w-full" type="number" name="default_refresh_interval"
                                        autofocus/>
                        </div>
                        <div class="flex">
                            <flux:spacer/>
                            <flux:button type="submit" variant="primary">Create Device</flux:button>
                        </div>

                    </form>
                </div>
            </flux:modal>

            <table
                class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-800/10 dark:divide-white/20 text-zinc-800"
                data-flux-table="">
                <thead data-flux-columns="">
                <tr>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Name</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Friendly ID</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Mac Address</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Refresh</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Actions</div>
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-rows="">
                @foreach ($devices as $device)
                    <tr data-flux-row="">
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $device->name }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $device->friendly_id }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  text-zinc-500 dark:text-zinc-300"
                        >
                            <div type="button" data-flux-badge="data-flux-badge"
                                 class="inline-flex items-center font-medium whitespace-nowrap -mt-1 -mb-1 text-xs py-1 [&_[data-flux-badge-icon]]:size-3 [&_[data-flux-badge-icon]]:mr-1 rounded-md px-2 text-zinc-700 [&_button]:!text-zinc-700 dark:text-zinc-200 [&_button]:dark:!text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40 [&:is(button)]:hover:bg-zinc-400/25 [&:is(button)]:hover:dark:bg-zinc-400/50">
                                {{ $device->mac_address }}
                            </div>
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $device->default_refresh_interval }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  font-medium text-zinc-800 dark:text-white"
                        >
                            <div class="flex items-center gap-4">
                                <flux:button href="{{ route('devices.configure', $device) }}" wire:navigate icon="eye">
                                </flux:button>

                                <flux:tooltip
                                    content="Proxies images from the TRMNL Cloud service when no image is set (available in TRMNL DEV Edition only)."
                                    position="bottom">
                                    <flux:switch wire:model.live="proxy_cloud"
                                                 wire:click="toggleProxyCloud({{ $device->id }})"
                                                 :checked="$device->proxy_cloud" label="☁️ Proxy"/>
                                </flux:tooltip>
                            </div>
                        </td>
                    </tr>
                @endforeach

                <!--[if ENDBLOCK]><![endif]-->
                </tbody>
            </table>
        </div>
    </div>

</div>
