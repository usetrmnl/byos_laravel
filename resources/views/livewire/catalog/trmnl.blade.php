<?php

use App\Services\PluginImportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new
#[Lazy]
class extends Component
{
    public array $recipes = [];

    public int $page = 1;

    public bool $hasMore = false;

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
            $cacheKey = 'trmnl_recipes_newest_page_'.$this->page;
            $response = Cache::remember($cacheKey, 43200, function () {
                $response = Http::timeout(10)->get('https://usetrmnl.com/recipes.json', [
                    'sort-by' => 'newest',
                    'page' => $this->page,
                ]);

                if (! $response->successful()) {
                    throw new RuntimeException('Failed to fetch TRMNL recipes');
                }

                return $response->json();
            });

            $data = $response['data'] ?? [];
            $mapped = $this->mapRecipes($data);

            if ($this->page === 1) {
                $this->recipes = $mapped;
            } else {
                $this->recipes = array_merge($this->recipes, $mapped);
            }

            $this->hasMore = ! empty($response['next_page_url']);
        } catch (Throwable $e) {
            Log::error('TRMNL catalog load error: '.$e->getMessage());
            if ($this->page === 1) {
                $this->recipes = [];
            }
            $this->hasMore = false;
        }
    }

    private function searchRecipes(string $term): void
    {
        $this->isSearching = true;
        try {
            $cacheKey = 'trmnl_recipes_search_'.md5($term).'_page_'.$this->page;
            $response = Cache::remember($cacheKey, 300, function () use ($term) {
                $response = Http::get('https://usetrmnl.com/recipes.json', [
                    'search' => $term,
                    'sort-by' => 'newest',
                    'page' => $this->page,
                ]);

                if (! $response->successful()) {
                    throw new RuntimeException('Failed to search TRMNL recipes');
                }

                return $response->json();
            });

            $data = $response['data'] ?? [];
            $mapped = $this->mapRecipes($data);

            if ($this->page === 1) {
                $this->recipes = $mapped;
            } else {
                $this->recipes = array_merge($this->recipes, $mapped);
            }

            $this->hasMore = ! empty($response['next_page_url']);
        } catch (Throwable $e) {
            Log::error('TRMNL catalog search error: '.$e->getMessage());
            if ($this->page === 1) {
                $this->recipes = [];
            }
            $this->hasMore = false;
        } finally {
            $this->isSearching = false;
        }
    }

    public function loadMore(): void
    {
        $this->page++;

        $term = mb_trim($this->search);
        if ($term === '' || mb_strlen($term) < 2) {
            $this->loadNewest();
        } else {
            $this->searchRecipes($term);
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $term = mb_trim($this->search);
        if ($term === '') {
            $this->loadNewest();

            return;
        }

        if (mb_strlen($term) < 2) {
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

        } catch (Exception $e) {
            Log::error('Plugin installation failed: '.$e->getMessage());
            $this->addError('installation', 'Error installing plugin: '.$e->getMessage());
        }
    }

    public function previewRecipe(string $recipeId): void
    {
        $this->previewingRecipe = $recipeId;
        $this->previewData = [];

        try {
            $response = Http::timeout(10)->get("https://usetrmnl.com/recipes/{$recipeId}.json");

            if ($response->successful()) {
                $item = $response->json()['data'] ?? [];
                $this->previewData = $this->mapRecipe($item);
            } else {
                // Fallback to searching for the specific recipe if single endpoint doesn't exist
                $response = Http::timeout(10)->get('https://usetrmnl.com/recipes.json', [
                    'search' => $recipeId,
                ]);

                if ($response->successful()) {
                    $data = $response->json()['data'] ?? [];
                    $item = collect($data)->firstWhere('id', $recipeId);
                    if ($item) {
                        $this->previewData = $this->mapRecipe($item);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('TRMNL catalog preview fetch error: '.$e->getMessage());
        }

        if (empty($this->previewData)) {
            $this->previewData = collect($this->recipes)->firstWhere('id', $recipeId) ?? [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapRecipes(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => $this->mapRecipe($item))
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapRecipe(array $item): array
    {
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
            'detail_url' => isset($item['id']) ? ('https://usetrmnl.com/recipes/'.$item['id']) : null,
        ];
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
        <flux:badge color="zinc">Newest</flux:badge>
    </div>

    @error('installation')
        <flux:callout variant="danger" icon="x-circle" heading="{{$message}}" />
    @enderror

    @if(empty($recipes))
        <div class="text-center py-8">
            <flux:icon name="exclamation-triangle" class="mx-auto h-12 w-12 text-zinc-400" />
            <flux:heading class="mt-2">No recipes found</flux:heading>
            <flux:subheading>Try a different search term</flux:subheading>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4">
            @foreach($recipes as $recipe)
                <div wire:key="recipe-{{ $recipe['id'] }}" class="rounded-xl dark:bg-white/10 border border-zinc-200 dark:border-white/10 shadow-xs">
                    <div class="px-10 py-8 space-y-6">
                        <div class="flex items-start space-x-4">
                        @php($thumb = $recipe['icon_url'] ?? $recipe['screenshot_url'])
                        @if($thumb)
                            <img src="{{ $thumb }}" loading="lazy" alt="{{ $recipe['name'] }}" class="w-12 h-12 rounded-lg object-cover">
                        @else
                            <div class="w-12 h-12 bg-zinc-200 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                <flux:icon name="puzzle-piece" class="w-6 h-6 text-zinc-400" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="lg">{{ $recipe['name'] }}</flux:heading>
                                    @if(data_get($recipe, 'stats.installs'))
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Installs: {{ data_get($recipe, 'stats.installs') }} · Forks: {{ data_get($recipe, 'stats.forks') }}</flux:text>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($recipe['detail_url'])
                                        <a href="{{ $recipe['detail_url'] }}" target="_blank" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
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

                                @if($recipe['id'] && ($recipe['screenshot_url'] ?? null))
                                    <flux:modal.trigger name="trmnl-catalog-preview">
                                        <flux:button
                                            wire:click="previewRecipe('{{ $recipe['id'] }}')"
                                            variant="subtle"
                                            icon="eye">
                                            Preview
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($hasMore)
            <div class="flex justify-center mt-6">
                <flux:button wire:click="loadMore" variant="subtle" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="loadMore">Load next page</span>
                    <span wire:loading wire:target="loadMore">Loading...</span>
                </flux:button>
            </div>
        @endif
    @endif

    <!-- Preview Modal -->
    <flux:modal name="trmnl-catalog-preview" class="min-w-[850px] min-h-[480px] space-y-6">
        <div wire:loading wire:target="previewRecipe" class="flex items-center justify-center py-12">
            <div class="flex items-center space-x-2">
                <flux:icon.loading />
                <flux:text>Fetching recipe details...</flux:text>
            </div>
        </div>

        <div wire:loading.remove wire:target="previewRecipe">
            @if($previewingRecipe && !empty($previewData))
                <div>
                    <flux:heading size="lg" class="mb-2">Preview {{ $previewData['name'] ?? 'Recipe' }}</flux:heading>
                </div>

                <div class="space-y-4">
                    <div class="bg-white dark:bg-zinc-900 rounded-lg overflow-hidden">
                        <img src="{{ $previewData['screenshot_url'] }}"
                             alt="Preview of {{ $previewData['name'] }}"
                             class="w-full h-auto max-h-[480px] object-contain">
                    </div>

                    @if($previewData['author_bio'])
                        <div class="rounded-xl dark:bg-white/10 border border-zinc-200 dark:border-white/10 shadow-xs">
                            <div class="px-10 py-8">
                                <flux:heading size="sm" class="mb-2">Description</flux:heading>
                                <flux:text size="sm">{{ $previewData['author_bio'] }}</flux:text>
                            </div>
                        </div>
                    @endif

                    @if(data_get($previewData, 'stats.installs'))
                        <div class="rounded-xl dark:bg-white/10 border border-zinc-200 dark:border-white/10 shadow-xs">
                            <div class="px-10 py-8">
                                <flux:heading size="sm" class="mb-2">Statistics</flux:heading>
                                <flux:text size="sm">
                                    Installs: {{ data_get($previewData, 'stats.installs') }} ·
                                    Forks: {{ data_get($previewData, 'stats.forks') }}
                                </flux:text>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700 space-x-3">
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
        </div>
    </flux:modal>
</div>
