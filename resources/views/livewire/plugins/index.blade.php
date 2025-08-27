<?php

use App\Console\Commands\ExampleRecipesSeederCommand;
use App\Services\PluginImportService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads;

    public string $name;
    public int $data_stale_minutes = 60;
    public string $data_strategy = "polling";
    public string $polling_url;
    public string $polling_verb = "get";
    public $polling_header;
    public $polling_body;
    public array $plugins;
    public $zipFile;

    public array $native_plugins = [
        'markup' =>
            ['name' => 'Markup', 'flux_icon_name' => 'code-bracket', 'detail_view_route' => 'plugins.markup'],
        'api' =>
            ['name' => 'API', 'flux_icon_name' => 'braces', 'detail_view_route' => 'plugins.api'],
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
        'data_stale_minutes' => 'required|integer|min:1',
        'data_strategy' => 'required|string|in:polling,webhook,static',
        'polling_url' => 'required_if:data_strategy,polling|nullable|url',
        'polling_verb' => 'required|string|in:get,post',
        'polling_header' => 'nullable|string|max:255',
        'polling_body' => 'nullable|string',
    ];

    public function refreshPlugins(): void
    {
        $userPlugins = auth()->user()?->plugins?->map(function ($plugin) {
            return $plugin->toArray();
        })->toArray();

        $this->plugins = array_merge($this->native_plugins, $userPlugins ?? []);
    }

    public function mount(): void
    {
        $this->refreshPlugins();
    }

    public function addPlugin(): void
    {
        abort_unless(auth()->user() !== null, 403);
        $this->validate();

        \App\Models\Plugin::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $this->name,
            'data_stale_minutes' => $this->data_stale_minutes,
            'data_strategy' => $this->data_strategy,
            'polling_url' => $this->polling_url ?? null,
            'polling_verb' => $this->polling_verb,
            'polling_header' => $this->polling_header,
            'polling_body' => $this->polling_body,
        ]);

        $this->reset(['name', 'data_stale_minutes', 'data_strategy', 'polling_url', 'polling_verb', 'polling_header', 'polling_body']);
        $this->refreshPlugins();

        Flux::modal('add-plugin')->close();
    }

    public function seedExamplePlugins(): void
    {
        Artisan::call(ExampleRecipesSeederCommand::class, ['user_id' => auth()->id()]);
        $this->refreshPlugins();

    }


    public function importZip(PluginImportService $pluginImportService): void
    {
        abort_unless(auth()->user() !== null, 403);

        $this->validate([
            'zipFile' => 'required|file|mimes:zip|max:10240', // 10MB max
        ]);

        try {
            $plugin = $pluginImportService->importFromZip($this->zipFile, auth()->user());

            $this->refreshPlugins();
            $this->reset(['zipFile']);

            Flux::modal('import-zip')->close();
        } catch (\Exception $e) {
            $this->addError('zipFile', 'Error installing plugin: ' . $e->getMessage());
        }
    }

};
?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold dark:text-gray-100">Plugins &amp; Recipes</h2>

            <flux:button.group>
                <flux:modal.trigger name="add-plugin">
                    <flux:button icon="plus" variant="primary">Add Recipe</flux:button>
                </flux:modal.trigger>

                <flux:dropdown>
                    <flux:button icon="chevron-down" variant="primary"></flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="import-zip">
                            <flux:menu.item icon="archive-box">Import Recipe Archive</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="import-from-catalog">
                            <flux:menu.item icon="book-open">Import from Catalog</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.item icon="beaker" wire:click="seedExamplePlugins">Seed Example Recipes</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </flux:button.group>


        </div>

        <flux:modal name="import-zip" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Import Recipe
                        <flux:badge color="yellow" class="ml-2">Alpha</flux:badge>
                    </flux:heading>
                    <flux:subheading>Upload a ZIP archive containing a TRMNL recipe — either exported from the cloud service or structured using the <a href="https://github.com/usetrmnl/trmnlp" target="_blank" class="underline">trmnlp</a> project structure.</flux:subheading>
                </div>

                <div class="mb-4">
                    <flux:text>The archive must at least contain <code>settings.yml</code> and <code>full.liquid</code> files.</flux:text>
{{--                    <p>The ZIP file should contain the following structure:</p>--}}
{{--                    <pre class="mt-2 p-2 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-auto">--}}
{{--.--}}
{{--├── src--}}
{{--│   ├── full.liquid (required)--}}
{{--│   ├── settings.yml (required)--}}
{{--│   └── ...--}}
{{--└── ...--}}
{{--                    </pre>--}}
                </div>

                <div class="mb-4">
                    <flux:heading size="sm">Limitations</flux:heading>
                    <ul class="list-disc pl-5 mt-2">
                        <li><flux:text>Only full view will be imported; shared markup will be prepended</flux:text></li>
                        <li><flux:text>Some Liquid filters may be not supported or behave differently</flux:text></li>
                        <li><flux:text>API responses in formats other than JSON are not yet supported</flux:text></li>
{{--                        <ul class="list-disc pl-5 mt-2">--}}
{{--                            <li><flux:text><code>date: "%N"</code> is unsupported. Use <code>date: "u"</code> instead </flux:text></li>--}}
{{--                        </ul>--}}
                    </ul>
                    <flux:text class="mt-1">Please report <a href="https://github.com/usetrmnl/byos_laravel/issues/new" target="_blank" class="underline">issues on GitHub</a>. Include your example zip file.</flux:text></li>
                </div>

                <form wire:submit="importZip">
                    <div class="mb-4">
                        <flux:label for="zipFile">.zip Archive</flux:label>
                        <input
                            type="file"
                            wire:model="zipFile"
                            id="zipFile"
                            accept=".zip"
                            class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 p-2.5"
                        />
                        @error('zipFile')
                            <flux:callout variant="danger" icon="x-circle" heading="{{$message}}" class="mt-2" />
                        @enderror
                    </div>

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button type="submit" variant="primary">Import</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        <flux:modal name="import-from-catalog">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Import from Catalog
                        <flux:badge color="yellow" class="ml-2">Alpha</flux:badge>
                    </flux:heading>
                    <flux:subheading>Browse and install Recipes from the community. Add yours <a href="https://github.com/bnussbau/trmnl-recipe-catalog" class="underline" target="_blank">here</a>.</flux:subheading>
                </div>
                <livewire:catalog.index @plugin-installed="refreshPlugins" />
            </div>
        </flux:modal>

        <flux:modal name="add-plugin" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Add Recipe</flux:heading>
                </div>

                <form wire:submit="addPlugin">
                    <div class="mb-4">
                        <flux:input label="Name" wire:model="name" id="name" class="block mt-1 w-full" type="text"
                                    name="name" autofocus/>
                    </div>

                    <div class="mb-4">
                        <flux:radio.group wire:model.live="data_strategy" label="Data Strategy" variant="segmented">
                            <flux:radio value="polling" label="Polling"/>
                            <flux:radio value="webhook" label="Webhook"/>
                            <flux:radio value="static" label="Static"/>
                        </flux:radio.group>
                    </div>

                    @if($data_strategy === 'polling')
                        <div class="mb-4">
                            <flux:input label="Polling URL" wire:model="polling_url" id="polling_url"
                                        placeholder="https://example.com/api"
                                        class="block mt-1 w-full" type="text" name="polling_url" autofocus/>
                        </div>

                        <div class="mb-4">
                            <flux:radio.group wire:model.live="polling_verb" label="Polling Verb" variant="segmented">
                                <flux:radio value="get" label="GET"/>
                                <flux:radio value="post" label="POST"/>
                            </flux:radio.group>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Polling Header" wire:model="polling_header" id="polling_header"
                                        class="block mt-1 w-full" type="text" name="polling_header" autofocus/>
                        </div>

                        @if($polling_verb === 'post')
                        <div class="mb-4">
                            <flux:textarea
                                label="Polling Body"
                                wire:model="polling_body"
                                id="polling_body"
                                class="block mt-1 w-full font-mono"
                                name="polling_body"
                                rows="4"
                                placeholder=''
                            />
                        </div>
                        @endif
                        <div class="mb-4">
                            <flux:input label="Data is stale after minutes" wire:model.live="data_stale_minutes"
                                        id="data_stale_minutes"
                                        class="block mt-1 w-full" type="number" name="data_stale_minutes" autofocus/>
                        </div>
                    @endif

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button type="submit" variant="primary">Create Recipe</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($plugins as $plugin)
                <div
                    class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                    <a href="{{ ($plugin['detail_view_route']) ? route($plugin['detail_view_route']) : route('plugins.recipe', ['plugin' => $plugin['id']]) }}"
                       class="block">
                        <div class="flex items-center space-x-4 px-10 py-8">
                            <flux:icon name="{{$plugin['flux_icon_name'] ?? 'puzzle-piece'}}"
                                       class="text-4xl text-accent"/>
                            <h3 class="text-lg font-medium dark:text-zinc-200">{{$plugin['name']}}</h3>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
