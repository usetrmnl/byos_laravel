<?php

namespace App\Liquid\Filters;

use Keepsuit\Liquid\Concerns\ContextAware;
use Keepsuit\Liquid\Filters\FiltersProvider;

/**
 * Uniqueness filters for Liquid templates
 */
class Uniqueness extends FiltersProvider
{
    use ContextAware;

    /**
     * Append a random string to ensure uniqueness within a template
     *
     * @param  string  $prefix  The prefix to append the random string to
     * @return string The prefix with a random string appended
     */
    public function append_random(string $prefix): string
    {
        return $prefix.$this->generateRandomString();
    }

    /**
     * Generate a random string
     *
     * @param  int  $length  The length of the random string
     * @return string A random string
     */
    private function generateRandomString(int $length = 4): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $randomString = '';

        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, mb_strlen($characters) - 1)];
        }

        return $randomString;
    }
}
