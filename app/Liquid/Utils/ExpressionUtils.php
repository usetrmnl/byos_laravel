<?php

namespace App\Liquid\Utils;

/**
 * Utility class for parsing and evaluating expressions in Liquid filters
 */
class ExpressionUtils
{
    /**
     * Check if an array is associative
     */
    public static function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Parse a condition expression into a structured format
     */
    public static function parseCondition(string $expression): array
    {
        $expression = mb_trim($expression);

        // Handle logical operators (and, or)
        if (str_contains($expression, ' and ')) {
            $parts = explode(' and ', $expression, 2);

            return [
                'type' => 'and',
                'left' => self::parseCondition(mb_trim($parts[0])),
                'right' => self::parseCondition(mb_trim($parts[1])),
            ];
        }

        if (str_contains($expression, ' or ')) {
            $parts = explode(' or ', $expression, 2);

            return [
                'type' => 'or',
                'left' => self::parseCondition(mb_trim($parts[0])),
                'right' => self::parseCondition(mb_trim($parts[1])),
            ];
        }

        // Handle comparison operators
        $operators = ['>=', '<=', '!=', '==', '>', '<', '='];

        foreach ($operators as $operator) {
            if (str_contains($expression, $operator)) {
                $parts = explode($operator, $expression, 2);

                return [
                    'type' => 'comparison',
                    'left' => mb_trim($parts[0]),
                    'operator' => $operator === '=' ? '==' : $operator,
                    'right' => mb_trim($parts[1]),
                ];
            }
        }

        // If no operator found, treat as a simple expression
        return [
            'type' => 'simple',
            'expression' => $expression,
        ];
    }

    /**
     * Evaluate a condition against an object
     */
    public static function evaluateCondition(array $condition, string $variable, mixed $object): bool
    {
        switch ($condition['type']) {
            case 'and':
                return self::evaluateCondition($condition['left'], $variable, $object) &&
                       self::evaluateCondition($condition['right'], $variable, $object);

            case 'or':
                if (self::evaluateCondition($condition['left'], $variable, $object)) {
                    return true;
                }
                return self::evaluateCondition($condition['right'], $variable, $object);

            case 'comparison':
                $leftValue = self::resolveValue($condition['left'], $variable, $object);
                $rightValue = self::resolveValue($condition['right'], $variable, $object);

                return match ($condition['operator']) {
                    '==' => $leftValue === $rightValue,
                    '!=' => $leftValue !== $rightValue,
                    '>' => $leftValue > $rightValue,
                    '<' => $leftValue < $rightValue,
                    '>=' => $leftValue >= $rightValue,
                    '<=' => $leftValue <= $rightValue,
                    default => false,
                };

            case 'simple':
                $value = self::resolveValue($condition['expression'], $variable, $object);

                return (bool) $value;

            default:
                return false;
        }
    }

    /**
     * Resolve a value from an expression, variable, or literal
     */
    public static function resolveValue(string $expression, string $variable, mixed $object): mixed
    {
        $expression = mb_trim($expression);

        // If it's the variable name, return the object
        if ($expression === $variable) {
            return $object;
        }

        // If it's a property access (e.g., "n.age"), resolve it
        if (str_starts_with($expression, $variable.'.')) {
            $property = mb_substr($expression, mb_strlen($variable) + 1);
            if (is_array($object) && array_key_exists($property, $object)) {
                return $object[$property];
            }
            if (is_object($object) && property_exists($object, $property)) {
                return $object->$property;
            }

            return null;
        }

        // Try to parse as a number
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        // Try to parse as boolean
        if (in_array(mb_strtolower($expression), ['true', 'false'])) {
            return mb_strtolower($expression) === 'true';
        }

        // Try to parse as null
        if (mb_strtolower($expression) === 'null') {
            return null;
        }

        // Return as string (remove quotes if present)
        if ((str_starts_with($expression, '"') && str_ends_with($expression, '"')) ||
            (str_starts_with($expression, "'") && str_ends_with($expression, "'"))) {
            return mb_substr($expression, 1, -1);
        }

        return $expression;
    }

    /**
     * Convert strftime format string to PHP date format string
     *
     * @param  string  $strftimeFormat  The strftime format string
     * @return string The PHP date format string
     */
    public static function strftimeToPhpFormat(string $strftimeFormat): string
    {
        $conversions = [
            '%A' => 'l',     // Full weekday name
            '%a' => 'D',     // Abbreviated weekday name
            '%B' => 'F',     // Full month name
            '%b' => 'M',     // Abbreviated month name
            '%Y' => 'Y',     // Full year (4 digits)
            '%y' => 'y',     // Year without century (2 digits)
            '%m' => 'm',     // Month as decimal number (01-12)
            '%d' => 'd',     // Day of month as decimal number (01-31)
            '%H' => 'H',     // Hour in 24-hour format (00-23)
            '%I' => 'h',     // Hour in 12-hour format (01-12)
            '%M' => 'i',     // Minute as decimal number (00-59)
            '%S' => 's',     // Second as decimal number (00-59)
            '%p' => 'A',     // AM/PM
            '%P' => 'a',     // am/pm
            '%j' => 'z',     // Day of year as decimal number (001-366)
            '%w' => 'w',     // Weekday as decimal number (0-6, Sunday is 0)
            '%U' => 'W',     // Week number of year (00-53, Sunday is first day)
            '%W' => 'W',     // Week number of year (00-53, Monday is first day)
            '%c' => 'D M j H:i:s Y', // Date and time representation
            '%x' => 'm/d/Y', // Date representation
            '%X' => 'H:i:s', // Time representation
        ];

        return str_replace(array_keys($conversions), array_values($conversions), $strftimeFormat);
    }
}
