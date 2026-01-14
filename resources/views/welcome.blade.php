<x-layouts::auth.card>
    <div class="flex flex-col gap-6">
        <x-auth-header title="TRMNL BYOS Laravel" description="Server is up and running."/>
    </div>
    <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mt-6 not-has-[nav]:hidden">
        @if (Route::has('login'))
            <nav class="flex items-center justify-end gap-4">
                @auth
                    <a
                        href="{{ url('/dashboard') }}"
                        class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                    >
                        Dashboard
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                    >
                        Log in
                    </a>

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                            Register
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>
    @auth
        @if(config('app.version'))
            <flux:text class="text-xs">Version: <a href="https://github.com/usetrmnl/byos_laravel/releases/"
                                                   target="_blank">{{ config('app.version') }}</a>
            </flux:text>

            @php
                $response = Cache::remember('latest_release', 86400, function () {
                     try {
                         $response = Http::get('https://api.github.com/repos/usetrmnl/byos_laravel/releases/latest');
                         if ($response->successful()) {
                             return $response->json();
                         }
                     } catch (\Exception $e) {
                         Log::debug('Failed to fetch latest release: ' . $e->getMessage());
                     }
                     return null;
                 });
                 $latestVersion = Arr::get($response, 'tag_name');

                 if ($latestVersion && version_compare($latestVersion, config('app.version'), '>')) {
                     $newVersion = $latestVersion;
                 }
            @endphp

            @if(isset($newVersion))
                <flux:callout class="text-xs mt-6" icon="arrow-down-circle">
                    <flux:callout.heading>Update available</flux:callout.heading>
                    <flux:callout.text>
                        There is a newer version {{ $newVersion }} available. Update to the latest version for the best experience.
                        <flux:callout.link href="https://github.com/usetrmnl/byos_laravel/releases/" target="_blank">Release notes</flux:callout.link>
                    </flux:callout.text>
                </flux:callout>
            @endif
        @endif
    @endauth
</x-layouts::auth.card>
