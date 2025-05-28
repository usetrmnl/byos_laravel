<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Device extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'proxy_cloud' => 'boolean',
        'last_log_request' => 'json',
        'proxy_cloud_response' => 'json',
        'width' => 'integer',
        'height' => 'integer',
        'rotate' => 'integer',
    ];

    public function getBatteryPercentAttribute()
    {
        $volts = $this->last_battery_voltage;

        // Define min and max voltage for Li-ion battery (3.0V empty, 4.2V full)
        $min_volt = 3.0;
        $max_volt = 4.2;

        // Ensure the voltage is within range
        if ($volts <= $min_volt) {
            return 0;
        } elseif ($volts >= $max_volt) {
            return 100;
        }

        // Calculate percentage
        $percent = (($volts - $min_volt) / ($max_volt - $min_volt)) * 100;

        return round($percent);
    }

    public function getWifiStrengthAttribute()
    {
        $rssi = $this->last_rssi_level;
        if ($rssi >= 0) {
            return 0; // No signal (0 bars)
        } elseif ($rssi <= -80) {
            return 1; // Weak signal (1 bar)
        } elseif ($rssi <= -60) {
            return 2; // Moderate signal (2 bars)
        } else {
            return 3; // Strong signal (3 bars)
        }
    }

    public function getUpdateFirmwareAttribute(): bool
    {
        if ($this->proxy_cloud_response && $this->proxy_cloud_response['update_firmware']) {
            return true;
        }

        if ($this->update_firmware_id) {
            return true;
        }

        return false;
    }

    public function getFirmwareUrlAttribute(): ?string
    {
        if ($this->proxy_cloud_response && $this->proxy_cloud_response['firmware_url']) {
            return $this->proxy_cloud_response['firmware_url'];
        }

        if ($this->update_firmware_id) {
            $firmware = Firmware::find($this->update_firmware_id);
            if ($firmware) {
                if ($firmware->storage_location) {
                    return Storage::disk('public')->url($firmware->storage_location);
                }

                return $firmware->url;
            }
        }

        return null;
    }

    public function resetUpdateFirmwareFlag(): void
    {
        if ($this->proxy_cloud_response) {
            $this->proxy_cloud_response = array_merge($this->proxy_cloud_response, ['update_firmware' => false]);
            $this->save();
        }
        if ($this->update_firmware_id) {
            $this->update_firmware_id = null;
            $this->save();
        }
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function getNextPlaylistItem(): ?PlaylistItem
    {
        // Get all active playlists
        $playlists = $this->playlists()
            ->where('is_active', true)
            ->get();

        // Find the first active playlist with an available item
        foreach ($playlists as $playlist) {
            if ($playlist->isActiveNow()) {
                $nextItem = $playlist->getNextPlaylistItem();
                if ($nextItem) {
                    return $nextItem;
                }
            }
        }

        return null;
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function mirrorDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'mirror_device_id');
    }

    public function updateFirmware(): BelongsTo
    {
        return $this->belongsTo(Firmware::class, 'update_firmware_id');
    }
}
