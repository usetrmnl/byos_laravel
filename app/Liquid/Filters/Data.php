<?php

namespace App\Liquid\Filters;

use Keepsuit\Liquid\Filters\FiltersProvider;

/**
 * Data filters for Liquid templates
 */
class Data extends FiltersProvider
{
    /**
     * Convert a variable to JSON
     *
     * @param  mixed  $value  The variable to convert
     * @return string JSON representation of the variable
     */
    public function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
