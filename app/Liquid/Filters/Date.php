<?php

namespace App\Liquid\Filters;

use Carbon\Carbon;
use Keepsuit\Liquid\Filters\FiltersProvider;

/**
 * Data filters for Liquid templates
 */
class Date extends FiltersProvider
{
    /**
     * Calculate a date that is a specified number of days in the past
     *
     * @param  int|string  $num  The number of days to subtract
     * @return string The date in Y-m-d format
     */
    public function days_ago(int|string $num): string
    {
        $days = (int) $num;

        return Carbon::now()->subDays($days)->toDateString();
    }
}
