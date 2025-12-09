<?php

namespace App\Services\Plugin\Parsers;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class JsonOrTextResponseParser implements ResponseParser
{
    public function parse(Response $response): array
    {
        try {
            $json = $response->json();
            if ($json !== null) {
                return $json;
            }

            return ['data' => $response->body()];
        } catch (Exception $e) {
            Log::warning('Failed to parse JSON response: '.$e->getMessage());

            return ['error' => 'Failed to parse JSON response'];
        }
    }
}
