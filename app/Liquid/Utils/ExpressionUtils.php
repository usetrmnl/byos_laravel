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
        if (empty($array)) {
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
                return self::evaluateCondition($condition['left'], $variable, $object) ||
                       self::evaluateCondition($condition['right'], $variable, $object);

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
}
