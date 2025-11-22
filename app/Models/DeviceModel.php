<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class DeviceModel extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'colors' => 'integer',
        'bit_depth' => 'integer',
        'scale_factor' => 'float',
        'rotation' => 'integer',
        'offset_x' => 'integer',
        'offset_y' => 'integer',
        'published_at' => 'datetime',
    ];

    public function getColorDepthAttribute(): ?string
    {
        if (! $this->bit_depth) {
            return null;
        }

        if ($this->bit_depth === 3) {
            return '2bit';
        }

        // if higher than 4 return 4bit
        if ($this->bit_depth > 4) {
            return '4bit';
        }

        return $this->bit_depth.'bit';
    }

    /**
     * Returns the scale level based on the device width.
     */
    public function getScaleLevelAttribute(): ?string
    {
        if (! $this->width) {
            return null;
        }

        if ($this->width > 800 && $this->width <= 1000) {
            return 'large';
        }

        if ($this->width > 1000 && $this->width <= 1400) {
            return 'xlarge';
        }

        if ($this->width > 1400) {
            return 'xxlarge';
        }

        return null;
    }
}
