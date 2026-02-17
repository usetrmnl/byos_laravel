<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UpdateSettings extends Settings
{
    public bool $prereleases = false;

    public static function group(): string
    {
        return 'update';
    }
}
