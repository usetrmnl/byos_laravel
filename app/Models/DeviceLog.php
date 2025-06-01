<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    protected function casts(): array
    {
        return [
            'log_entry' => 'array',
            'device_timestamp' => 'datetime',
        ];
    }
}
