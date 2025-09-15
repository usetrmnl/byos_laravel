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

        return $this->bit_depth.'bit';
    }
}
