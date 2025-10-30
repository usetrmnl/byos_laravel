<?php

use App\Models\Plugin;
use Illuminate\Support\Carbon;
use Keepsuit\Liquid\Exceptions\LiquidException;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public Plugin $plugin;
    public string|null $markup_code;
    public string|null $view_content;
    public string|null $markup_language;

    public string $name;
    public bool $no_bleed = false;
    public bool $dark_mode = false;
    public int $data_stale_minutes;
    public string $data_strategy;
    public string|null $polling_url;
    public string $polling_verb;
    public string|null $polling_header;
    public string|null $polling_body;
    public $data_payload;
    public ?Carbon $data_payload_updated_at;
    public array $checked_devices = [];
    public array $device_playlists = [];
    public array $device_playlist_names = [];
    public array $device_weekdays = [];
    public array $device_active_from = [];
    public array $device_active_until = [];
    public string $mashup_layout = 'full';
    public array $mashup_plugins = [];
    public array $configuration_template = [];
    public array $configuration = [];
    public array $xhrSelectOptions = [];
    public array $searchQueries = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $this->blade_code = $this->plugin->render_markup;
        $this->configuration_template = $this->plugin->configuration_template ?? [];
        $this->configuration = is_array($this->plugin->configuration) ? $this->plugin->configuration : [];

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
        } else {
            $this->markup_code = $this->plugin->render_markup;
            $this->markup_language = $this->plugin->markup_language ?? 'blade';
        }

        // Initialize screen settings from the model
        $this->no_bleed = (bool) ($this->plugin->no_bleed ?? false);
        $this->dark_mode = (bool) ($this->plugin->dark_mode ?? false);

        $this->fillformFields();
        $this->data_payload_updated_at = $this->plugin->data_payload_updated_at;
    }

    public function fillFormFields(): void
    {
        $this->name = $this->plugin->name;
        $this->data_stale_minutes = $this->plugin->data_stale_minutes;
        $this->data_strategy = $this->plugin->data_strategy;
        $this->polling_url = $this->plugin->polling_url;
        $this->polling_verb = $this->plugin->polling_verb;
        $this->polling_header = $this->plugin->polling_header;
        $this->polling_body = $this->plugin->polling_body;
        $this->data_payload = json_encode($this->plugin->data_payload, JSON_PRETTY_PRINT);
    }

    public function saveMarkup(): void
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);
        $this->validate();
        $this->plugin->update([
            'render_markup' => $this->markup_code ?? null,
            'markup_language' => $this->markup_language ?? null
        ]);
    }

    protected array $rules = [
        'name' => 'required|string|max:255',
        'data_stale_minutes' => 'required|integer|min:1',
        'data_strategy' => 'required|string|in:polling,webhook,static',
        'polling_url' => 'required_if:data_strategy,polling|nullable',
        'polling_verb' => 'required|string|in:get,post',
        'polling_header' => 'nullable|string|max:255',
        'polling_body' => 'nullable|string',
        'data_payload' => 'required_if:data_strategy,static|nullable|json',
        'markup_code' => 'nullable|string',
        'markup_language' => 'nullable|string|in:blade,liquid',
        'checked_devices' => 'array',
        'device_playlist_names' => 'array',
        'device_playlists' => 'array',
        'device_weekdays' => 'array',
        'device_active_from' => 'array',
        'device_active_until' => 'array',
        'no_bleed' => 'boolean',
        'dark_mode' => 'boolean',
    ];

    public function editSettings()
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);

        // Custom validation for polling_url with Liquid variable resolution
        $this->validatePollingUrl();

        $validated = $this->validate();
        $validated['data_payload'] = json_decode(Arr::get($validated,'data_payload'), true);
        $this->plugin->update($validated);
    }

    protected function validatePollingUrl(): void
    {
        if ($this->data_strategy === 'polling' && !empty($this->polling_url)) {
            try {
                $resolvedUrl = $this->plugin->resolveLiquidVariables($this->polling_url);

                if (!filter_var($resolvedUrl, FILTER_VALIDATE_URL)) {
                    $this->addError('polling_url', 'The polling URL must be a valid URL after resolving configuration variables.');
                }
            } catch (\Exception $e) {
                $this->addError('polling_url', 'Error resolving Liquid variables: ' . $e->getMessage() . $e->getPrevious()?->getMessage());
            }
        }
    }

    public function updateData(): void
    {
        if ($this->plugin->data_strategy === 'polling') {
            try {
                $this->plugin->updateDataPayload();

                $this->data_payload = json_encode($this->plugin->data_payload, JSON_PRETTY_PRINT);
                $this->data_payload_updated_at = $this->plugin->data_payload_updated_at;

            } catch (\Exception $e) {
                $this->dispatch('data-update-error', message: $e->getMessage() . $e->getPrevious()?->getMessage());
            }
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
            'mashup_layout' => 'required|string',
            'mashup_plugins' => 'required_if:mashup_layout,1Lx1R,1Lx2R,2Lx1R,1Tx1B,2Tx1B,1Tx2B,2x2|array',
        ]);

        // Validate that each checked device has a playlist selected
        foreach ($this->checked_devices as $deviceId) {
            if (!isset($this->device_playlists[$deviceId]) || empty($this->device_playlists[$deviceId])) {
                $this->addError('device_playlists.' . $deviceId, 'Please select a playlist for each device.');
                return;
            }

            // If creating new playlist, validate required fields
            if ($this->device_playlists[$deviceId] === 'new') {
                if (!isset($this->device_playlist_names[$deviceId]) || empty($this->device_playlist_names[$deviceId])) {
                    $this->addError('device_playlist_names.' . $deviceId, 'Playlist name is required when creating a new playlist.');
                    return;
                }
            }
        }

        foreach ($this->checked_devices as $deviceId) {
            $playlist = null;

            if ($this->device_playlists[$deviceId] === 'new') {
                // Create new playlist
                $playlist = \App\Models\Playlist::create([
                    'device_id' => $deviceId,
                    'name' => $this->device_playlist_names[$deviceId],
                    'weekdays' => !empty($this->device_weekdays[$deviceId] ?? null) ? $this->device_weekdays[$deviceId] : null,
                    'active_from' => $this->device_active_from[$deviceId] ?? null,
                    'active_until' => $this->device_active_until[$deviceId] ?? null,
                ]);
            } else {
                $playlist = \App\Models\Playlist::findOrFail($this->device_playlists[$deviceId]);
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

        $this->reset([
            'checked_devices',
            'device_playlists',
            'device_playlist_names',
            'device_weekdays',
            'device_active_from',
            'device_active_until',
            'mashup_layout',
            'mashup_plugins'
        ]);
        Flux::modal('add-to-playlist')->close();
    }

    public function saveConfiguration()
    {
        abort_unless(auth()->user()->plugins->contains($this->plugin), 403);

        $configurationValues = [];
        if (isset($this->configuration_template['custom_fields'])) {
            foreach ($this->configuration_template['custom_fields'] as $field) {
                $fieldKey = $field['keyname'];
                if (isset($this->configuration[$fieldKey])) {
                    $configurationValues[$fieldKey] = $this->configuration[$fieldKey];
                }
            }
        }

        $this->plugin->update([
            'configuration' => $configurationValues
        ]);

        Flux::modal('configuration-modal')->close();
    }

    public function getDevicePlaylists($deviceId)
    {
        return \App\Models\Playlist::where('device_id', $deviceId)->get();
    }

    public function hasAnyPlaylistSelected(): bool
    {
        foreach ($this->checked_devices as $deviceId) {
            if (isset($this->device_playlists[$deviceId]) && !empty($this->device_playlists[$deviceId])) {
                return true;
            }
        }
        return false;
    }

    public function getConfigurationValue($key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
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
        $this->markup_code = $markup;
    }

    public function renderLayoutWithTitleBar(): string
    {
        if ($this->markup_language === 'liquid') {
            return <<<HTML
<div class="view view--{{ size }}">
    <div class="layout">
        <!-- ADD YOUR CONTENT HERE-->
    </div>
    <div class="title_bar">
        <span class="title">TRMNL BYOS</span>
    </div>
</div>
HTML;
        }

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
        if ($this->markup_language === 'liquid') {
            return <<<HTML
<div class="view view--{{ size }}">
    <div class="layout">
        <!-- ADD YOUR CONTENT HERE-->
    </div>
</div>
HTML;
        }

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

        // If data strategy is polling and data_payload is null, fetch the data first
        if ($this->plugin->data_strategy === 'polling' && $this->plugin->data_payload === null) {
            $this->updateData();
        }

        try {
            $previewMarkup = $this->plugin->render($size);
            $this->dispatch('preview-updated', preview: $previewMarkup);
        } catch (LiquidException $e) {
            $this->dispatch('preview-error', message: $e->toLiquidErrorMessage());
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

        public function loadXhrSelectOptions(string $fieldKey, string $endpoint, ?string $query = null): void
        {
            abort_unless(auth()->user()->plugins->contains($this->plugin), 403);

            try {
                $requestData = [];
                if ($query !== null) {
                    $requestData = [
                        'function' => $fieldKey,
                        'query' => $query
                    ];
                }

                $response = $query !== null
                    ? Http::post($endpoint, $requestData)
                    : Http::post($endpoint);

                if ($response->successful()) {
                    $this->xhrSelectOptions[$fieldKey] = $response->json();
                } else {
                    $this->xhrSelectOptions[$fieldKey] = [];
                }
            } catch (\Exception $e) {
                $this->xhrSelectOptions[$fieldKey] = [];
            }
        }

        public function searchXhrSelect(string $fieldKey, string $endpoint): void
        {
            $query = $this->searchQueries[$fieldKey] ?? '';
            if (!empty($query)) {
                $this->loadXhrSelectOptions($fieldKey, $endpoint, $query);
            }
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
                    <flux:button icon="eye" wire:click="renderPreview" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Preview</flux:button>
                </flux:modal.trigger>
                <flux:dropdown>
                    <flux:button icon="chevron-down" :disabled="$plugin->hasMissingRequiredConfigurationFields()"></flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-1Tx1B" wire:click="renderPreview('half_horizontal')" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Half-Horizontal
                            </flux:menu.item>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-1Lx1R" wire:click="renderPreview('half_vertical')" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Half-Vertical
                            </flux:menu.item>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="preview-plugin">
                            <flux:menu.item icon="mashup-2x2" wire:click="renderPreview('quadrant')" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Quadrant</flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>

            </flux:button.group>
            <flux:button.group>
                <flux:modal.trigger name="add-to-playlist">
                    <flux:button icon="play" variant="primary" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Add to Playlist</flux:button>
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

                    @if(count($checked_devices) > 0)
                        <flux:separator text="Playlist Selection" />
                        <div class="mt-4 mb-4 space-y-6">
                            @foreach($checked_devices as $deviceId)
                                @php
                                    $device = auth()->user()->devices->find($deviceId);
                                @endphp
                                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-3">
                                        {{ $device->name }}
                                    </div>

                                    <div class="mb-4">
                                        <flux:select wire:model.live.debounce="device_playlists.{{ $deviceId }}">
                                            <option value="">Select Playlist or Create New</option>
                                            @foreach($this->getDevicePlaylists($deviceId) as $playlist)
                                                <option value="{{ $playlist->id }}">{{ $playlist->name }}</option>
                                            @endforeach
                                            <option value="new">Create New Playlist</option>
                                        </flux:select>
                                    </div>

                                    @if(isset($device_playlists[$deviceId]) && $device_playlists[$deviceId] === 'new')
                                        <div class="space-y-4">
                                            <div>
                                                <flux:input label="Playlist Name" wire:model="device_playlist_names.{{ $deviceId }}"/>
                                            </div>
                                            <div>
                                                <flux:checkbox.group wire:model="device_weekdays.{{ $deviceId }}" label="Active Days (optional)">
                                                    <flux:checkbox label="Monday" value="1"/>
                                                    <flux:checkbox label="Tuesday" value="2"/>
                                                    <flux:checkbox label="Wednesday" value="3"/>
                                                    <flux:checkbox label="Thursday" value="4"/>
                                                    <flux:checkbox label="Friday" value="5"/>
                                                    <flux:checkbox label="Saturday" value="6"/>
                                                    <flux:checkbox label="Sunday" value="0"/>
                                                </flux:checkbox.group>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <flux:input type="time" label="Active From (optional)" wire:model="device_active_from.{{ $deviceId }}"/>
                                                </div>
                                                <div>
                                                    <flux:input type="time" label="Active Until (optional)" wire:model="device_active_until.{{ $deviceId }}"/>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(count($checked_devices) > 0 && $this->hasAnyPlaylistSelected())
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
                        <flux:button type="submit" variant="primary" :disabled="$plugin->hasMissingRequiredConfigurationFields()">Add to Playlist</flux:button>
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

        <flux:modal name="configuration-modal" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Configuration</flux:heading>
                    <flux:subheading>Configure your plugin settings</flux:subheading>
                </div>

                        <form wire:submit="saveConfiguration">
                            @if(isset($configuration_template['custom_fields']) && is_array($configuration_template['custom_fields']))
                                @foreach($configuration_template['custom_fields'] as $field)
                                    @php
                                        $fieldKey = $field['keyname'] ?? $field['key'] ?? $field['name'];
                                        $currentValue = $configuration[$fieldKey] ?? '';
                                    @endphp
                                    <div class="mb-4">
                                        @if($field['field_type'] === 'author_bio')
                                            @continue
                                        @endif

                                        @if($field['field_type'] === 'copyable_webhook_url')
                                            @continue
                                        @endif

                                        @if($field['field_type'] === 'string' || $field['field_type'] === 'url')
                                            <flux:input
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                            />
                                        @elseif($field['field_type'] === 'text')
                                            <flux:textarea
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                            />
                                        @elseif($field['field_type'] === 'code')
                                            <flux:textarea
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                rows="{{ $field['rows'] ?? 3 }}"
                                                placeholder="{{ $field['placeholder'] ?? null }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                                class="font-mono"
                                            />
                                        @elseif($field['field_type'] === 'password')
                                            <flux:input
                                                type="password"
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                                viewable
                                            />
                                        @elseif($field['field_type'] === 'copyable')
                                            <flux:input
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                value="{{ $field['value'] }}"
                                                copyable
                                            />
                                        @elseif($field['field_type'] === 'time_zone')
                                            <flux:select
                                                label="{{ $field['name'] }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                            >
                                                <option value="">Select timezone...</option>
                                                @foreach(timezone_identifiers_list() as $timezone)
                                                    <option value="{{ $timezone }}" {{ $currentValue === $timezone ? 'selected' : '' }}>{{ $timezone }}</option>
                                                @endforeach
                                            </flux:select>
                                        @elseif($field['field_type'] === 'number')
                                            <flux:input
                                                type="number"
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? $field['name'] }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                            />
                                        @elseif($field['field_type'] === 'boolean')
                                            <flux:checkbox
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? $field['name'] }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                :checked="$currentValue"
                                            />
                                        @elseif($field['field_type'] === 'date')
                                            <flux:input
                                                type="date"
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? $field['name'] }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                            />
                                        @elseif($field['field_type'] === 'time')
                                            <flux:input
                                                type="time"
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? $field['name'] }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                            />
                                        @elseif($field['field_type'] === 'select')
                                            @if(isset($field['multiple']) && $field['multiple'] === true)
                                                <flux:checkbox.group
                                                    label="{{ $field['name'] }}"
                                                    wire:model="configuration.{{ $fieldKey }}"
                                                    description="{{ $field['description'] ?? '' }}"
                                                    descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                >
                                                    @if(isset($field['options']) && is_array($field['options']))
                                                        @foreach($field['options'] as $option)
                                                            @if(is_array($option))
                                                                @foreach($option as $label => $value)
                                                                    <flux:checkbox label="{{ $label }}" value="{{ $value }}"/>
                                                                @endforeach
                                                            @else
                                                                @php
                                                                    $key = mb_strtolower(str_replace(' ', '_', $option));
                                                                @endphp
                                                                <flux:checkbox label="{{ $option }}" value="{{ $key }}"/>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </flux:checkbox.group>
                                            @else
                                                <flux:select
                                                    label="{{ $field['name'] }}"
                                                    wire:model="configuration.{{ $fieldKey }}"
                                                    description="{{ $field['description'] ?? '' }}"
                                                    descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                >
                                                    <option value="">Select {{ $field['name'] }}...</option>
                                                    @if(isset($field['options']) && is_array($field['options']))
                                                        @foreach($field['options'] as $option)
                                                            @if(is_array($option))
                                                                @foreach($option as $label => $value)
                                                                    <option value="{{ $value }}" {{ $currentValue === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                                @endforeach
                                                            @else
                                                                @php
                                                                    $key = mb_strtolower(str_replace(' ', '_', $option));
                                                                @endphp
                                                                <option value="{{ $key }}" {{ $currentValue === $key ? 'selected' : '' }}>{{ $option }}</option>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </flux:select>
                                            @endif
                                        @elseif($field['field_type'] === 'xhrSelect')
                                            <flux:select
                                                label="{{ $field['name'] }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? '' }}"
                                                wire:init="loadXhrSelectOptions('{{ $fieldKey }}', '{{ $field['endpoint'] }}')"
                                            >
                                                <option value="">Select {{ $field['name'] }}...</option>
                                                @if(isset($xhrSelectOptions[$fieldKey]) && is_array($xhrSelectOptions[$fieldKey]))
                                                    @foreach($xhrSelectOptions[$fieldKey] as $option)
                                                        @if(is_array($option))
                                                            @if(isset($option['id']) && isset($option['name']))
                                                                {{-- xhrSelectSearch format: { 'id' => 'db-456', 'name' => 'Team Goals' } --}}
                                                                <option value="{{ $option['id'] }}" {{ $currentValue === (string)$option['id'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                                                            @else
                                                                {{-- xhrSelect format: { 'Braves' => 123 } --}}
                                                                @foreach($option as $label => $value)
                                                                    <option value="{{ $value }}" {{ $currentValue === (string)$value ? 'selected' : '' }}>{{ $label }}</option>
                                                                @endforeach
                                                            @endif
                                                        @else
                                                            <option value="{{ $option }}" {{ $currentValue === (string)$option ? 'selected' : '' }}>{{ $option }}</option>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </flux:select>
                                        @elseif($field['field_type'] === 'xhrSelectSearch')
                                            <div class="space-y-2">

                                                <flux:label>{{ $field['name'] }}</flux:label>
                                                <flux:description>{{ $field['description'] ?? '' }}</flux:description>
                                                <flux:input.group>
                                                    <flux:input
                                                        wire:model="searchQueries.{{ $fieldKey }}"
                                                        placeholder="Enter search query..."
                                                    />
                                                    <flux:button
                                                        wire:click="searchXhrSelect('{{ $fieldKey }}', '{{ $field['endpoint'] }}')"
                                                        icon="magnifying-glass"/>
                                                </flux:input.group>
                                                <flux:description>{{ $field['help_text'] ?? '' }}</flux:description>
                                                @if((isset($xhrSelectOptions[$fieldKey]) && is_array($xhrSelectOptions[$fieldKey]) && count($xhrSelectOptions[$fieldKey]) > 0) || !empty($currentValue))
                                                    <flux:select
                                                        wire:model="configuration.{{ $fieldKey }}"
                                                    >
                                                        <option value="">Select {{ $field['name'] }}...</option>
                                                        @if(isset($xhrSelectOptions[$fieldKey]) && is_array($xhrSelectOptions[$fieldKey]))
                                                            @foreach($xhrSelectOptions[$fieldKey] as $option)
                                                                @if(is_array($option))
                                                                    @if(isset($option['id']) && isset($option['name']))
                                                                        {{-- xhrSelectSearch format: { 'id' => 'db-456', 'name' => 'Team Goals' } --}}
                                                                        <option value="{{ $option['id'] }}" {{ $currentValue === (string)$option['id'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                                                                    @else
                                                                        {{-- xhrSelect format: { 'Braves' => 123 } --}}
                                                                        @foreach($option as $label => $value)
                                                                            <option value="{{ $value }}" {{ $currentValue === (string)$value ? 'selected' : '' }}>{{ $label }}</option>
                                                                        @endforeach
                                                                    @endif
                                                                @else
                                                                    <option value="{{ $option }}" {{ $currentValue === (string)$option ? 'selected' : '' }}>{{ $option }}</option>
                                                                @endif
                                                            @endforeach
                                                        @endif
                                                        @if(!empty($currentValue) && (!isset($xhrSelectOptions[$fieldKey]) || empty($xhrSelectOptions[$fieldKey])))
                                                            {{-- Show current value even if no options are loaded --}}
                                                            <option value="{{ $currentValue }}" selected>{{ $currentValue }}</option>
                                                        @endif
                                                    </flux:select>
                                                @endif
                                            </div>
                                        @elseif($field['field_type'] === 'multi_string')
                                            <flux:input
                                                label="{{ $field['name'] }}"
                                                description="{{ $field['description'] ?? '' }}"
                                                descriptionTrailing="{{ $field['help_text'] ?? 'Enter multiple values separated by commas' }}"
                                                wire:model="configuration.{{ $fieldKey }}"
                                                value="{{ $currentValue }}"
                                                placeholder="{{ $field['placeholder'] ?? 'value1,value2' }}"
                                            />
                                        @else
                                            <flux:callout variant="warning">Field type "{{ $field['field_type'] }}" not yet supported</flux:callout>
                                        @endif
                                    </div>
                                @endforeach
                            @endif

                            <div class="flex">
                                <flux:spacer/>
                                <flux:button type="submit" variant="primary">Save Configuration</flux:button>
                            </div>
                        </form>
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

                    @php
                        $authorField = null;
                        if (isset($configuration_template['custom_fields'])) {
                            foreach ($configuration_template['custom_fields'] as $field) {
                                if ($field['field_type'] === 'author_bio') {
                                    $authorField = $field;
                                    break;
                                }
                            }
                        }
                    @endphp

                    @if($authorField)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ $authorField['description'] }}
                            </div>

                            @if(isset($authorField['github_url']) || isset($authorField['learn_more_url']) || isset($authorField['email_address']))
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if(isset($authorField['github_url']))
                                        @php
                                            $githubUrl = $authorField['github_url'];
                                            $githubUsername = null;

                                            // Extract username from various GitHub URL formats
                                            if (preg_match('/github\.com\/([^\/\?]+)/', $githubUrl, $matches)) {
                                                $githubUsername = $matches[1];
                                            }
                                        @endphp
                                        @if($githubUsername)<flux:label badge="{{ $githubUsername }}"/>@endif
                                    @endif
                                    @if(isset($authorField['learn_more_url']))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon:trailing="arrow-up-right"
                                            href="{{ $authorField['learn_more_url'] }}"
                                            target="_blank"
                                        >
                                            Learn More
                                        </flux:button>
                                    @endif

                                    @if(isset($authorField['github_url']))
                                        <flux:button
                                            size="sm"
                                            icon="github"
                                            variant="ghost"
                                            href="{{ $authorField['github_url'] }}"
                                            target="_blank"
                                        >
                                        </flux:button>
                                    @endif

                                    @if(isset($authorField['email_address']))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="envelope"
                                            href="mailto:{{ $authorField['email_address'] }}"
                                        >
                                        </flux:button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if(isset($configuration_template['custom_fields']) && !empty($configuration_template['custom_fields']))
                        @if($plugin->hasMissingRequiredConfigurationFields())
                            <flux:callout class="mb-2" variant="warning" icon="exclamation-circle" heading="Please set required configuration fields." />
                        @endif
                        <div class="mb-4">
                            <flux:modal.trigger name="configuration-modal">
                                <flux:button icon="cog" class="block mt-1 w-full">Configuration</flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                    <div class="mb-4">
                        <flux:radio.group wire:model.live="data_strategy" label="Data Strategy" variant="segmented">
                            <flux:radio value="polling" label="Polling"/>
                            <flux:radio value="webhook" label="Webhook"/>
                            <flux:radio value="static" label="Static"/>
                        </flux:radio.group>
                    </div>

                    @if($data_strategy === 'polling')
                        <div class="mb-4">
                            <flux:textarea label="Polling URL" description="You can use configuration variables with Liquid syntax. Supports multiple requests via line break separation" wire:model="polling_url" id="polling_url"
                                        placeholder="https://example.com/api"
                                        class="block w-full" type="text" name="polling_url" autofocus>
                            </flux:input>
                            <flux:button icon="cloud-arrow-down" wire:click="updateData" class="block mt-2 w-full">
                                Fetch data now
                            </flux:button>
                        </div>

                        <div class="mb-4">
                            <flux:radio.group wire:model.live="polling_verb" label="Polling Verb" variant="segmented">
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

                        @if($polling_verb === 'post')
                        <div class="mb-4">
                            <flux:textarea
                                label="Polling Body (e.g. for GraphQL queries)"
                                wire:model="polling_body"
                                id="polling_body"
                                class="block mt-1 w-full font-mono"
                                name="polling_body"
                                rows="6"
                            />
                        </div>
                        @endif
                        <div class="mb-4">
                            <flux:input label="Data is stale after minutes" wire:model="data_stale_minutes"
                                        id="data_stale_minutes"
                                        class="block mt-1 w-full" type="number" name="data_stale_minutes" autofocus/>
                        </div>
                    @elseif($data_strategy === 'webhook')
                        <div class="mb-4">
                            <flux:input
                                label="Webhook URL"
                                descriptionTrailing="Send JSON payload with key <code>merge_variables</code> to the webhook URL. The payload will be merged with the plugin data."
                                :value="route('api.custom_plugins.webhook', ['plugin_uuid' => $plugin->uuid])"
                                class="block mt-1 w-full font-mono"
                                readonly
                                copyable
                            >
                            </flux:input>
                        </div>
                    @elseif($data_strategy === 'static')
                        <flux:text class="mb-2">Enter static JSON data in the Data Payload field.</flux:text>
                    @endif

                    <div class="mb-4">
                        <flux:label>Screen Settings</flux:label>
                        <div class="mt-2 space-y-2">
                            <flux:checkbox
                                wire:model="no_bleed"
                                label="Remove bleed margin?"
                                description="If selected, padding around your markup will be removed."
                            />
                            <flux:checkbox
                                wire:model="dark_mode"
                                label="Enable Dark Mode?"
                                description="Inverts black/white pixels for the entire screen. Add class 'image' to img tags as needed."
                            />
                        </div>
                    </div>

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button type="submit" variant="primary" class="w-full">Save</flux:button>
                    </div>
                </form>
            </div>
            <div>
                <div class="mb-1">
                    <flux:label>Data Payload</flux:label>
                    @isset($this->data_payload_updated_at)
                        <flux:badge icon="clock" size="sm" variant="pill" class="ml-2">{{ $this->data_payload_updated_at?->diffForHumans() ?? 'Never' }}</flux:badge>
                    @endisset
                </div>
                <flux:error name="data_payload"/>
                <flux:field>
                    @php
                        $textareaId = 'payload-' . uniqid();
                    @endphp
                    <flux:textarea
                        wire:model="data_payload"
                        id="{{ $textareaId }}"
                        placeholder="Enter your HTML code here..."
                        rows="12"
                        hidden
                    />
                    <div
                        x-data="codeEditorFormComponent({
                                isDisabled: @js($data_strategy !== 'static'),
                                language: 'json',
                                state: $wire.entangle('data_payload'),
                                textareaId: @js($textareaId)
                            })"
                        wire:ignore
                        wire:key="cm-{{ $textareaId }}"
                        class="max-w-2xl min-h-[300px] h-[500px] overflow-hidden resize-y"
                    >
                        <!-- Loading state -->
                        <div x-show="isLoading" class="flex items-center justify-center h-full">
                            <div class="flex items-center space-x-2 ">
                                <flux:icon.loading />
                            </div>
                        </div>

                        <!-- Editor container -->
                        <div x-show="!isLoading" x-ref="editor" class="h-full"></div>
                    </div>
                </flux:field>


            </div>
        </div>
        <flux:separator class="my-5"/>
        <div>
            <h3 class="text-xl font-semibold dark:text-gray-100">Markup</h3>
            @if($plugin->render_markup_view)
                <div>
                    Edit view
                    <span class="font-mono text-accent mb-4">{{ $plugin->render_markup_view }}</span> to update.
                </div>
                <div class="mb-4 mt-4">
                    <flux:field>
                        @php
                            $textareaId = 'code-view-' . uniqid();
                        @endphp
                        <flux:textarea
                            wire:model="view_content"
                            id="{{ $textareaId }}"
                            placeholder="Enter your HTML code here..."
                            rows="25"
                            hidden
                        />
                        <div
                            x-data="codeEditorFormComponent({
                                isDisabled: false,
                                language: 'liquid',
                                state: $wire.entangle('markup_code'),
                                textareaId: @js($textareaId)
                            })"
                            wire:ignore
                            wire:key="cm-{{ $textareaId }}"
                            class="min-h-[300px] h-[300px] overflow-hidden resize-y"
                        >
                            <!-- Loading state -->
                            <div x-show="isLoading" class="flex items-center justify-center h-full">
                                <div class="flex items-center space-x-2">
                                    <flux:icon.loading />
                                </div>
                            </div>

                            <!-- Editor container -->
                            <div x-show="!isLoading" x-ref="editor" class="h-full"></div>
                        </div>
                    </flux:field>




                </div>
            @else
            <div class="flex items-center gap-6 mb-4 mt-4">
                <div class="flex-1 flex items-center">
                    <span class="pr-2">Template language</span>
                    <flux:radio.group wire:model.live="markup_language" variant="segmented">
                        <flux:radio value="blade" label="Blade"/>
                        <flux:radio value="liquid" label="Liquid"/>
                    </flux:radio.group>
                </div>
                <div class="text-accent flex items-center gap-2">
                    <span class="pr-2">Getting started</span>
                    <flux:button wire:click="renderExample('layoutTitle')" class="text-xl">Responsive Layout with Title Bar</flux:button>
                    <flux:button wire:click="renderExample('layout')" class="text-xl">Responsive Layout</flux:button>
                </div>
            </div>
            @endif
        </div>
        @if(!$plugin->render_markup_view)
            <form wire:submit="saveMarkup">
                <div class="mb-4">
                    <flux:field>
                        @php
                            $textareaId = 'code-' . uniqid();
                        @endphp
                        <flux:label>{{ $markup_language === 'liquid' ? 'Liquid Code' : 'Blade Code' }}</flux:label>
                        <flux:textarea
                            wire:model="markup_code"
                            id="{{ $textareaId }}"
                            placeholder="Enter your HTML code here..."
                            rows="25"
                            hidden
                        />
                        <div
                            x-data="codeEditorFormComponent({
                                isDisabled: false,
                                language: 'liquid',
                                state: $wire.entangle('markup_code'),
                                textareaId: @js($textareaId)
                            })"
                            wire:ignore
                            wire:key="cm-{{ $textareaId }}"
                            class="min-h-[300px] h-[300px] overflow-hidden resize-y"
                        >
                            <!-- Loading state -->
                            <div x-show="isLoading" class="flex items-center justify-center h-full">
                                <div class="flex items-center space-x-2">
                                    <flux:icon.loading />
                                </div>
                            </div>

                            <!-- Editor container -->
                            <div x-show="!isLoading" x-ref="editor" class="h-full"></div>
                        </div>
                    </flux:field>

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

    $wire.on('data-update-error', ({message}) => {
        alert('Data Update Error: ' + message);
    });
</script>
@endscript
