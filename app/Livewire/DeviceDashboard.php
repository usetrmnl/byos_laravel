<?php

namespace App\Livewire;

use Livewire\Component;

class DeviceDashboard extends Component
{
    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.device-dashboard', ['devices' => auth()->user()->devices()->paginate(10)]);
    }
}
