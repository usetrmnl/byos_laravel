<?php

use Livewire\Volt\Component;

new class extends Component {

    public string $name;
    public int $data_stale_minutes = 60;
    public string $data_strategy = "polling";
    public string $polling_url;
    public string $polling_verb = "get";
    public $polling_header;
    public array $plugins;

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
    ];

    private function refreshPlugins(): void
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
            'uuid' => \Illuminate\Support\Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $this->name,
            'data_stale_minutes' => $this->data_stale_minutes,
            'data_strategy' => $this->data_strategy,
            'polling_url' => $this->polling_url ?? null,
            'polling_verb' => $this->polling_verb,
            'polling_header' => $this->polling_header,
        ]);

        $this->reset(['name', 'data_stale_minutes', 'data_strategy', 'polling_url', 'polling_verb', 'polling_header']);
        $this->refreshPlugins();

        Flux::modal('add-plugin')->close();
    }

    public function seedExamplePlugins(): void
    {
//        \Artisan::call('db:seed', ['--class' => 'ExampleRecipesSeeder']);
        \Artisan::call(\App\Console\Commands\ExampleRecipesSeederCommand::class, ['user_id' => auth()->id()]);

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
                        <flux:menu.item icon="beaker" wire:click="seedExamplePlugins">Seed Example Recipes</flux:menu.item>
                        {{--                        <flux:menu.separator/>--}}
                        {{--                        <flux:modal.trigger name="import-recipe">--}}
                        {{--                            <flux:menu.item icon="paper-clip">Import Recipe ZIP File</flux:menu.item>--}}
                        {{--                        </flux:modal.trigger>--}}
                        {{--                        <flux:menu.separator/>--}}
                        {{--                        <flux:modal.trigger name="add-native-plugin">--}}
                        {{--                            <flux:menu.item icon="code-bracket">New Native Plugin</flux:menu.item>--}}
                        {{--                        </flux:modal.trigger>--}}
                    </flux:menu>
                </flux:dropdown>
            </flux:button.group>


        </div>

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
                            <flux:radio.group wire:model="polling_verb" label="Polling Verb" variant="segmented">
                                <flux:radio value="get" label="GET"/>
                                <flux:radio value="post" label="POST"/>
                            </flux:radio.group>
                        </div>

                        <div class="mb-4">
                            <flux:input label="Polling Header" wire:model="polling_header" id="polling_header"
                                        class="block mt-1 w-full" type="text" name="polling_header" autofocus/>
                        </div>
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
