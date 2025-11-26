<?php

use App\Services\PluginImportService;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

new
#[Lazy]
class extends Component {
    public array $catalogPlugins = [];
    public string $installingPlugin = '';
    public string $previewingPlugin = '';
    public array $previewData = [];

    public function mount(): void
    {
        $this->loadCatalogPlugins();
    }

    public function placeholder()
    {
        return <<<'HTML'
         <div class="space-y-4">
            <div class="flex items-center justify-center py-12">
                <div class="flex items-center space-x-2">
                    <flux:icon.loading />
                    <flux:text>Loading recipes...</flux:text>
                </div>
            </div>
        </div>
        HTML;
    }

    private function loadCatalogPlugins(): void
    {
        $catalogUrl = config('app.catalog_url');

        $this->catalogPlugins = Cache::remember('catalog_plugins', 43200, function () use ($catalogUrl) {
            try {
                $response = Http::timeout(10)->get($catalogUrl);
                $catalogContent = $response->body();
                $catalog = Yaml::parse($catalogContent);

                $currentVersion = config('app.version');

                return collect($catalog)
                    ->filter(function ($plugin) use ($currentVersion) {
                        // Check if Laravel compatibility is true
                        if (!Arr::get($plugin, 'byos.byos_laravel.compatibility', false)) {
                            return false;
                        }

                        // Check minimum version if specified
                        $minVersion = Arr::get($plugin, 'byos.byos_laravel.min_version');
                        if ($minVersion && $currentVersion && version_compare($currentVersion, $minVersion, '<')) {
                            return false;
                        }

                        return true;
                    })
                    ->map(function ($plugin, $key) {
                        return [
                            'id' => $key,
                            'name' => Arr::get($plugin, 'name', 'Unknown Plugin'),
                            'description' => Arr::get($plugin, 'author_bio.description', ''),
                            'author' => Arr::get($plugin, 'author.name', 'Unknown Author'),
                            'github' => Arr::get($plugin, 'author.github'),
                            'license' => Arr::get($plugin, 'license'),
                            'zip_url' => Arr::get($plugin, 'trmnlp.zip_url'),
                            'zip_entry_path' => Arr::get($plugin, 'trmnlp.zip_entry_path'),
                            'repo_url' => Arr::get($plugin, 'trmnlp.repo'),
                            'logo_url' => Arr::get($plugin, 'logo_url'),
                            'screenshot_url' => Arr::get($plugin, 'screenshot_url'),
                            'learn_more_url' => Arr::get($plugin, 'author_bio.learn_more_url'),
                        ];
                    })
                    ->sortBy('name')
                    ->toArray();
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
            $importedPlugin = $pluginImportService->importFromUrl(
                $plugin['zip_url'],
                auth()->user(),
                $plugin['zip_entry_path'] ?? null,
                null,
                $plugin['logo_url'] ?? null
            );

            $this->dispatch('plugin-installed');
            Flux::modal('import-from-catalog')->close();

        } catch (\Exception $e) {
            $this->addError('installation', 'Error installing plugin: ' . $e->getMessage());
        } finally {
            $this->installingPlugin = '';
        }
    }

    public function previewPlugin(string $pluginId): void
    {
        $plugin = collect($this->catalogPlugins)->firstWhere('id', $pluginId);

        if (!$plugin) {
            $this->addError('preview', 'Plugin not found.');
            return;
        }

        $this->previewingPlugin = $pluginId;
        $this->previewData = $plugin;

        // Store scroll position for restoration later
        $this->dispatch('store-scroll-position');
    }

    public function closePreview(): void
    {
        $this->previewingPlugin = '';
        $this->previewData = [];

        // Restore scroll position when returning to catalog
        $this->dispatch('restore-scroll-position');
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
                            <img src="{{ $plugin['logo_url'] }}" loading="lazy" alt="{{ $plugin['name'] }}" class="w-12 h-12 rounded-lg object-cover">
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

                                <flux:modal.trigger name="catalog-preview">
                                    <flux:button
                                        wire:click="previewPlugin('{{ $plugin['id'] }}')"
                                        variant="subtle"
                                        icon="eye">
                                        Preview
                                    </flux:button>
                                </flux:modal.trigger>



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

    <!-- Preview Modal -->
    <flux:modal name="catalog-preview" class="min-w-[850px] min-h-[480px] space-y-6">
        @if($previewingPlugin && !empty($previewData))
            <div>
                <flux:heading size="lg">Preview {{ $previewData['name'] ?? 'Plugin' }}</flux:heading>
            </div>

            <div class="space-y-4">
                @if($previewData['screenshot_url'])
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden">
                        <img src="{{ $previewData['screenshot_url'] }}"
                             alt="Preview of {{ $previewData['name'] }}"
                             class="w-full h-auto max-h-[480px] object-contain">
                    </div>
                @elseif($previewData['logo_url'])
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden p-8 text-center">
                        <img src="{{ $previewData['logo_url'] }}"
                             alt="{{ $previewData['name'] }} logo"
                             class="mx-auto h-32 w-auto object-contain mb-4">
                        <p class="text-gray-600 dark:text-gray-400">No preview image available</p>
                    </div>
                @else
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden p-8 text-center">
                        <flux:icon name="puzzle-piece" class="mx-auto h-32 w-32 text-gray-400 mb-4" />
                        <p class="text-gray-600 dark:text-gray-400">No preview available</p>
                    </div>
                @endif

                @if($previewData['description'])
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Description</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $previewData['description'] }}</p>
                    </div>
                @endif

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700 space-x-3">
                    <flux:modal.close>
                        <flux:button
                            wire:click="installPlugin('{{ $previewingPlugin }}')"
                            variant="primary">
                            Install Plugin
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@script
<script>
    let catalogScrollPosition = 0;

    $wire.on('store-scroll-position', () => {
        const catalogModal = document.querySelector('[data-flux-modal="import-from-catalog"]');
        if (catalogModal) {
            const scrollContainer = catalogModal.querySelector('.space-y-4') || catalogModal;
            catalogScrollPosition = scrollContainer.scrollTop || 0;
        }
    });

    $wire.on('restore-scroll-position', () => {
        // Small delay to ensure modal is fully rendered
        setTimeout(() => {
            const catalogModal = document.querySelector('[data-flux-modal="import-from-catalog"]');
            if (catalogModal) {
                const scrollContainer = catalogModal.querySelector('.space-y-4') || catalogModal;
                scrollContainer.scrollTop = catalogScrollPosition;
            }
        }, 100);
    });

    // Listen for when the catalog modal is opened and restore scroll position
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-flux-modal-open') {
                    const target = mutation.target;
                    if (target.getAttribute('data-flux-modal') === 'import-from-catalog' &&
                        target.getAttribute('data-flux-modal-open') === 'true') {
                        // Modal was opened, restore scroll position
                        setTimeout(() => {
                            const scrollContainer = target.querySelector('.space-y-4') || target;
                            if (catalogScrollPosition > 0) {
                                scrollContainer.scrollTop = catalogScrollPosition;
                            }
                        }, 100);
                    }
                }
            });
        });

        const catalogModal = document.querySelector('[data-flux-modal="import-from-catalog"]');
        if (catalogModal) {
            observer.observe(catalogModal, { attributes: true });
        }
    });
</script>
@endscript
