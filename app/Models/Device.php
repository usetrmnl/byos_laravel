<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read DeviceModel|null $deviceModel
 */
class Device extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'battery_notification_sent' => 'boolean',
        'proxy_cloud' => 'boolean',
        'last_log_request' => 'json',
        'proxy_cloud_response' => 'json',
        'width' => 'integer',
        'height' => 'integer',
        'rotate' => 'integer',
        'last_refreshed_at' => 'datetime',
        'sleep_mode_enabled' => 'boolean',
        'sleep_mode_from' => 'datetime:H:i',
        'sleep_mode_to' => 'datetime:H:i',
        'special_function' => 'string',
        'pause_until' => 'datetime',
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
        }
        if ($volts >= $max_volt) {
            return 100;
        }

        // Calculate percentage
        $percent = (($volts - $min_volt) / ($max_volt - $min_volt)) * 100;

        return round($percent);
    }

    /**
     * Calculate battery voltage from percentage
     *
     * @param  int  $percent  Battery percentage (0-100)
     * @return float Calculated voltage
     */
    public function calculateVoltageFromPercent(int $percent): float
    {
        // Define min and max voltage for Li-ion battery (3.0V empty, 4.2V full)
        $min_volt = 3.0;
        $max_volt = 4.2;

        // Ensure the percentage is within range
        if ($percent <= 0) {
            return $min_volt;
        }
        if ($percent >= 100) {
            return $max_volt;
        }

        // Calculate voltage
        $voltage = $min_volt + (($percent / 100) * ($max_volt - $min_volt));

        return round($voltage, 2);
    }

    public function getWifiStrengthAttribute()
    {
        $rssi = $this->last_rssi_level;
        if ($rssi >= 0) {
            return 0; // No signal (0 bars)
        }
        if ($rssi <= -80) {
            return 1; // Weak signal (1 bar)
        }
        if ($rssi <= -60) {
            return 2; // Moderate signal (2 bars)
        }

        return 3; // Strong signal (3 bars)

    }

    public function getUpdateFirmwareAttribute(): bool
    {
        if ($this->update_firmware_id) {
            return true;
        }

        if ($this->proxy_cloud_response && $this->proxy_cloud_response['update_firmware']) {
            return true;
        }

        return false;
    }

    public function getFirmwareUrlAttribute(): ?string
    {
        if ($this->update_firmware_id) {
            $firmware = Firmware::find($this->update_firmware_id);
            if ($firmware) {
                if ($firmware->storage_location) {
                    return Storage::disk('public')->url($firmware->storage_location);
                }

                return $firmware->url;
            }
        }

        if ($this->proxy_cloud_response && $this->proxy_cloud_response['firmware_url']) {
            return $this->proxy_cloud_response['firmware_url'];
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
        /** @var \Illuminate\Support\Collection|Playlist[] $playlists */
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
        return $this->belongsTo(self::class, 'mirror_device_id');
    }

    public function updateFirmware(): BelongsTo
    {
        return $this->belongsTo(Firmware::class, 'update_firmware_id');
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    /**
     * Get the color depth string (e.g., "4bit") for the associated device model.
     */
    public function colorDepth(): ?string
    {
        return $this->deviceModel?->color_depth;
    }

    /**
     * Get the scale level (e.g., large/xlarge/xxlarge) for the associated device model.
     */
    public function scaleLevel(): ?string
    {
        return $this->deviceModel?->scale_level;
    }

    /**
     * Get the device variant name, defaulting to 'og' if not available.
     */
    public function deviceVariant(): string
    {
        return $this->deviceModel->name ?? 'og';
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSleepModeActive(?DateTimeInterface $now = null): bool
    {
        if (! $this->sleep_mode_enabled || ! $this->sleep_mode_from || ! $this->sleep_mode_to) {
            return false;
        }

        $now = $now ? Carbon::instance($now) : now();

        // Handle overnight ranges (e.g. 22:00 to 06:00)
        return $this->sleep_mode_from < $this->sleep_mode_to
            ? $now->between($this->sleep_mode_from, $this->sleep_mode_to)
            : ($now->gte($this->sleep_mode_from) || $now->lte($this->sleep_mode_to));
    }

    public function getSleepModeEndsInSeconds(?DateTimeInterface $now = null): ?int
    {
        if (! $this->sleep_mode_enabled || ! $this->sleep_mode_from || ! $this->sleep_mode_to) {
            return null;
        }

        $now = $now ? Carbon::instance($now) : now();
        $from = $this->sleep_mode_from;
        $to = $this->sleep_mode_to;

        // Handle overnight ranges (e.g. 22:00 to 06:00)
        if ($from < $to) {
            // Normal range, same day
            return $now->between($from, $to) ? (int) $now->diffInSeconds($to, false) : null;
        }
        // Overnight range
        if ($now->gte($from)) {
            // After 'from', before midnight
            return (int) $now->diffInSeconds($to->copy()->addDay(), false);
        }
        if ($now->lt($to)) {
            // After midnight, before 'to'
            return (int) $now->diffInSeconds($to, false);
        }

        // Not in sleep window
        return null;

    }

    public function isPauseActive(): bool
    {
        return $this->pause_until && $this->pause_until->isFuture();
    }
}
