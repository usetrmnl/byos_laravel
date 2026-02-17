<?php

use Livewire\Component;

new class extends Component
{
    public $token;

    public $devices;

    public $selected_device;

    public function mount(): void
    {
        $token = Auth::user()?->tokens()?->first();
        if ($token === null) {
            $token = Auth::user()->createToken('api-token', ['update-screen']);
        }
        $this->token = $token->plainTextToken;

        $this->devices = auth()->user()->devices?->pluck('id', 'name');
        $this->selected_device = $this->devices->first();
    }

    public function regenerateToken()
    {
        Auth::user()->tokens()?->first()?->delete();
        $token = Auth::user()->createToken('api-token', ['update-screen']);
        $this->token = $token->plainTextToken;
    }
};
?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold dark:text-gray-100">API
                <flux:badge size="sm" class="ml-2">Plugin</flux:badge>
            </h2>

        </div>

        <div class="mb-6 max-w-md">
            @if(isset($devices))
                <flux:select wire:model.live="selected_device" label="Select Device">
                    @foreach($devices as $id => $name)
                        <flux:select.option value="{{$name}}">
                            {{ $id }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <div>
            <p>
                <flux:badge>POST</flux:badge>
                <span class="ml-2 font-mono">{{ route('display.update') }}?device_id={{ $selected_device }}</span>
            </p>
            <div class="mt-4">
                <h3 class="text-lg">Headers</h3>
                <div>Authorization <span class="ml-2 font-mono">Bearer {{$token ?? '**********'}}</span>
                    <flux:button variant="subtle" size="xs" class="mt-2" wire:click="regenerateToken()">
                        Regenerate Token
                    </flux:button>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-lg">Body</h3>
                <div class="font-mono">
                    <pre>
{&#x22;markup&#x22;:&#x22;&#x3C;h1&#x3E;Hello World&#x3C;/h1&#x3E;&#x22;}
                    </pre>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <p>
                <flux:badge>GET</flux:badge><flux:badge>POST</flux:badge>
                <span class="ml-2 font-mono">{{ route('display.status') }}?device_id={{ $selected_device }}</span>
            </p>
            <div class="mt-4">
                <h3 class="text-lg">Headers</h3>
                <div>Authorization <span class="ml-2 font-mono">Bearer {{$token ?? '**********'}}</span>
                    <flux:button variant="subtle" size="xs" class="mt-2" wire:click="regenerateToken()">
                        Regenerate Token
                    </flux:button>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-lg">Body <flux:badge size="sm">POST</flux:badge></h3>
                <div class="font-mono">
                    <pre>
{&#x22;default_refresh_interval&#x22;: 900, &#x22;sleep_mode_enabled&#x22;: true, &#x22;pause_until&#x22;: &#x22;2025-07-10T22:00:00+02:00&#x22;}
                    </pre>
                </div>
            </div>
        </div>
    </div>
</div>
