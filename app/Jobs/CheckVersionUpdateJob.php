<?php

namespace App\Jobs;

use App\Settings\UpdateSettings;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckVersionUpdateJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CACHE_KEY = 'latest_release';

    private const BACKOFF_KEY = 'github_api_backoff';

    private const BACKOFF_MINUTES = 10;

    private const CACHE_TTL = 86400;

    public function __construct(private bool $forceRefresh = false) {}

    public function handle(UpdateSettings $updateSettings): array
    {
        try {
            $currentVersion = config('app.version');

            if (! $currentVersion) {
                return $this->errorResponse();
            }

            $backoffUntil = Cache::get(self::BACKOFF_KEY);
            if ($this->isInBackoffPeriod($backoffUntil)) {
                return $this->rateLimitResponse($backoffUntil);
            }

            $cachedResponse = Cache::get(self::CACHE_KEY);
            $response = $this->fetchOrUseCache($cachedResponse, $updateSettings->prereleases, $backoffUntil);

            if (! $response) {
                return $this->errorResponse('fetch_failed');
            }

            [$latestVersion, $releaseData] = $this->extractLatestVersion($response, $updateSettings->prereleases);
            $isNewer = $latestVersion && version_compare($latestVersion, $currentVersion, '>');

            return [
                'latest_version' => $latestVersion,
                'is_newer' => $isNewer,
                'release_data' => $releaseData,
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            Log::error('Version check failed: '.$e->getMessage());

            return $this->errorResponse('connection_failed');
        } catch (Exception $e) {
            Log::error('Unexpected error in version check: '.$e->getMessage());

            return $this->errorResponse('unexpected_error');
        }
    }

    private function isInBackoffPeriod(?\Illuminate\Support\Carbon $backoffUntil): bool
    {
        return $backoffUntil !== null && now()->isBefore($backoffUntil);
    }

    private function rateLimitResponse(\Illuminate\Support\Carbon $backoffUntil): array
    {
        return [
            'latest_version' => null,
            'is_newer' => false,
            'release_data' => null,
            'error' => 'rate_limit',
            'backoff_until' => $backoffUntil->timestamp,
        ];
    }

    private function errorResponse(?string $error = null): array
    {
        return [
            'latest_version' => null,
            'is_newer' => false,
            'release_data' => null,
            'error' => $error,
        ];
    }

    private function fetchOrUseCache(?array $cachedResponse, bool $enablePrereleases, ?\Illuminate\Support\Carbon $backoffUntil): ?array
    {
        if ($cachedResponse && ! $this->forceRefresh) {
            return $cachedResponse;
        }

        if ($this->isInBackoffPeriod($backoffUntil)) {
            return $cachedResponse;
        }

        try {
            $httpResponse = $this->fetchReleases($enablePrereleases);

            if ($httpResponse->status() === 429) {
                return $this->handleRateLimit($cachedResponse);
            }

            if ($httpResponse->successful()) {
                $responseData = $httpResponse->json();
                Cache::put(self::CACHE_KEY, $responseData, self::CACHE_TTL);

                return $responseData;
            }

            Log::warning('GitHub API request failed', [
                'status' => $httpResponse->status(),
                'body' => $httpResponse->body(),
            ]);

            return $cachedResponse;
        } catch (ConnectionException $e) {
            Log::debug('Failed to fetch releases: '.$e->getMessage());

            return $cachedResponse ?? null;
        } catch (Exception $e) {
            Log::debug('Failed to fetch releases: '.$e->getMessage());

            return $cachedResponse ?? null;
        }
    }

    private function fetchReleases(bool $enablePrereleases)
    {
        $githubRepo = config('app.github_repo');
        $apiBaseUrl = "https://api.github.com/repos/{$githubRepo}";
        $endpoint = $enablePrereleases ? "{$apiBaseUrl}/releases" : "{$apiBaseUrl}/releases/latest";

        return Http::timeout(10)->connectTimeout(5)->get($endpoint);
    }

    private function handleRateLimit(?array $cachedResponse): ?array
    {
        $backoffUntil = now()->addMinutes(self::BACKOFF_MINUTES);
        Cache::put(self::BACKOFF_KEY, $backoffUntil, 600);
        Log::warning('GitHub API rate limit exceeded. Backing off for 10 minutes.');

        return $cachedResponse;
    }

    private function extractLatestVersion(array $response, bool $enablePrereleases): array
    {
        if (! $enablePrereleases || ! isset($response[0])) {
            return [
                Arr::get($response, 'tag_name'),
                $response,
            ];
        }

        [$stableRelease, $prerelease] = $this->findReleases($response);

        if ($prerelease && $stableRelease) {
            $prereleaseVersion = Arr::get($prerelease, 'tag_name');
            $stableVersion = Arr::get($stableRelease, 'tag_name');

            if (version_compare($prereleaseVersion, $stableVersion, '>')) {
                return [$prereleaseVersion, $prerelease];
            }

            return [$stableVersion, $stableRelease];
        }

        if ($prerelease) {
            return [Arr::get($prerelease, 'tag_name'), $prerelease];
        }

        if ($stableRelease) {
            return [Arr::get($stableRelease, 'tag_name'), $stableRelease];
        }

        return [null, null];
    }

    private function findReleases(array $allReleases): array
    {
        $stableRelease = null;
        $prerelease = null;

        foreach ($allReleases as $release) {
            $tagName = Arr::get($release, 'tag_name');
            if (! $tagName) {
                continue;
            }

            $isPrerelease = (bool) Arr::get($release, 'prerelease', false);

            if ($isPrerelease && ! $prerelease) {
                $prerelease = $release;
            } elseif (! $isPrerelease && ! $stableRelease) {
                $stableRelease = $release;
            }

            if ($stableRelease && $prerelease) {
                break;
            }
        }

        return [$stableRelease, $prerelease];
    }
}
