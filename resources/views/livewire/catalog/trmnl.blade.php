<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public array $recipes = [];
    public string $search = '';
    public string $error = '';
    public bool $isSearching = false;

    public function mount(): void
    {
        $this->loadNewest();
    }

    private function loadNewest(): void
    {
        $this->error = '';
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
            $this->error = 'Failed to load the TRMNL catalog. Please try again later.';
        }
    }

    private function searchRecipes(string $term): void
    {
        $this->error = '';
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
            $this->error = 'Search failed. Please try again later.';
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
                        ? ($item['author_bio']['description'] ?? null)
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

    @if($error)
        <flux:callout variant="danger" icon="x-circle" heading="{{ $error }}" />
    @endif

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
                                <flux:tooltip text="Installation via cloud coming soon">
                                    <flux:button disabled variant="primary">
                                        Install
                                    </flux:button>
                                </flux:tooltip>

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
