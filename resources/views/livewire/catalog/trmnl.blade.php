<?php

use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\PluginImportService;
use Illuminate\Support\Facades\Auth;

new
#[Lazy]
class extends Component {
    public array $recipes = [];
    public string $search = '';
    public bool $isSearching = false;
    public string $previewingRecipe = '';
    public array $previewData = [];

    public function mount(): void
    {
        $this->loadNewest();
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

    private function loadNewest(): void
    {
        try {
            $this->recipes = Cache::remember('trmnl_recipes_newest', 43200, function () {
                $response = Http::timeout(10)->get('https://usetrmnl.com/recipes.json', [
                    'sort-by' => 'newest',
                ]);

                if (!$response->successful()) {
                    throw new \RuntimeException('Failed to fetch TRMNL recipes');
                }

                $json = $response->json();
                $data = $json['data'] ?? [];
                return $this->mapRecipes($data);
            });
        } catch (\Throwable $e) {
            Log::error('TRMNL catalog load error: ' . $e->getMessage());
            $this->recipes = [];
        }
    }

    private function searchRecipes(string $term): void
    {
        $this->isSearching = true;
        try {
            $cacheKey = 'trmnl_recipes_search_' . md5($term);
            $this->recipes = Cache::remember($cacheKey, 300, function () use ($term) {
                $response = Http::get('https://usetrmnl.com/recipes.json', [
                    'search' => $term,
                    'sort-by' => 'newest',
                ]);

                if (!$response->successful()) {
                    throw new \RuntimeException('Failed to search TRMNL recipes');
                }

                $json = $response->json();
                $data = $json['data'] ?? [];
                return $this->mapRecipes($data);
            });
        } catch (\Throwable $e) {
            Log::error('TRMNL catalog search error: ' . $e->getMessage());
            $this->recipes = [];
        } finally {
            $this->isSearching = false;
        }
    }

    public function updatedSearch(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            $this->loadNewest();
            return;
        }

        if (strlen($term) < 2) {
            // Require at least 2 chars to avoid noisy calls
            return;
        }

        $this->searchRecipes($term);
    }

    public function installPlugin(string $recipeId, PluginImportService $pluginImportService): void
    {
        abort_unless(auth()->user() !== null, 403);

        try {
            $zipUrl = "https://usetrmnl.com/api/plugin_settings/{$recipeId}/archive";

            $recipe = collect($this->recipes)->firstWhere('id', $recipeId);

            $plugin = $pluginImportService->importFromUrl(
                $zipUrl,
                auth()->user(),
                null,
                config('services.trmnl.liquid_enabled') ? 'trmnl-liquid' : null,
                $recipe['icon_url'] ?? null
            );

            $this->dispatch('plugin-installed');
            Flux::modal('import-from-trmnl-catalog')->close();

        } catch (\Exception $e) {
            Log::error('Plugin installation failed: ' . $e->getMessage());
            $this->addError('installation', 'Error installing plugin: ' . $e->getMessage());
        }
    }

    public function previewRecipe(string $recipeId): void
    {
        $recipe = collect($this->recipes)->firstWhere('id', $recipeId);

        if (!$recipe) {
            $this->addError('preview', 'Recipe not found.');
            return;
        }

        $this->previewingRecipe = $recipeId;
        $this->previewData = $recipe;

        // Store scroll position for restoration later
        $this->dispatch('store-scroll-position');
    }

    public function closePreview(): void
    {
        $this->previewingRecipe = '';
        $this->previewData = [];

        // Restore scroll position when returning to catalog
        $this->dispatch('restore-scroll-position');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function mapRecipes(array $items): array
    {
        return collect($items)
            ->map(function (array $item) {
                return [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? 'Untitled',
                    'icon_url' => $item['icon_url'] ?? null,
                    'screenshot_url' => $item['screenshot_url'] ?? null,
                    'author_bio' => is_array($item['author_bio'] ?? null)
                        ? strip_tags($item['author_bio']['description'] ?? null)
                        : null,
                    'stats' => [
                        'installs' => data_get($item, 'stats.installs'),
                        'forks' => data_get($item, 'stats.forks'),
                    ],
                    'detail_url' => isset($item['id']) ? ('https://usetrmnl.com/recipes/' . $item['id']) : null,
                ];
            })
            ->toArray();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center gap-3">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.400ms="search"
                placeholder="Search TRMNL recipes (min 2 chars)..."
                icon="magnifying-glass"
            />
        </div>
        <flux:badge color="gray">Newest</flux:badge>
    </div>

    @error('installation')
        <flux:callout variant="danger" icon="x-circle" heading="{{$message}}" />
    @enderror

    @if(empty($recipes))
        <div class="text-center py-8">
            <flux:icon name="exclamation-triangle" class="mx-auto h-12 w-12 text-gray-400" />
            <flux:heading class="mt-2">No recipes found</flux:heading>
            <flux:subheading>Try a different search term</flux:subheading>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4">
            @foreach($recipes as $recipe)
                <flux:card class="space-y-6">
                    <div class="flex items-start space-x-4">
                        @php($thumb = $recipe['icon_url'] ?? $recipe['screenshot_url'])
                        @if($thumb)
                            <img src="{{ $thumb }}" loading="lazy" alt="{{ $recipe['name'] }}" class="w-12 h-12 rounded-lg object-cover">
                        @else
                            <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <flux:icon name="puzzle-piece" class="w-6 h-6 text-gray-400" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="lg">{{ $recipe['name'] }}</flux:heading>
                                    @if(data_get($recipe, 'stats.installs'))
                                        <flux:text variant="subtle" size="sm">Installs: {{ data_get($recipe, 'stats.installs') }} · Forks: {{ data_get($recipe, 'stats.forks') }}</flux:text>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($recipe['detail_url'])
                                        <a href="{{ $recipe['detail_url'] }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <flux:icon name="arrow-top-right-on-square" class="w-5 h-5" />
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if($recipe['author_bio'])
                                <flux:text class="mt-2" size="sm">{{ $recipe['author_bio'] }}</flux:text>
                            @endif

                            <div class="mt-4 flex items-center space-x-3">
                                @if($recipe['id'])
                                    <flux:button
                                        wire:click="installPlugin('{{ $recipe['id'] }}')"
                                        variant="primary">
                                        Install
                                    </flux:button>
                                @endif

                                @if($recipe['id'])
                                    <flux:modal.trigger name="trmnl-catalog-preview">
                                        <flux:button
                                            wire:click="previewRecipe('{{ $recipe['id'] }}')"
                                            variant="subtle"
                                            icon="eye">
                                            Preview
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif



                                @if($recipe['detail_url'])
                                    <flux:button
                                        href="{{ $recipe['detail_url'] }}"
                                        target="_blank"
                                        variant="subtle">
                                        View on TRMNL
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <!-- Preview Modal -->
    <flux:modal name="trmnl-catalog-preview" class="min-w-[850px] min-h-[480px] space-y-6">
        @if($previewingRecipe && !empty($previewData))
            <div>
                <flux:heading size="lg">Preview {{ $previewData['name'] ?? 'Recipe' }}</flux:heading>
            </div>

            <div class="space-y-4">
                @if($previewData['screenshot_url'])
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden">
                        <img src="{{ $previewData['screenshot_url'] }}"
                             alt="Preview of {{ $previewData['name'] }}"
                             class="w-full h-auto max-h-[480px] object-contain">
                    </div>
                @elseif($previewData['icon_url'])
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden p-8 text-center">
                        <img src="{{ $previewData['icon_url'] }}"
                             alt="{{ $previewData['name'] }} icon"
                             class="mx-auto h-32 w-auto object-contain mb-4">
                        <flux:text variant="subtle">No preview image available</flux:text>
                    </div>
                @else
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden p-8 text-center">
                        <flux:icon name="puzzle-piece" class="mx-auto h-32 w-32 text-gray-400 mb-4" />
                        <flux:text variant="subtle">No preview available</flux:text>
                    </div>
                @endif

                @if($previewData['author_bio'])
                    <flux:card variant="subtle">
                        <flux:heading size="sm" class="mb-2">Description</flux:heading>
                        <flux:text size="sm">{{ $previewData['author_bio'] }}</flux:text>
                    </flux:card>
                @endif

                @if(data_get($previewData, 'stats.installs'))
                    <flux:card variant="subtle">
                        <flux:heading size="sm" class="mb-2">Statistics</flux:heading>
                        <flux:text size="sm">
                            Installs: {{ data_get($previewData, 'stats.installs') }} ·
                            Forks: {{ data_get($previewData, 'stats.forks') }}
                        </flux:text>
                    </flux:card>
                @endif

                <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700 space-x-3">
                    @if($previewData['detail_url'])
                        <flux:button
                            href="{{ $previewData['detail_url'] }}"
                            target="_blank"
                            variant="subtle">
                            View on TRMNL
                        </flux:button>
                    @endif
                    <flux:modal.close>
                        <flux:button
                            wire:click="installPlugin('{{ $previewingRecipe }}')"
                            variant="primary">
                            Install Recipe
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@script
<script>
    let trmnlCatalogScrollPosition = 0;

    $wire.on('store-scroll-position', () => {
        const catalogModal = document.querySelector('[data-flux-modal="import-from-trmnl-catalog"]');
        if (catalogModal) {
            const scrollContainer = catalogModal.querySelector('.space-y-4') || catalogModal;
            trmnlCatalogScrollPosition = scrollContainer.scrollTop || 0;
        }
    });

    $wire.on('restore-scroll-position', () => {
        // Small delay to ensure modal is fully rendered
        setTimeout(() => {
            const catalogModal = document.querySelector('[data-flux-modal="import-from-trmnl-catalog"]');
            if (catalogModal) {
                const scrollContainer = catalogModal.querySelector('.space-y-4') || catalogModal;
                scrollContainer.scrollTop = trmnlCatalogScrollPosition;
            }
        }, 100);
    });

    // Listen for when the catalog modal is opened and restore scroll position
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-flux-modal-open') {
                    const target = mutation.target;
                    if (target.getAttribute('data-flux-modal') === 'import-from-trmnl-catalog' &&
                        target.getAttribute('data-flux-modal-open') === 'true') {
                        // Modal was opened, restore scroll position
                        setTimeout(() => {
                            const scrollContainer = target.querySelector('.space-y-4') || target;
                            if (trmnlCatalogScrollPosition > 0) {
                                scrollContainer.scrollTop = trmnlCatalogScrollPosition;
                            }
                        }, 100);
                    }
                }
            });
        });

        const catalogModal = document.querySelector('[data-flux-modal="import-from-trmnl-catalog"]');
        if (catalogModal) {
            observer.observe(catalogModal, { attributes: true });
        }
    });
</script>
@endscript
