<?php

namespace App\Liquid\Filters;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Keepsuit\Liquid\Filters\FiltersProvider;

/**
 * Localization filters for Liquid templates
 *
 * Uses Laravel's translator for word translations. Translation files are located in the
 * lang/{locale}/custom_plugins.php files.
 */
class Localization extends FiltersProvider
{
    /**
     * Localize a date with strftime syntax
     *
     * @param  mixed  $date  The date to localize (string or DateTime)
     * @param  string  $format  The strftime format string
     * @param  string|null  $locale  The locale to use for localization
     * @return string The localized date string
     */
    public function l_date(mixed $date, string $format = 'Y-m-d', ?string $locale = null): string
    {
        $carbon = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        if ($locale) {
            $carbon->locale($locale);
        }

        return $carbon->translatedFormat($format);
    }

    /**
     * Translate a common word to another language
     *
     * @param  string  $word  The word to translate
     * @param  string  $locale  The locale to translate to
     * @return string The translated word
     */
    public function l_word(string $word, string $locale): string
    {
        $translation = trans('custom_plugins.'.mb_strtolower($word), locale: $locale);

        if ($translation === 'custom_plugins.'.mb_strtolower($word)) {
            return $word;
        }

        return $translation;
    }
}
