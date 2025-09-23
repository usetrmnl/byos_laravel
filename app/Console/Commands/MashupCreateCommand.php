<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MashupCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mashup:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new mashup and add it to a playlist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Select device
        $device = $this->selectDevice();
        if (! $device) {
            return 1;
        }

        // Select playlist
        $playlist = $this->selectPlaylist($device);
        if (! $playlist) {
            return 1;
        }

        // Select mashup layout
        $layout = $this->selectLayout();
        if (! $layout) {
            return 1;
        }

        // Get mashup name
        $name = $this->getMashupName();
        if (! $name) {
            return 1;
        }

        // Select plugins
        $plugins = $this->selectPlugins($layout);
        if ($plugins->isEmpty()) {
            return 1;
        }

        $maxOrder = $playlist->items()->max('order') ?? 0;

        // Create playlist item with mashup
        PlaylistItem::createMashup(
            playlist: $playlist,
            layout: $layout,
            pluginIds: $plugins->pluck('id')->toArray(),
            name: $name,
            order: $maxOrder + 1
        );

        $this->info('Mashup created successfully!');

        return 0;
    }

    protected function selectDevice(): ?Device
    {
        $devices = Device::all();
        if ($devices->isEmpty()) {
            $this->error('No devices found. Please create a device first.');

            return null;
        }

        $deviceId = $this->choice(
            'Select a device',
            $devices->mapWithKeys(fn ($device) => [$device->id => $device->name])->toArray()
        );

        return $devices->firstWhere('id', $deviceId);
    }

    protected function selectPlaylist(Device $device): ?Playlist
    {
        /** @var Collection|Playlist[] $playlists */
        $playlists = $device->playlists;
        if ($playlists->isEmpty()) {
            $this->error('No playlists found for this device. Please create a playlist first.');

            return null;
        }

        $playlistId = $this->choice(
            'Select a playlist',
            $playlists->mapWithKeys(fn (Playlist $playlist) => [$playlist->id => $playlist->name])->toArray()
        );

        return $playlists->firstWhere('id', $playlistId);
    }

    protected function selectLayout(): ?string
    {
        return $this->choice(
            'Select a layout',
            PlaylistItem::getAvailableLayouts()
        );
    }

    protected function getMashupName(): ?string
    {
        $name = $this->ask('Enter a name for this mashup', 'Mashup');

        if (mb_strlen($name) < 2) {
            $this->error('The name must be at least 2 characters.');

            return null;
        }

        if (mb_strlen($name) > 50) {
            $this->error('The name must not exceed 50 characters.');

            return null;
        }

        return $name;
    }

    protected function selectPlugins(string $layout): Collection
    {
        $requiredCount = PlaylistItem::getRequiredPluginCountForLayout($layout);

        $plugins = Plugin::all();
        if ($plugins->isEmpty()) {
            $this->error('No plugins found. Please create some plugins first.');

            return collect();
        }

        $selectedPlugins = collect();
        $availablePlugins = $plugins->mapWithKeys(fn ($plugin) => [$plugin->id => $plugin->name])->toArray();

        for ($i = 0; $i < $requiredCount; ++$i) {
            $position = match ($i) {
                0 => 'first',
                1 => 'second',
                2 => 'third',
                3 => 'fourth',
                default => ($i + 1).'th'
            };

            $pluginId = $this->choice(
                "Select the $position plugin",
                $availablePlugins
            );

            $selectedPlugins->push($plugins->firstWhere('id', $pluginId));
            unset($availablePlugins[$pluginId]);
        }

        return $selectedPlugins;
    }
}
