<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public bool $hasUpdate = false;

    public function mount(): void
    {
        $this->checkForUpdate();
    }

    public function checkForUpdate(): void
    {
        $currentVersion = config('app.version');
        if (! $currentVersion) {
            return;
        }

        $response = Cache::get('latest_release');
        if (! $response) {
            return;
        }

        // Handle both single release object and array of releases
        if (is_array($response) && isset($response[0])) {
            // Array of releases - find the latest one
            $latestRelease = $response[0];
            $latestVersion = Arr::get($latestRelease, 'tag_name');
        } else {
            // Single release object
            $latestVersion = Arr::get($response, 'tag_name');
        }

        if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')) {
            $this->hasUpdate = true;
        }
    }
} ?>

<span>
    @if($hasUpdate)
        <flux:badge color="yellow"><flux:icon name="sparkles" class="size-4"/></flux:badge>
    @endif
</span>
