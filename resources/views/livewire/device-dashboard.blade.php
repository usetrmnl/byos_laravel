<?php

use Livewire\Volt\Component;

new class extends Component {
    public function mount()
    {
        return view('livewire.device-dashboard', ['devices' => auth()->user()->devices()->paginate(10)]);
    }
}
?>

<div>
    <div class="bg-muted flex flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-3xl flex-col gap-6">
            @if($devices->isEmpty())
                <div class="flex flex-col gap-6">
                    <div
                        class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                        <div class="px-10 py-8">
                            <h1 class="text-xl font-medium dark:text-zinc-200">Add your first device</h1>
                            <flux:button href="{{ route('devices') }}" class="mt-4" icon="plus-circle" variant="primary"
                                         class="w-full mt-4">Add Device
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            @foreach($devices as $device)
                <div class="flex flex-col gap-6">
                    <div
                        class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                        <div class="px-10 py-8">
                            @php
                                $current_image_uuid =$device->current_screen_image;
                                if($current_image_uuid) {
                                    $file_extension = file_exists(storage_path('app/public/images/generated/' . $current_image_uuid . '.png')) ? 'png' : 'bmp';
                                    $current_image_path = 'storage/images/generated/' . $current_image_uuid . '.' . $file_extension;
                                } else {
                                    $current_image_path = 'storage/images/setup-logo.bmp';
                                }
                            @endphp

                            <h1 class="text-xl font-medium dark:text-zinc-200">{{ $device->name }}</h1>
                            <p class="text-sm dark:text-zinc-400">{{$device->mac_address}}</p>
                            @if($device->mirror_device_id)
                                <flux:separator class="mt-2 mb-4"/>
                                <flux:callout variant="info">
                                    <div class="flex items-center gap-2">
                                        <flux:icon.link class="dark:text-zinc-200"/>
                                        <flux:text>
                                            This device is mirrored from
                                            <a href="{{ route('devices.configure', $device->mirrorDevice) }}" class="font-medium hover:underline">
                                                {{ $device->mirrorDevice->name }}
                                            </a>
                                        </flux:text>
                                    </div>
                                </flux:callout>
                            @elseif($current_image_path)
                                <flux:separator class="mt-2 mb-4"/>
                                <img src="{{ asset($current_image_path) }}" class="max-h-[480px]" alt="Current Image"/>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{--    @php--}}
    {{--        $current_image_uuid = auth()->user()?->devices()?->first()?->current_screen_image;--}}
    {{--        $current_image_path = 'images/generated/' . $current_image_uuid . '.png';--}}
    {{--    @endphp--}}
    {{--    @if($current_image_uuid)--}}
    {{--        <h1 class="text-xl font-medium dark:text-zinc-200">TRMNL Giveaway</h1>--}}
    {{--        <p class="text-sm dark:text-zinc-400">D8:3B:DA:F3:C1:DC</p>--}}
    {{--        <flux:separator class="mt-2 mb-4"/>--}}
    {{--        <img src="{{ asset($current_image_path) }}" alt="Current Image"/>--}}
    {{--    @else--}}
    {{--        <h1 class="text-xl font-medium dark:text-zinc-200">Add your first device</h1>--}}
    {{--        <flux:button href="{{ route('devices') }}" class="mt-4"  icon="plus-circle" variant="primary">Add Device</flux:button>--}}
    {{--    @endif--}}
</div>
