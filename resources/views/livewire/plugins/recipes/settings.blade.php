<?php

use App\Models\Plugin;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

/*
 * This component contains the TRMNL Plugin Settings modal
 */
new class extends Component {
    public Plugin $plugin;
    public string|null $trmnlp_id = null;
    public string|null $uuid = null;

    public int $resetIndex = 0;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->resetErrorBag();
        // Reload data
        $this->plugin = $this->plugin->fresh();
        $this->trmnlp_id = $this->plugin->trmnlp_id;
        $this->uuid = $this->plugin->uuid;
    }

    public function saveTrmnlpId(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);

        $this->validate([
            'trmnlp_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('plugins', 'trmnlp_id')
                    ->where('user_id', auth()->id())
                    ->ignore($this->plugin->id),
            ],
        ]);

        $this->plugin->update([
            'trmnlp_id' => empty($this->trmnlp_id) ? null : $this->trmnlp_id,
        ]);

        //$this->loadData(); // Reload to ensure we have the latest data
        Flux::modal('trmnlp-settings')->close();
    }
};?>

<flux:modal name="trmnlp-settings" class="min-w-[400px] space-y-6">
    <div wire:key="trmnlp-settings-form-{{ $resetIndex }}" class="space-y-6">
        <div>
            <flux:heading size="lg">Recipe Settings</flux:heading>
        </div>

        <form wire:submit="saveTrmnlpId">
            <div class="grid gap-6">
                {{-- <flux:input label="UUID" wire:model="uuid" readonly copyable /> --}}
                <flux:field>
                    <flux:label>TRMNLP Recipe ID</flux:label>
                    <flux:input
                        wire:model="trmnlp_id"
                        placeholder="TRMNL Recipe ID"
                    />
                    <flux:error name="trmnlp_id" />
                    <flux:description>Recipe ID in the TRMNL Recipe Catalog. If set, it can be used with <code>trmnlp</code>. </flux:description>
                </flux:field>
            </div>

            <div class="flex gap-2 mt-4">
                <flux:spacer/>
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </div>
</flux:modal>
