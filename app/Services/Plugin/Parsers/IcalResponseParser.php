<?php

namespace App\Services\Plugin\Parsers;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use om\IcalParser;

class IcalResponseParser implements ResponseParser
{
    public function __construct(
        private readonly IcalParser $parser = new IcalParser(),
    ) {}

    public function parse(Response $response): ?array
    {
        $contentType = $response->header('Content-Type');
        $body = $response->body();

        if (! $this->isIcalResponse($contentType, $body)) {
            return null;
        }

        try {
            $this->parser->parseString($body);

            $events = $this->parser->getEvents()->sorted()->getArrayCopy();
            $windowStart = now()->subDays(7);
            $windowEnd = now()->addDays(30);

            $filteredEvents = array_values(array_filter($events, function (array $event) use ($windowStart, $windowEnd): bool {
                $startDate = $this->asCarbon($event['DTSTART'] ?? null);

                if (! $startDate instanceof Carbon) {
                    return false;
                }

                return $startDate->between($windowStart, $windowEnd, true);
            }));

            $normalizedEvents = array_map($this->normalizeIcalEvent(...), $filteredEvents);

            return ['ical' => $normalizedEvents];
        } catch (Exception $exception) {
            Log::warning('Failed to parse iCal response: '.$exception->getMessage());

            return ['error' => 'Failed to parse iCal response'];
        }
    }

    private function isIcalResponse(?string $contentType, string $body): bool
    {
        $normalizedContentType = $contentType ? mb_strtolower($contentType) : '';

        if ($normalizedContentType && str_contains($normalizedContentType, 'text/calendar')) {
            return true;
        }

        return str_contains($body, 'BEGIN:VCALENDAR');
    }

    private function asCarbon(DateTimeInterface|string|null $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Exception $exception) {
                Log::warning('Failed to parse date value: '.$exception->getMessage());

                return null;
            }
        }

        return null;
    }

    private function normalizeIcalEvent(array $event): array
    {
        $normalized = [];

        foreach ($event as $key => $value) {
            $normalized[$key] = $this->normalizeIcalValue($value);
        }

        return $normalized;
    }

    private function normalizeIcalValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toAtomString();
        }

        if (is_array($value)) {
            return array_map($this->normalizeIcalValue(...), $value);
        }

        return $value;
    }
}
