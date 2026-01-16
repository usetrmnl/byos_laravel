<?php

use App\Jobs\CheckVersionUpdateJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public ?string $latestVersion = null;

    public bool $isUpdateAvailable = false;

    public ?array $releaseData = null;

    public function mount(): void
    {
        $currentVersion = config('app.version');
        if (! $currentVersion) {
            return;
        }

        // Check cache first - only show content if cached
        $cachedResponse = Cache::get('latest_release');
        if ($cachedResponse) {
            $this->processCachedResponse($cachedResponse, $currentVersion);
        } else {
            // Defer job in background using dispatchAfterResponse
            CheckVersionUpdateJob::dispatchAfterResponse();
        }
    }

    private function processCachedResponse($response, string $currentVersion): void
    {
        $latestVersion = null;

        // Handle both single release object and array of releases
        if (is_array($response) && isset($response[0])) {
            // Array of releases - find the latest one
            $latestRelease = $response[0];
            $latestVersion = Arr::get($latestRelease, 'tag_name');
            $this->releaseData = $latestRelease;
        } else {
            // Single release object
            $latestVersion = Arr::get($response, 'tag_name');
            $this->releaseData = $response;
        }

        if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')) {
            $this->latestVersion = $latestVersion;
            $this->isUpdateAvailable = true;
        }
    }
} ?>

<div>
    @if(config('app.version') && $isUpdateAvailable && $latestVersion)
        <flux:callout class="text-xs mt-6" icon="arrow-down-circle">
            <flux:callout.heading>Update available</flux:callout.heading>
            <flux:callout.text>
                There is a newer version {{ $latestVersion }} available. Update to the latest version for the best experience.
                <flux:callout.link href="{{route('settings.update')}}" wire:navigate>Release notes</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif
</div>
