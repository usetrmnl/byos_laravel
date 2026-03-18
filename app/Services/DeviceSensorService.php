<?php

namespace App\Services;

use App\Enums\DeviceSensorKind;
use App\Enums\DeviceSensorSource;
use App\Models\Device;
use App\Models\DeviceSensor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeviceSensorService
{
    public function ingestFromHeader(Device $device, string $headerValue): void
    {
        $records = $this->parseHeader($headerValue);

        if ($records === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($records as $record) {
            if (! $this->isValidRecord($record)) {
                Log::warning('Invalid sensor record skipped', ['record' => $record, 'device_id' => $device->id]);

                continue;
            }

            $createdAt = $this->parseCreatedAt($record['created_at'] ?? null) ?? $now;

            $rows[] = [
                'device_id' => $device->id,
                'make' => (string) $record['make'],
                'model' => (string) $record['model'],
                'kind' => (string) $record['kind'],
                'value' => (float) $record['value'],
                'unit' => (string) $record['unit'],
                'source' => DeviceSensorSource::DEVICE->value,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if ($rows !== []) {
            DeviceSensor::query()->insert($rows);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHeader(string $headerValue): array
    {
        $headerValue = mb_trim($headerValue);

        if ($headerValue === '') {
            return [];
        }

        $records = [];

        foreach (explode(',', $headerValue) as $recordPart) {
            $recordPart = mb_trim($recordPart);

            if ($recordPart === '') {
                continue;
            }

            $attributes = [];

            foreach (explode(';', $recordPart) as $attributePart) {
                $attributePart = mb_trim($attributePart);

                if ($attributePart === '') {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $attributePart, 2), 2, null);

                if ($key === null || $value === null) {
                    continue;
                }

                $key = mb_strtolower(mb_trim($key));
                $value = mb_trim($value);

                $attributes[$key] = $value;
            }

            if ($attributes !== []) {
                $records[] = $attributes;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function isValidRecord(array $record): bool
    {
        foreach (['make', 'model', 'kind', 'value', 'unit'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $record) || $record[$requiredKey] === null || $record[$requiredKey] === '') {
                return false;
            }
        }

        $kind = (string) $record['kind'];

        return DeviceSensorKind::tryFrom($kind) !== null;
    }

    private function parseCreatedAt(?string $createdAt): ?Carbon
    {
        if ($createdAt === null || $createdAt === '') {
            return null;
        }

        if (is_numeric($createdAt)) {
            try {
                return Carbon::createFromTimestamp((int) $createdAt);
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($createdAt);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function latestPerKind(Device $device, int $limitPerKind = 1): array
    {
        $kinds = array_map(static fn (DeviceSensorKind $kind): string => $kind->value, DeviceSensorKind::cases());

        $result = [];

        foreach ($kinds as $kind) {
            $query = DeviceSensor::query()
                ->where('device_id', $device->id)
                ->where('kind', $kind)
                ->orderByDesc('created_at')
                ->limit($limitPerKind);

            $latest = $query->first();

            if ($latest !== null) {
                $result[$kind] = $this->toContextArray($latest);
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentHistory(Device $device, int $limit = 50): array
    {
        return DeviceSensor::query()
            ->where('device_id', $device->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (DeviceSensor $sensor): array => $this->toContextArray($sensor))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toContextArray(DeviceSensor $sensor): array
    {
        return [
            'kind' => $sensor->kind->value,
            'value' => $sensor->value,
            'unit' => $sensor->unit,
            'make' => $sensor->make,
            'model' => $sensor->model,
            'source' => $sensor->source,
            'ts' => $sensor->created_at?->timestamp,
        ];
    }
}
