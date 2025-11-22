<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array|null $colors
 */
final class DevicePalette extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'grays' => 'integer',
        'colors' => 'array',
    ];
}
