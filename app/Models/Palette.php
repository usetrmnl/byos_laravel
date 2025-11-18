<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Palette model representing color palettes used by device models.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $palette JSON single dimension array where each entry is a 24-bit number
 *                           representing the RGB color code.
 */
final class Palette extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function deviceModels(): HasMany
    {
        return $this->hasMany(DeviceModel::class);
    }
}
