<?php

use App\Models\DeviceModel;
use Livewire\Volt\Component;

new class extends Component {

    public $deviceModels;

    public $name;
    public $label;
    public $description;
    public $width;
    public $height;
    public $colors;
    public $bit_depth;
    public $scale_factor = 1.0;
    public $rotation = 0;
    public $mime_type = 'image/png';
    public $offset_x = 0;
    public $offset_y = 0;
    public $published_at;

    protected $rules = [
        'name' => 'required|string|max:255|unique:device_models,name',
        'label' => 'required|string|max:255',
        'description' => 'required|string',
        'width' => 'required|integer|min:1',
        'height' => 'required|integer|min:1',
        'colors' => 'required|integer|min:1',
        'bit_depth' => 'required|integer|min:1',
        'scale_factor' => 'required|numeric|min:0.1',
        'rotation' => 'required|integer',
        'mime_type' => 'required|string|max:255',
        'offset_x' => 'required|integer',
        'offset_y' => 'required|integer',
        'published_at' => 'nullable|date',
    ];

    public function mount()
    {
        $this->deviceModels = DeviceModel::all();
        return view('livewire.device-models.index');
    }

    public function createDeviceModel(): void
    {
        $this->validate();

        DeviceModel::create([
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'colors' => $this->colors,
            'bit_depth' => $this->bit_depth,
            'scale_factor' => $this->scale_factor,
            'rotation' => $this->rotation,
            'mime_type' => $this->mime_type,
            'offset_x' => $this->offset_x,
            'offset_y' => $this->offset_y,
            'published_at' => $this->published_at,
        ]);

        $this->reset(['name', 'label', 'description', 'width', 'height', 'colors', 'bit_depth', 'scale_factor', 'rotation', 'mime_type', 'offset_x', 'offset_y', 'published_at']);
        \Flux::modal('create-device-model')->close();

        $this->deviceModels = DeviceModel::all();
        session()->flash('message', 'Device model created successfully.');
    }

    public $editingDeviceModelId;

    public function editDeviceModel(DeviceModel $deviceModel): void
    {
        $this->editingDeviceModelId = $deviceModel->id;
        $this->name = $deviceModel->name;
        $this->label = $deviceModel->label;
        $this->description = $deviceModel->description;
        $this->width = $deviceModel->width;
        $this->height = $deviceModel->height;
        $this->colors = $deviceModel->colors;
        $this->bit_depth = $deviceModel->bit_depth;
        $this->scale_factor = $deviceModel->scale_factor;
        $this->rotation = $deviceModel->rotation;
        $this->mime_type = $deviceModel->mime_type;
        $this->offset_x = $deviceModel->offset_x;
        $this->offset_y = $deviceModel->offset_y;
        $this->published_at = $deviceModel->published_at?->format('Y-m-d\TH:i');
    }

    public function updateDeviceModel(): void
    {
        $deviceModel = DeviceModel::findOrFail($this->editingDeviceModelId);

        $this->validate([
            'name' => 'required|string|max:255|unique:device_models,name,' . $deviceModel->id,
            'label' => 'required|string|max:255',
            'description' => 'required|string',
            'width' => 'required|integer|min:1',
            'height' => 'required|integer|min:1',
            'colors' => 'required|integer|min:1',
            'bit_depth' => 'required|integer|min:1',
            'scale_factor' => 'required|numeric|min:0.1',
            'rotation' => 'required|integer',
            'mime_type' => 'required|string|max:255',
            'offset_x' => 'required|integer',
            'offset_y' => 'required|integer',
            'published_at' => 'nullable|date',
        ]);

        $deviceModel->update([
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'colors' => $this->colors,
            'bit_depth' => $this->bit_depth,
            'scale_factor' => $this->scale_factor,
            'rotation' => $this->rotation,
            'mime_type' => $this->mime_type,
            'offset_x' => $this->offset_x,
            'offset_y' => $this->offset_y,
            'published_at' => $this->published_at,
        ]);

        $this->reset(['name', 'label', 'description', 'width', 'height', 'colors', 'bit_depth', 'scale_factor', 'rotation', 'mime_type', 'offset_x', 'offset_y', 'published_at', 'editingDeviceModelId']);
        \Flux::modal('edit-device-model-' . $deviceModel->id)->close();

        $this->deviceModels = DeviceModel::all();
        session()->flash('message', 'Device model updated successfully.');
    }

    public function deleteDeviceModel(DeviceModel $deviceModel): void
    {
        $deviceModel->delete();

        $this->deviceModels = DeviceModel::all();
        session()->flash('message', 'Device model deleted successfully.');
    }
}

?>

<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold dark:text-gray-100">Device Models</h2>
                {{-- <flux:modal.trigger name="create-device-model">--}}
                {{--     <flux:button icon="plus" variant="primary">Add Device Model</flux:button>--}}
                {{-- </flux:modal.trigger>--}}
            </div>
            @if (session()->has('message'))
                <div class="mb-4">
                    <flux:callout variant="success" icon="check-circle" heading=" {{ session('message') }}">
                        <x-slot name="controls">
                            <flux:button icon="x-mark" variant="ghost"
                                         x-on:click="$el.closest('[data-flux-callout]').remove()"/>
                        </x-slot>
                    </flux:callout>
                </div>
            @endif

            <flux:modal name="create-device-model" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Add Device Model</flux:heading>
                    </div>

                    <form wire:submit="createDeviceModel">
                        <div class="mb-4">
                            <flux:input label="Name" wire:model="name" id="name" class="block mt-1 w-full" type="text"
                                        name="name" autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Label" wire:model="label" id="label" class="block mt-1 w-full"
                                        type="text"
                                        name="label"/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Description" wire:model="description" id="description"
                                        class="block mt-1 w-full" name="description"/>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <flux:input label="Width" wire:model="width" id="width" class="block mt-1 w-full"
                                        type="number"
                                        name="width"/>
                            <flux:input label="Height" wire:model="height" id="height" class="block mt-1 w-full"
                                        type="number"
                                        name="height"/>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <flux:input label="Colors" wire:model="colors" id="colors" class="block mt-1 w-full"
                                        type="number"
                                        name="colors"/>
                            <flux:input label="Bit Depth" wire:model="bit_depth" id="bit_depth"
                                        class="block mt-1 w-full" type="number"
                                        name="bit_depth"/>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <flux:input label="Scale Factor" wire:model="scale_factor" id="scale_factor"
                                        class="block mt-1 w-full" type="number"
                                        name="scale_factor" step="0.1"/>
                            <flux:input label="Rotation" wire:model="rotation" id="rotation" class="block mt-1 w-full"
                                        type="number"
                                        name="rotation"/>
                        </div>

                        <div class="mb-4">
                            <flux:input label="MIME Type" wire:model="mime_type" id="mime_type"
                                        class="block mt-1 w-full" type="text"
                                        name="mime_type"/>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <flux:input label="Offset X" wire:model="offset_x" id="offset_x" class="block mt-1 w-full"
                                        type="number"
                                        name="offset_x"/>
                            <flux:input label="Offset Y" wire:model="offset_y" id="offset_y" class="block mt-1 w-full"
                                        type="number"
                                        name="offset_y"/>
                        </div>

                        <div class="flex">
                            <flux:spacer/>
                            <flux:button type="submit" variant="primary">Create Device Model</flux:button>
                        </div>
                    </form>
                </div>
            </flux:modal>

            @foreach ($deviceModels as $deviceModel)
                <flux:modal name="edit-device-model-{{ $deviceModel->id }}" class="md:w-96">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Edit Device Model</flux:heading>
                        </div>

                        <form wire:submit="updateDeviceModel">
                            <div class="mb-4">
                                <flux:input label="Name" wire:model="name" id="edit_name" class="block mt-1 w-full"
                                            type="text"
                                            name="edit_name"/>
                            </div>

                            <div class="mb-4">
                                <flux:input label="Label" wire:model="label" id="edit_label" class="block mt-1 w-full"
                                            type="text"
                                            name="edit_label"/>
                            </div>

                            <div class="mb-4">
                                <flux:input label="Description" wire:model="description" id="edit_description"
                                            class="block mt-1 w-full" name="edit_description"/>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <flux:input label="Width" wire:model="width" id="edit_width" class="block mt-1 w-full"
                                            type="number"
                                            name="edit_width"/>
                                <flux:input label="Height" wire:model="height" id="edit_height"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_height"/>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <flux:input label="Colors" wire:model="colors" id="edit_colors"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_colors"/>
                                <flux:input label="Bit Depth" wire:model="bit_depth" id="edit_bit_depth"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_bit_depth"/>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <flux:input label="Scale Factor" wire:model="scale_factor" id="edit_scale_factor"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_scale_factor" step="0.1"/>
                                <flux:input label="Rotation" wire:model="rotation" id="edit_rotation"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_rotation"/>
                            </div>

                            <div class="mb-4">
                                <flux:select label="MIME Type" wire:model="mime_type" id="edit_mime_type" name="edit_mime_type">
                                    <flux:select.option>image/png</flux:select.option>
                                    <flux:select.option>image/bmp</flux:select.option>
                                </flux:select>

                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <flux:input label="Offset X" wire:model="offset_x" id="edit_offset_x"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_offset_x"/>
                                <flux:input label="Offset Y" wire:model="offset_y" id="edit_offset_y"
                                            class="block mt-1 w-full" type="number"
                                            name="edit_offset_y"/>
                            </div>

                            <div class="flex">
                                <flux:spacer/>
                                <flux:button type="submit" variant="primary">Update Device Model</flux:button>
                            </div>
                        </form>
                    </div>
                </flux:modal>
            @endforeach

            <table
                class="min-w-full table-fixed text-zinc-800 divide-y divide-zinc-800/10 dark:divide-white/20 text-zinc-800"
                data-flux-table>
                <thead data-flux-columns>
                <tr>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Description</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Width</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Height</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Bit Depth</div>
                    </th>
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Actions</div>
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-rows>
                @foreach ($deviceModels as $deviceModel)
                    <tr data-flux-row>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300"
                        >
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-white">{{ $deviceModel->label }}</div>
                                <div class="text-xs text-zinc-500">{{ Str::limit($deviceModel->name, 50) }}</div>
                            </div>
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $deviceModel->width }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $deviceModel->height }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $deviceModel->bit_depth }}
                        </td>
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap font-medium text-zinc-800 dark:text-white"
                        >
                            <div class="flex items-center gap-4">
                                <flux:button.group>
                                    <flux:modal.trigger name="edit-device-model-{{ $deviceModel->id }}">
                                        <flux:button wire:click="editDeviceModel({{ $deviceModel->id }})" icon="pencil"
                                                     iconVariant="outline">
                                        </flux:button>
                                    </flux:modal.trigger>
                                    <flux:button wire:click="deleteDeviceModel({{ $deviceModel->id }})" icon="trash"
                                                 iconVariant="outline">
                                    </flux:button>
                                </flux:button.group>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
