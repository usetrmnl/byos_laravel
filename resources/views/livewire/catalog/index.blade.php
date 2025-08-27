<?php

use App\Services\PluginImportService;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

new class extends Component {
    public array $catalogPlugins = [];
    public string $installingPlugin = '';

    public function mount(): void
    {
        $this->loadCatalogPlugins();
    }

    private function loadCatalogPlugins(): void
    {
        $catalogUrl = config('app.catalog_url');

        $this->catalogPlugins = Cache::remember('catalog_plugins', 43200, function () use ($catalogUrl) {
            try {
                $response = Http::get($catalogUrl);
                $catalogContent = $response->body();
                $catalog = Yaml::parse($catalogContent);

                return collect($catalog)->map(function ($plugin, $key) {
                    return [
                        'id' => $key,
                        'name' => $plugin['name'] ?? 'Unknown Plugin',
                        'description' => $plugin['author_bio']['description'] ?? '',
                        'author' => $plugin['author']['name'] ?? 'Unknown Author',
                        'github' => $plugin['author']['github'] ?? null,
                        'license' => $plugin['license'] ?? null,
                        'zip_url' => $plugin['trmnlp']['zip_url'] ?? null,
                        'repo_url' => $plugin['trmnlp']['repo'] ?? null,
                        'logo_url' => $plugin['logo_url'] ?? null,
                        'screenshot_url' => $plugin['screenshot_url'] ?? null,
                        'learn_more_url' => $plugin['author_bio']['learn_more_url'] ?? null,
                    ];
                })->toArray();
            } catch (\Exception $e) {
                Log::error('Failed to load catalog from URL: ' . $e->getMessage());
                return [];
            }
        });
    }

    public function installPlugin(string $pluginId, PluginImportService $pluginImportService): void
    {
        abort_unless(auth()->user() !== null, 403);

        $plugin = collect($this->catalogPlugins)->firstWhere('id', $pluginId);

        if (!$plugin || !$plugin['zip_url']) {
            $this->addError('installation', 'Plugin not found or no download URL available.');
            return;
        }

        $this->installingPlugin = $pluginId;

        try {
            $importedPlugin = $pluginImportService->importFromUrl($plugin['zip_url'], auth()->user());

            $this->dispatch('plugin-installed');
            Flux::modal('import-from-catalog')->close();

        } catch (\Exception $e) {
            $this->addError('installation', 'Error installing plugin: ' . $e->getMessage());
        } finally {
            $this->installingPlugin = '';
        }
    }
}; ?>

<div class="space-y-4">
    @if(empty($catalogPlugins))
        <div class="text-center py-8">
            <flux:icon name="exclamation-triangle" class="mx-auto h-12 w-12 text-gray-400" />
            <flux:heading class="mt-2">No plugins available</flux:heading>
            <flux:subheading>Catalog is empty</flux:subheading>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4">
            @error('installation')
                <flux:callout variant="danger" icon="x-circle" heading="{{$message}}" />
            @enderror

            @foreach($catalogPlugins as $plugin)
                <div class="bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10 [:where(&)]:p-6 [:where(&)]:rounded-xl space-y-6">
                    <div class="flex items-start space-x-4">
                        @if($plugin['logo_url'])
                            <img src="{{ $plugin['logo_url'] }}" alt="{{ $plugin['name'] }}" class="w-12 h-12 rounded-lg object-cover">
                        @else
                            <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <flux:icon name="puzzle-piece" class="w-6 h-6 text-gray-400" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $plugin['name'] }}</h3>
                                    @if ($plugin['github'])
                                        <p class="text-sm text-gray-500 dark:text-gray-400">by {{ $plugin['github'] }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($plugin['license'])
                                        <flux:badge color="gray" size="sm">{{ $plugin['license'] }}</flux:badge>
                                    @endif
                                    @if($plugin['repo_url'])
                                        <a href="{{ $plugin['repo_url'] }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <flux:icon name="github" class="w-5 h-5" />
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if($plugin['description'])
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $plugin['description'] }}</p>
                            @endif

                            <div class="mt-4 flex items-center space-x-3">
                                <flux:button
                                    wire:click="installPlugin('{{ $plugin['id'] }}')"
                                    variant="primary">
                                    Install
                                </flux:button>

                                @if($plugin['learn_more_url'])
                                    <flux:button
                                        href="{{ $plugin['learn_more_url'] }}"
                                        target="_blank"
                                        variant="subtle">
                                        Learn More
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
