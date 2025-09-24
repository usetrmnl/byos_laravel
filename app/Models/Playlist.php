<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'weekdays' => 'array',
        'active_from' => 'datetime:H:i',
        'active_until' => 'datetime:H:i',
        'refresh_time' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }

    public function isActiveNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check weekday
        if ($this->weekdays !== null && ! in_array(now()->dayOfWeek, $this->weekdays)) {
            return false;
        }

        if ($this->active_from !== null && $this->active_until !== null) {
            $now = now();

            // Handle time ranges that span across midnight
            if ($this->active_from > $this->active_until) {
                // Time range spans midnight (e.g., 09:01 to 03:58)
                if ($now >= $this->active_from || $now <= $this->active_until) {
                    return true;
                }
            } elseif ($now >= $this->active_from && $now <= $this->active_until) {
                return true;
            }

            return false;
        }

        return true;
    }

    public function getNextPlaylistItem(): ?PlaylistItem
    {
        if (! $this->isActiveNow()) {
            return null;
        }

        // Get active playlist items ordered by display order
        /** @var \Illuminate\Support\Collection|PlaylistItem[] $playlistItems */
        $playlistItems = $this->items()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($playlistItems->isEmpty()) {
            return null;
        }

        // Get the last displayed item
        $lastDisplayed = $playlistItems
            ->sortByDesc('last_displayed_at')
            ->first();

        if (! $lastDisplayed || ! $lastDisplayed->last_displayed_at) {
            // If no item has been displayed yet, return the first one
            return $playlistItems->first();
        }

        // Find the next item in sequence
        $currentOrder = $lastDisplayed->order;
        $nextItem = $playlistItems
            ->where('order', '>', $currentOrder)
            ->first();

        // If there's no next item, loop back to the first one
        if (! $nextItem) {
            return $playlistItems->first();
        }

        return $nextItem;
    }
}
