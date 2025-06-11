<?php

use App\Models\Plugin;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Blade;

new class extends Component {
    public Plugin $plugin;
    public string|null $blade_code;
    public string|null $view_content;

    public string $name;
    public int $data_stale_minutes;
    public string $data_strategy;
    public string|null $polling_url;
    public string $polling_verb;
    public string|null $polling_header;
    public $data_payload;
    public array $checked_devices = [];
    public string $playlist_name = '';
    public array|null $selected_weekdays = null;
    public string $active_from = '';
    public string $active_until = '';
    public string $selected_playlist = '';
    public string $mashup_layout = 'full';
    public array $mashup_plugins = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $this->blade_code = $this->plugin->render_markup;

        if ($this->plugin->render_markup_view) {
            try {
                $basePath = resource_path('views/' . str_replace('.', '/', $this->plugin->render_markup_view));
                $paths = [
                    $basePath . '.blade.php',
                    $basePath . '.liquid',
                ];

                $this->view_content = null;
                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        $this->view_content = file_get_contents($path);
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->view_content = null;
            }
        }

        $this->fillformFields();
    }

    public function fillFormFields(): void
    {
        $this->name = $this->plugin->name;
        $this->data_stale_minutes = $this->plugin->data_stale_minutes;
        $this->data_strategy = $this->plugin->data_strategy;
        $this->polling_url = $this->plugin->polling_url;
        $this->polling_verb = $this->plugin->polling_verb;
        $this->polling_header = $this->plugin->polling_header;
        $this->data_payload = json_encode($this->plugin->data_payload);
    }

    public function saveMarkup(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $this->validate();
        $this->plugin->update(['render_markup' => $this->blade_code]);
    }

    protected array $rules = [
        'name' => 'required|string|max:255',
        'data_stale_minutes' => 'required|integer|min:1',
        'data_strategy' => 'required|string|in:polling,webhook',
        'polling_url' => 'required_if:data_strategy,polling|nullable|url',
        'polling_verb' => 'required|string|in:get,post',
        'polling_header' => 'nullable|string|max:255',
        'blade_code' => 'nullable|string',
        'checked_devices' => 'array',
        'playlist_name' => 'required_if:selected_playlist,new|string|max:255',
        'selected_weekdays' => 'nullable|array',
        'active_from' => 'nullable|date_format:H:i',
        'active_until' => 'nullable|date_format:H:i',
        'selected_playlist' => 'nullable|string',
    ];

    public function editSettings()
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $validated = $this->validate();
        $this->plugin->update($validated);
    }

    public function updateData()
    {
        if ($this->plugin->data_strategy === 'polling') {
            // Parse headers from polling_header string
            $headers = ['User-Agent' => 'usetrmnl/byos_laravel', 'Accept' => 'application/json'];

            if ($this->plugin->polling_header) {
                $headerLines = explode("\n", trim($this->plugin->polling_header));
                foreach ($headerLines as $line) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }

            $response = Http::withHeaders($headers)
                ->get($this->plugin->polling_url)
                ->json();

            $this->plugin->update(['data_payload' => $response]);
            $this->data_payload = json_encode($response);
        }
    }

    public function getAvailablePlugins()
    {
        return auth()->user()->plugins()->where('id', '!=', $this->plugin->id)->get();
    }

    public function getRequiredPluginCount(): int
    {
        if ($this->mashup_layout === 'full') {
            return 1;
        }

        return match ($this->mashup_layout) {
            '1Lx1R', '1Tx1B' => 2,  // Left-Right or Top-Bottom split
            '1Lx2R', '2Lx1R', '2Tx1B', '1Tx2B' => 3,  // Two on one side, one on other
            '2x2' => 4,  // Quadrant
            default => 1,
        };
    }

    public function addToPlaylist()
    {
        $this->validate([
            'checked_devices' => 'required|array|min:1',
            'selected_playlist' => 'required|string',
            'mashup_layout' => 'required|string',
            'mashup_plugins' => 'required_if:mashup_layout,1Lx1R,1Lx2R,2Lx1R,1Tx1B,2Tx1B,1Tx2B,2x2|array',
        ]);

        foreach ($this->checked_devices as $deviceId) {
            $playlist = null;

            if ($this->selected_playlist === 'new') {
                // Create new playlist
                $this->validate([
                    'playlist_name' => 'required|string|max:255',
                ]);

                $playlist = \App\Models\Playlist::create([
                    'device_id' => $deviceId,
                    'name' => $this->playlist_name,
                    'weekdays' => !empty($this->selected_weekdays) ? $this->selected_weekdays : null,
                    'active_from' => $this->active_from ?: null,
                    'active_until' => $this->active_until ?: null,
                ]);
            } else {
                $playlist = \App\Models\Playlist::findOrFail($this->selected_playlist);
            }

            // Add plugin to playlist
            $maxOrder = $playlist->items()->max('order') ?? 0;

            if ($this->mashup_layout === 'full') {
                $playlist->items()->create([
                    'plugin_id' => $this->plugin->id,
                    'order' => $maxOrder + 1,
                ]);
            } else {
                // Create mashup
                $pluginIds = array_merge([$this->plugin->id], array_map('intval', $this->mashup_plugins));
                \App\Models\PlaylistItem::createMashup(
                    $playlist,
                    $this->mashup_layout,
                    $pluginIds,
                    $this->plugin->name . ' Mashup',
                    $maxOrder + 1
                );
            }
        }

        $this->reset(['checked_devices', 'playlist_name', 'selected_weekdays', 'active_from', 'active_until', 'selected_playlist', 'mashup_layout', 'mashup_plugins']);
        Flux::modal('add-to-playlist')->close();
    }

    public function getDevicePlaylists($deviceId)
    {
        return \App\Models\Playlist::where('device_id', $deviceId)->get();
    }

    public function renderExample(string $example)
    {
        switch ($example) {
            case 'layoutTitle':
                $markup = $this->renderLayoutWithTitleBar();
                break;
            case 'layout':
                $markup = $this->renderLayoutBlank();
                break;
            default:
                $markup = '<h1>Hello World!</h1>';
                break;
        }
        $this->blade_code = $markup;
    }

    public function renderLayoutWithTitleBar(): string
    {
        return <<<HTML
@props(['size' => 'full'])
<x-trmnl::view size="{{\$size}}">
    <x-trmnl::layout>
        <!-- ADD YOUR CONTENT HERE-->
    </x-trmnl::layout>
    <x-trmnl::title-bar/>
</x-trmnl::view>
HTML;
    }

    public function renderLayoutBlank(): string
    {
        return <<<HTML
@props(['size' => 'full'])
<x-trmnl::view size="{{\$size}}">
    <x-trmnl::layout>
        <!-- ADD YOUR CONTENT HERE-->
    </x-trmnl::layout>
</x-trmnl::view>
HTML;
    }

    public function renderPreview($size = 'full'): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);

        try {
            $previewMarkup = $this->plugin->render($size);
            $this->dispatch('preview-updated', preview: $previewMarkup);
        } catch (\Exception $e) {
            $this->dispatch('preview-error', message: $e->getMessage());
        }
    }

    public function deletePlugin(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $this->plugin->delete();
        $this->redirect(route('plugins.index'));
    }
}

?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold dark:text-gray-100">{{$plugin->name}}
                <flux:badge size="sm" class="ml-2">Recipe</flux:badge>
            </h2>

            <flux:button.group>
                <flux:modal.trigger name="preview-plugin">
                    <flux:button icon="eye" wire:click="renderPreview">Preview</flux:button>
                </flux:modal.trigger>
                <flux:dropdown>
                    <flux:button icon="chevron-down"></flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-1Tx1B" wire:click="renderPreview('half_horizontal')">Half-Horizontal
                            </flux:menu.item>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-1Lx1R" wire:click="renderPreview('half_vertical')">Half-Vertical
                            </flux:menu.item>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-2x2" wire:click="renderPreview('quadrant')">Quadrant</flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>

            </flux:button.group>
            <flux:button.group>
                <flux:modal.trigger name="add-to-playlist">
                    <flux:button icon="play" variant="primary">Add to Playlist</flux:button>
                </flux:modal.trigger>

                <flux:dropdown>
                    <flux:button icon="chevron-down" variant="primary"></flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="delete-plugin">
                            <flux:menu.item icon="trash" variant="danger">Delete Plugin</flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>
            </flux:button.group>
        </div>

        <flux:modal name="add-to-playlist" class="min-w-2xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Add to Playlist</flux:heading>
                </div>

                <form wire:submit="addToPlaylist">
                    <flux:separator text="Device(s)" />
                    <div class="mt-4 mb-4">
                        <flux:checkbox.group wire:model.live="checked_devices">
                            @foreach(auth()->user()->devices as $device)
                                <flux:checkbox label="{{ $device->name }}" value="{{ $device->id }}"/>
                            @endforeach
                        </flux:checkbox.group>
                    </div>

                    @if(count($checked_devices) === 1)
                        <flux:separator text="Playlist" />
                        <div class="mt-4 mb-4">
                            <flux:select wire:model.live.debounce="selected_playlist">
                                <option value="">Select Playlist or Create New</option>
                                @foreach($this->getDevicePlaylists($checked_devices[0]) as $playlist)
                                    <option value="{{ $playlist->id }}">{{ $playlist->name }}</option>
                                @endforeach
                                <option value="new">Create New Playlist</option>
                            </flux:select>
                        </div>
                    @endif
                    @if($selected_playlist)
                        @if($selected_playlist === 'new')
                            <div class="mt-4 mb-4">
                                <flux:input label="Playlist Name" wire:model="playlist_name"/>
                            </div>
                            <div class="mb-4">
                                <flux:checkbox.group wire:model="selected_weekdays" label="Active Days (optional)">
                                    <flux:checkbox label="Monday" value="1"/>
                                    <flux:checkbox label="Tuesday" value="2"/>
                                    <flux:checkbox label="Wednesday" value="3"/>
                                    <flux:checkbox label="Thursday" value="4"/>
                                    <flux:checkbox label="Friday" value="5"/>
                                    <flux:checkbox label="Saturday" value="6"/>
                                    <flux:checkbox label="Sunday" value="0"/>
                                </flux:checkbox.group>
                            </div>

                            <div class="mb-4">
                                <flux:input type="time" label="Active From (optional)" wire:model="active_from"/>
                            </div>

                            <div class="mb-4">
                                <flux:input type="time" label="Active Until (optional)" wire:model="active_until"/>
                            </div>
                        @endif

                        <flux:separator text="Layout" />
                        <div class="mt-4 mb-4">
                            <flux:radio.group wire:model.live="mashup_layout" variant="segmented">
                                <flux:radio value="full" icon="mashup-1x1"/>
                                <flux:radio value="1Lx1R"  icon="mashup-1Lx1R"/>
                                <flux:radio value="1Lx2R"  icon="mashup-1Lx2R"/>
                                <flux:radio value="2Lx1R"  icon="mashup-2Lx1R"/>
                                <flux:radio value="1Tx1B" icon="mashup-1Tx1B"/>
                                <flux:radio value="2Tx1B"  icon="mashup-2Tx1B"/>
                                <flux:radio value="1Tx2B"  icon="mashup-1Tx2B"/>
                                <flux:radio value="2x2"  icon="mashup-2x2"/>
                            </flux:radio.group>
                        </div>

                        @if($mashup_layout !== 'full')
                            <div class="mb-4">
                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Mashup Slots</div>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 text-sm text-zinc-500 dark:text-zinc-400">Main Plugin</div>
                                        <flux:input :value="$plugin->name" disabled class="flex-1"/>
                                    </div>
                                    @for($i = 0; $i < $this->getRequiredPluginCount() - 1; $i++)
                                        <div class="flex items-center gap-2">
                                            <div class="w-24 text-sm text-zinc-500 dark:text-zinc-400">Plugin {{ $i + 2 }}:</div>
                                            <flux:select wire:model="mashup_plugins.{{ $i }}" class="flex-1">
                                                <option value="">Select a plugin...</option>
                                                @foreach($this->getAvailablePlugins() as $availablePlugin)
                                                    <option value="{{ $availablePlugin->id }}">{{ $availablePlugin->name }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button type="submit" variant="primary">Add to Playlist</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        <flux:modal name="delete-plugin" class="min-w-[22rem] space-y-6">
            <div>
                <flux:heading size="lg">Delete {{ $plugin->name }}?</flux:heading>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">This will remove this plugin from your
                    account.</p>
            </div>

            <div class="flex gap-2">
                <flux:spacer/>
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deletePlugin" variant="danger">Delete plugin</flux:button>
            </div>
        </flux:modal>

        <flux:modal name="preview-plugin" class="min-w-[850px] min-h-[480px] space-y-6">
            <div>
                <flux:heading size="lg">Preview {{ $plugin->name }}</flux:heading>
            </div>

            <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden">
                <iframe id="preview-frame" class="w-full h-[480px] border-0"></iframe>
            </div>
        </flux:modal>

        <div class="mt-5 mb-5">
            <h3 class="text-xl font-semibold dark:text-gray-100">Settings</h3>
        </div>
        <div class="grid lg:grid-cols-2 lg:gap-8">
            <div>
                <form wire:submit="editSettings" class="mb-6">
                    <div class="mb-4">
                        <flux:input label="Name" wire:model="name" id="name" class="block mt-1 w-full" type="text"
                                    name="name" autofocus/>
                    </div>

                    <div class="mb-4">
                        <flux:input label="Data is stale after minutes" wire:model="data_stale_minutes"
                                    id="data_stale_minutes"
                                    class="block mt-1 w-full" type="number" name="data_stale_minutes" autofocus/>
                    </div>

                    <div class="mb-4">
                        <flux:radio.group wire:model.live="data_strategy" label="Data Strategy" variant="segmented">
                            <flux:radio value="polling" label="Polling"/>
                            <flux:radio value="webhook" label="Webhook"/>
                        </flux:radio.group>
                    </div>

                    @if($data_strategy === 'polling')
                        <div class="mb-4">
                            <flux:input label="Polling URL" wire:model="polling_url" id="polling_url"
                                        placeholder="https://example.com/api"
                                        class="block mt-1 w-full" type="text" name="polling_url" autofocus>
                                <x-slot name="iconTrailing">
                                    <flux:button size="sm" variant="subtle" icon="cloud-arrow-down"
                                                 wire:click="updateData"
                                                 tooltip="Fetch data now" class="-mr-1"/>
                                </x-slot>
                            </flux:input>
                        </div>

                        <div class="mb-4">
                            <flux:radio.group wire:model="polling_verb" label="Polling Verb" variant="segmented">
                                <flux:radio value="get" label="GET"/>
                                <flux:radio value="post" label="POST"/>
                            </flux:radio.group>
                        </div>

                        <div class="mb-4">
                            <flux:textarea
                                label="Polling Headers (one per line, format: Header: Value)"
                                wire:model="polling_header"
                                id="polling_header"
                                class="block mt-1 w-full font-mono"
                                name="polling_header"
                                rows="3"
                                placeholder="Authorization: Bearer ey.*******&#10;Content-Type: application/json"
                            />
                        </div>
                    @else
                        <div class="mb-4">
                            <flux:input
                                label="Webhook URL"
                                :value="route('api.custom_plugins.webhook', ['plugin_uuid' => $plugin->uuid])"
                                class="block mt-1 w-full font-mono"
                                readonly
                                copyable
                            >
                            </flux:input>
                        </div>
                        <div>
                            <p>Send JSON payload with key <code>merge_variables</code> to the webhook URL. The payload
                                will be merged with the plugin data.</p>
                        </div>
                    @endif

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    </div>
                </form>
            </div>
            <div>
                <flux:textarea label="Data Payload" wire:model="data_payload" id="data_payload"
                               class="block mt-1 w-full font-mono" type="text" name="data_payload"
                               readonly rows="24"/>
            </div>
        </div>
        <flux:separator/>
        <div class="mt-5 mb-5 ">
            <h3 class="text-xl font-semibold dark:text-gray-100">Markup</h3>
            @if($plugin->render_markup_view)
                <div>
                    Edit view
                    <span class="font-mono text-accent mb-4">{{ $plugin->render_markup_view }}</span> to update.
                </div>
                <div class="mb-4 mt-4">
                    <flux:textarea
                        label="File Content"
                        class="font-mono"
                        wire:model="view_content"
                        id="view_content"
                        name="view_content"
                        rows="15"
                        readonly
                    />
                </div>
            @else
                <div class="text-accent">
                    <span class="pr-2">Getting started:</span><flux:button wire:click="renderExample('layoutTitle')" class="text-xl">Responsive Layout with Title Bar</flux:button>
                    <flux:button wire:click="renderExample('layout')" class="text-xl">Responsive Layout</flux:button>
                </div>
            @endif
        </div>
        @if(!$plugin->render_markup_view)
            <form wire:submit="saveMarkup">
                <div class="mb-4">
                    <flux:textarea
                        label="Blade Code"
                        class="font-mono"
                        wire:model="blade_code"
                        id="blade_code"
                        name="blade_code"
                        rows="15"
                        placeholder="Enter your blade code here..."
                    />
                </div>

                <div class="flex">
                    <flux:button type="submit" variant="primary">
                        Save
                    </flux:button>
                </div>
            </form>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('preview-updated', ({preview}) => {
        const frame = document.getElementById('preview-frame');
        const frameDoc = frame.contentDocument || frame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(preview);
        frameDoc.close();
    });

    $wire.on('preview-error', ({message}) => {
        alert('Preview Error: ' + message);
    });
</script>
@endscript
