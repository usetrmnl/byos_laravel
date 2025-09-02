<?php

namespace App\Liquid\Filters;

class StandardFilters extends \Keepsuit\Liquid\Filters\StandardFilters
{
    /**
     * Converts any URL-unsafe characters in a string to the
     * [percent-encoded](https://developer.mozilla.org/en-US/docs/Glossary/percent-encoding) equivalent.
     */
    public function urlEncode(string|int|float|array|null $input): string
    {

        if (is_array($input)) {
            $input = json_encode($input);
        }

        return parent::urlEncode($input);
    }
}
