<?php

namespace App\Liquid\Filters;

use Illuminate\Support\Number;
use Keepsuit\Liquid\Filters\FiltersProvider;

class Numbers extends FiltersProvider
{
    /**
     * Format a number with delimiters (default: comma)
     *
     * @param  mixed  $value  The number to format
     * @param  string  $delimiter  The delimiter to use (default: comma)
     * @param  string  $separator  The separator for decimal part (default: period)
     */
    public function number_with_delimiter(mixed $value, string $delimiter = ',', string $separator = '.'): string
    {
        // 2 decimal places for floats, 0 for integers
        $decimal = is_float($value + 0) ? 2 : 0;

        return number_format($value, decimals: $decimal, decimal_separator: $separator, thousands_separator: $delimiter);
    }

    /**
     * Format a number as currency
     *
     * @param  mixed  $value  The number to format
     * @param  string  $currency  Currency symbol or locale code
     * @param  string  $delimiter  The delimiter to use (default: comma)
     * @param  string  $separator  The separator for decimal part (default: period)
     */
    public function number_to_currency(mixed $value, string $currency = 'USD', string $delimiter = ',', string $separator = '.'): string
    {
        if ($currency === '$') {
            $currency = 'USD';
        } elseif ($currency === '€') {
            $currency = 'EUR';
        } elseif ($currency === '£') {
            $currency = 'GBP';
        }

        if ($delimiter === '.' && $separator === ',') {
            $locale = 'de';
        } else {
            $locale = 'en';
        }

        // 2 decimal places for floats, 0 for integers
        $decimal = is_float($value + 0) ? 2 : 0;

        return Number::currency($value, in: $currency, precision: $decimal, locale: $locale);
    }
}
