<?php

use App\Liquid\Utils\ExpressionUtils;

test('isAssociativeArray returns true for associative array', function (): void {
    $array = ['a' => 1, 'b' => 2, 'c' => 3];

    expect(ExpressionUtils::isAssociativeArray($array))->toBeTrue();
});

test('isAssociativeArray returns false for indexed array', function (): void {
    $array = [1, 2, 3, 4, 5];

    expect(ExpressionUtils::isAssociativeArray($array))->toBeFalse();
});

test('isAssociativeArray returns false for empty array', function (): void {
    $array = [];

    expect(ExpressionUtils::isAssociativeArray($array))->toBeFalse();
});

test('parseCondition handles simple comparison', function (): void {
    $result = ExpressionUtils::parseCondition('n >= 3');

    expect($result)->toBe([
        'type' => 'comparison',
        'left' => 'n',
        'operator' => '>=',
        'right' => '3',
    ]);
});

test('parseCondition handles equality comparison', function (): void {
    $result = ExpressionUtils::parseCondition('user.role == "admin"');

    expect($result)->toBe([
        'type' => 'comparison',
        'left' => 'user.role',
        'operator' => '==',
        'right' => '"admin"',
    ]);
});

test('parseCondition handles and operator', function (): void {
    $result = ExpressionUtils::parseCondition('user.age >= 30 and user.active == true');

    expect($result)->toBe([
        'type' => 'and',
        'left' => [
            'type' => 'comparison',
            'left' => 'user.age',
            'operator' => '>=',
            'right' => '30',
        ],
        'right' => [
            'type' => 'comparison',
            'left' => 'user.active',
            'operator' => '==',
            'right' => 'true',
        ],
    ]);
});

test('parseCondition handles or operator', function (): void {
    $result = ExpressionUtils::parseCondition('user.age < 30 or user.role == "admin"');

    expect($result)->toBe([
        'type' => 'or',
        'left' => [
            'type' => 'comparison',
            'left' => 'user.age',
            'operator' => '<',
            'right' => '30',
        ],
        'right' => [
            'type' => 'comparison',
            'left' => 'user.role',
            'operator' => '==',
            'right' => '"admin"',
        ],
    ]);
});

test('parseCondition handles simple expression', function (): void {
    $result = ExpressionUtils::parseCondition('user.active');

    expect($result)->toBe([
        'type' => 'simple',
        'expression' => 'user.active',
    ]);
});

test('evaluateCondition handles comparison with numbers', function (): void {
    $condition = ExpressionUtils::parseCondition('n >= 3');

    expect(ExpressionUtils::evaluateCondition($condition, 'n', 5))->toBeTrue();
    expect(ExpressionUtils::evaluateCondition($condition, 'n', 2))->toBeFalse();
    expect(ExpressionUtils::evaluateCondition($condition, 'n', 3))->toBeTrue();
});

test('evaluateCondition handles comparison with strings', function (): void {
    $condition = ExpressionUtils::parseCondition('user.role == "admin"');
    $user = ['role' => 'admin'];

    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeTrue();

    $user = ['role' => 'user'];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeFalse();
});

test('evaluateCondition handles and operator', function (): void {
    $condition = ExpressionUtils::parseCondition('user.age >= 30 and user.active == true');
    $user = ['age' => 35, 'active' => true];

    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeTrue();

    $user = ['age' => 25, 'active' => true];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeFalse();

    $user = ['age' => 35, 'active' => false];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeFalse();
});

test('evaluateCondition handles or operator', function (): void {
    $condition = ExpressionUtils::parseCondition('user.age < 30 or user.role == "admin"');
    $user = ['age' => 25, 'role' => 'user'];

    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeTrue();

    $user = ['age' => 35, 'role' => 'admin'];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeTrue();

    $user = ['age' => 35, 'role' => 'user'];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeFalse();
});

test('evaluateCondition handles simple boolean expression', function (): void {
    $condition = ExpressionUtils::parseCondition('user.active');
    $user = ['active' => true];

    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeTrue();

    $user = ['active' => false];
    expect(ExpressionUtils::evaluateCondition($condition, 'user', $user))->toBeFalse();
});

test('resolveValue returns object when expression matches variable', function (): void {
    $object = ['name' => 'Alice', 'age' => 25];

    expect(ExpressionUtils::resolveValue('user', 'user', $object))->toBe($object);
});

test('resolveValue resolves property access for arrays', function (): void {
    $object = ['name' => 'Alice', 'age' => 25];

    expect(ExpressionUtils::resolveValue('user.name', 'user', $object))->toBe('Alice');
    expect(ExpressionUtils::resolveValue('user.age', 'user', $object))->toBe(25);
});

test('resolveValue resolves property access for objects', function (): void {
    $object = new stdClass();
    $object->name = 'Alice';
    $object->age = 25;

    expect(ExpressionUtils::resolveValue('user.name', 'user', $object))->toBe('Alice');
    expect(ExpressionUtils::resolveValue('user.age', 'user', $object))->toBe(25);
});

test('resolveValue returns null for non-existent properties', function (): void {
    $object = ['name' => 'Alice'];

    expect(ExpressionUtils::resolveValue('user.age', 'user', $object))->toBeNull();
});

test('resolveValue parses numeric values', function (): void {
    expect(ExpressionUtils::resolveValue('123', 'user', []))->toBe(123);
    expect(ExpressionUtils::resolveValue('45.67', 'user', []))->toBe(45.67);
});

test('resolveValue parses boolean values', function (): void {
    expect(ExpressionUtils::resolveValue('true', 'user', []))->toBeTrue();
    expect(ExpressionUtils::resolveValue('false', 'user', []))->toBeFalse();
    expect(ExpressionUtils::resolveValue('TRUE', 'user', []))->toBeTrue();
    expect(ExpressionUtils::resolveValue('FALSE', 'user', []))->toBeFalse();
});

test('resolveValue parses null value', function (): void {
    expect(ExpressionUtils::resolveValue('null', 'user', []))->toBeNull();
    expect(ExpressionUtils::resolveValue('NULL', 'user', []))->toBeNull();
});

test('resolveValue removes quotes from strings', function (): void {
    expect(ExpressionUtils::resolveValue('"hello"', 'user', []))->toBe('hello');
    expect(ExpressionUtils::resolveValue("'world'", 'user', []))->toBe('world');
});

test('resolveValue returns expression as-is for unquoted strings', function (): void {
    expect(ExpressionUtils::resolveValue('hello', 'user', []))->toBe('hello');
    expect(ExpressionUtils::resolveValue('world', 'user', []))->toBe('world');
});
