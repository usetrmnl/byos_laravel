<?php

namespace App\Services\Plugin\Parsers;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class XmlResponseParser implements ResponseParser
{
    public function parse(Response $response): ?array
    {
        $contentType = $response->header('Content-Type');

        if (! $contentType || ! str_contains(mb_strtolower($contentType), 'xml')) {
            return null;
        }

        try {
            $xml = simplexml_load_string($response->body());
            if ($xml === false) {
                throw new Exception('Invalid XML content');
            }

            return ['rss' => $this->xmlToArray($xml)];
        } catch (Exception $exception) {
            Log::warning('Failed to parse XML response: '.$exception->getMessage());

            return ['error' => 'Failed to parse XML response'];
        }
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $array = (array) $xml;

        foreach ($array as $key => $value) {
            if ($value instanceof SimpleXMLElement) {
                $array[$key] = $this->xmlToArray($value);
            }
        }

        return $array;
    }
}
