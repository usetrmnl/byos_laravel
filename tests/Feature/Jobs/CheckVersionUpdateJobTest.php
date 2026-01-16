<?php

use App\Jobs\CheckVersionUpdateJob;
use App\Settings\UpdateSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Cache::flush();
    config(['app.version' => '1.0.0']);
});

test('it returns latest version when update is available', function (): void {
    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases/latest" => Http::response([
            'tag_name' => '1.1.0',
            'body' => 'Release notes',
        ], 200),
    ]);

    $result = (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    expect($result)
        ->latest_version->toBe('1.1.0')
        ->is_newer->toBeTrue()
        ->release_data->not->toBeNull();
});

test('it returns false when no update is available', function (): void {
    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases/latest" => Http::response([
            'tag_name' => '1.0.0',
            'body' => 'Release notes',
        ], 200),
    ]);

    $result = (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    expect($result)
        ->latest_version->toBe('1.0.0')
        ->is_newer->toBeFalse();
});

test('it caches the release data', function (): void {
    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases/latest" => Http::response([
            'tag_name' => '1.1.0',
            'body' => 'Release notes',
        ], 200),
    ]);

    (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    expect(Cache::has('latest_release'))->toBeTrue();
});

test('it forces refresh when forceRefresh is true', function (): void {
    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases/latest" => Http::sequence()
            ->push([
                'tag_name' => '1.1.0',
                'body' => 'Release notes',
            ], 200)
            ->push([
                'tag_name' => '1.2.0',
                'body' => 'New release notes',
            ], 200),
    ]);

    // First call caches
    (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    // Second call with force refresh should make new request
    $result = (new CheckVersionUpdateJob(true))->handle(app(UpdateSettings::class));

    expect($result['latest_version'])->toBe('1.2.0');
    Http::assertSentCount(2);
});

test('it handles pre-releases when enabled', function (): void {
    $updateSettings = app(UpdateSettings::class);
    $updateSettings->prereleases = true;
    $updateSettings->save();

    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases" => Http::response([
            [
                'tag_name' => '1.2.0-beta',
                'body' => 'Beta release',
                'prerelease' => true,
            ],
            [
                'tag_name' => '1.1.0',
                'body' => 'Stable release',
                'prerelease' => false,
            ],
        ], 200),
    ]);

    $result = (new CheckVersionUpdateJob)->handle($updateSettings);

    // Should prefer pre-release if newer
    expect($result['latest_version'])->toBe('1.2.0-beta');
});

test('it returns null when no version is configured', function (): void {
    config(['app.version' => null]);

    $result = (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    expect($result)
        ->latest_version->toBeNull()
        ->is_newer->toBeFalse()
        ->release_data->toBeNull();
});

test('it handles API failures gracefully', function (): void {
    $githubRepo = config('app.github_repo');
    $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";

    Http::fake([
        "{$apiBaseUrl}/releases/latest" => Http::response([], 500),
    ]);

    $result = (new CheckVersionUpdateJob)->handle(app(UpdateSettings::class));

    expect($result)
        ->latest_version->toBeNull()
        ->is_newer->toBeFalse()
        ->release_data->toBeNull();
});
