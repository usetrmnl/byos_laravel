<?php

namespace App\Services\Plugin\Parsers;

class ResponseParserRegistry
{
    /**
     * @var array<int, ResponseParser>
     */
    private readonly array $parsers;

    /**
     * @param  array<int, ResponseParser>  $parsers
     */
    public function __construct(array $parsers = [])
    {
        $this->parsers = $parsers ?: [
            new XmlResponseParser(),
            new IcalResponseParser(),
            new JsonOrTextResponseParser(),
        ];
    }

    /**
     * @return array<int, ResponseParser>
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }
}
