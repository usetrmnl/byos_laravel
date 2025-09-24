<?php

namespace App\Livewire\Actions;

use Livewire\Component;

class DeviceAutoJoin extends Component
{
    public bool $deviceAutojoin = false;

    public bool $isFirstUser = false;

    public function mount(): void
    {
        $this->deviceAutojoin = auth()->user()->assign_new_devices;
        $this->isFirstUser = auth()->user()->id === 1;

    }

    public function updating($name, $value): void
    {
        $this->validate([
            'deviceAutojoin' => 'boolean',
        ]);

        if ($name === 'deviceAutojoin') {
            auth()->user()->update([
                'assign_new_devices' => $value,
            ]);
        }
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.actions.device-auto-join');
    }
}
