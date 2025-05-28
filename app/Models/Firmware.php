<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Firmware extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latest' => 'boolean',
        ];
    }

    public static function getLatest(): ?self
    {
        return self::where('latest', true)->first();
    }
}
