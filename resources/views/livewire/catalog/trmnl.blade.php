<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\PluginImportService;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public array $recipes = [];
    public string $search = '';
    public bool $isSearching = false;
    public string $installingPlugin = '';

    public function mount(): void
    {
        $this->loadNewest();
    }

    private function loadNewest(): void
    {
        try {
            $this->recipes = Cache::remember('trmnl_recipes_newest', 300, function () {
                $response = Http::get('https://usetrmnl.com/recipes.json', [
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

        $this->installingPlugin = $recipeId;

        try {
            $zipUrl = "https://usetrmnl.com/api/plugin_settings/{$recipeId}/archive";
            $plugin = $pluginImportService->importFromUrl($zipUrl, auth()->user());
            
            $this->dispatch('plugin-installed');
            Flux::modal('import-from-trmnl-catalog')->close();
            
        } catch (\Exception $e) {
            Log::error('Plugin installation failed: ' . $e->getMessage());
            $this->addError('installation', 'Error installing plugin: ' . $e->getMessage());
        } finally {
            $this->installingPlugin = '';
        }
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
                <div class="bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10 [:where(&)]:p-6 [:where(&)]:rounded-xl space-y-6">
                    <div class="flex items-start space-x-4">
                        @php($thumb = $recipe['icon_url'] ?? $recipe['screenshot_url'])
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $recipe['name'] }}" class="w-12 h-12 rounded-lg object-cover">
                        @else
                            <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <flux:icon name="puzzle-piece" class="w-6 h-6 text-gray-400" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $recipe['name'] }}</h3>
                                    @if(data_get($recipe, 'stats.installs'))
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Installs: {{ data_get($recipe, 'stats.installs') }} · Forks: {{ data_get($recipe, 'stats.forks') }}</p>
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
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $recipe['author_bio'] }}</p>
                            @endif

                            <div class="mt-4 flex items-center space-x-3">
                                @if($recipe['id'])
                                    @if($installingPlugin === $recipe['id'])
                                        <flux:button 
                                            wire:click="installPlugin('{{ $recipe['id'] }}')"
                                            variant="primary"
                                            disabled>
                                            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                        </flux:button>
                                    @else
                                        <flux:button 
                                            wire:click="installPlugin('{{ $recipe['id'] }}')"
                                            variant="primary">
                                            Install
                                        </flux:button>
                                    @endif
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
                </div>
            @endforeach
        </div>
    @endif
</div>
