<?php

namespace App\Liquid\Filters;

use App\Facades\QrCode;
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

    /**
     * Generate a QR code as SVG from the input text
     *
     * @param  string  $text  The text to encode in the QR code
     * @param  int|null  $moduleSize  Optional module size (defaults to 11, which equals 319px)
     * @param  string|null  $errorCorrection  Optional error correction level: 'l', 'm', 'q', 'h' (defaults to 'm')
     * @return string The SVG QR code
     */
    public function qr_code(string $text, ?int $moduleSize = null, ?string $errorCorrection = null): string
    {
        // Default module_size is 11
        // Size calculation: (21 modules for QR code + 4 modules margin on each side * 2) * module_size
        // = (21 + 8) * module_size = 29 * module_size
        $moduleSize ??= 11;
        $size = 29 * $moduleSize;

        $qrCode = QrCode::format('svg')
            ->size($size);

        // Set error correction level if provided
        if ($errorCorrection !== null) {
            $qrCode->errorCorrection($errorCorrection);
        }

        return $qrCode->generate($text);
    }
}
