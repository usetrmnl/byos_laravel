<?php

namespace App\Services\Plugin\Parsers;

use Illuminate\Http\Client\Response;

interface ResponseParser
{
    /**
     * Attempt to parse the given response.
     *
     * Return null when the parser is not applicable so other parsers can run.
     */
    public function parse(Response $response): ?array;
}
