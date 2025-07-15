<?php

namespace App\Liquid\Filters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Keepsuit\Liquid\Filters\FiltersProvider;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

/**
 * String, Markup, and HTML filters for Liquid templates
 */
class StringMarkup extends FiltersProvider
{
    /**
     * Pluralize a word based on count
     *
     * @param  string  $word  The word to pluralize
     * @param  int  $count  The count to determine pluralization
     * @return string The pluralized word with count
     */
    public function pluralize(string $word, int $count = 2): string
    {
        if ($count === 1) {
            return "{$count} {$word}";
        }

        return "{$count} ".Str::plural($word, $count);
    }

    /**
     * Convert markdown to HTML
     *
     * @param  string  $markdown  The markdown text to convert
     * @return string The HTML representation of the markdown
     */
    public function markdown_to_html(string $markdown): ?string
    {
        $converter = new CommonMarkConverter();

        try {
            return $converter->convert($markdown);
        } catch (CommonMarkException $e) {
            Log::error('Markdown conversion error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Strip HTML tags from a string
     *
     * @param  string  $html  The HTML string to strip
     * @return string The string without HTML tags
     */
    public function strip_html(string $html): string
    {
        return strip_tags($html);
    }
}
