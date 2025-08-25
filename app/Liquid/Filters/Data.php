<?php

namespace App\Liquid\Filters;

use Keepsuit\Liquid\Filters\FiltersProvider;

/**
 * Data filters for Liquid templates
 */
class Data extends FiltersProvider
{
    /**
     * Convert a variable to JSON
     *
     * @param  mixed  $value  The variable to convert
     * @return string JSON representation of the variable
     */
    public function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Find an object in a collection by a specific key-value pair
     *
     * @param  array  $collection  The collection to search in
     * @param  string  $key  The key to search for
     * @param  mixed  $value  The value to match
     * @param  mixed  $fallback  Optional fallback value if no match is found
     * @return mixed The matching object or fallback value
     */
    public function find_by(array $collection, string $key, mixed $value, mixed $fallback = null): mixed
    {
        foreach ($collection as $item) {
            if (is_array($item) && isset($item[$key]) && $item[$key] === $value) {
                return $item;
            }
        }

        return $fallback;
    }

    /**
     * Group a collection by a specific key
     *
     * @param  array  $collection  The collection to group
     * @param  string  $key  The key to group by
     * @return array The grouped collection
     */
    public function group_by(array $collection, string $key): array
    {
        $grouped = [];

        foreach ($collection as $item) {
            if (is_array($item) && array_key_exists($key, $item)) {
                $groupKey = $item[$key];
                if (! isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [];
                }
                $grouped[$groupKey][] = $item;
            }
        }

        return $grouped;
    }
}
