<?php

namespace App\Liquid\Filters;

use App\Liquid\Utils\ExpressionUtils;
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

    /**
     * Format a date string with ordinal day (1st, 2nd, 3rd, etc.)
     *
     * @param  string  $dateStr  The date string to parse
     * @param  string  $strftimeExp  The strftime format string with <<ordinal_day>> placeholder
     * @return string The formatted date with ordinal day
     */
    public function ordinalize(string $dateStr, string $strftimeExp): string
    {
        $date = Carbon::parse($dateStr);
        $ordinalDay = $date->ordinal('day');
        
        // Convert strftime format to PHP date format
        $phpFormat = ExpressionUtils::strftimeToPhpFormat($strftimeExp);
        
        // Split the format string by the ordinal day placeholder
        $parts = explode('<<ordinal_day>>', $phpFormat);
        
        if (count($parts) === 2) {
            $before = $date->format($parts[0]);
            $after = $date->format($parts[1]);
            return $before . $ordinalDay . $after;
        }
        
        // Fallback: if no placeholder found, just format normally
        return $date->format($phpFormat);
    }

}
