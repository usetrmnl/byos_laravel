<?php

use App\Liquid\Filters\Data;

test('json filter converts arrays to JSON', function () {
    $filter = new Data();
    $array = ['foo' => 'bar', 'baz' => 'qux'];

    expect($filter->json($array))->toBe('{"foo":"bar","baz":"qux"}');
});

test('json filter converts objects to JSON', function () {
    $filter = new Data();
    $object = new stdClass();
    $object->foo = 'bar';
    $object->baz = 'qux';

    expect($filter->json($object))->toBe('{"foo":"bar","baz":"qux"}');
});

test('json filter handles nested structures', function () {
    $filter = new Data();
    $nested = [
        'foo' => 'bar',
        'nested' => [
            'baz' => 'qux',
            'items' => [1, 2, 3],
        ],
    ];

    expect($filter->json($nested))->toBe('{"foo":"bar","nested":{"baz":"qux","items":[1,2,3]}}');
});

test('json filter handles scalar values', function () {
    $filter = new Data();

    expect($filter->json('string'))->toBe('"string"');
    expect($filter->json(123))->toBe('123');
    expect($filter->json(true))->toBe('true');
    expect($filter->json(null))->toBe('null');
});

test('json filter preserves unicode characters', function () {
    $filter = new Data();
    $data = ['message' => 'Hello, 世界'];

    expect($filter->json($data))->toBe('{"message":"Hello, 世界"}');
});

test('json filter does not escape slashes', function () {
    $filter = new Data();
    $data = ['url' => 'https://example.com/path'];

    expect($filter->json($data))->toBe('{"url":"https://example.com/path"}');
});

test('find_by filter finds object by key-value pair', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('find_by filter returns null when no match found', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'ronak');
    expect($result)->toBeNull();
});

test('find_by filter returns fallback when no match found', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'ronak', 'Not Found');
    expect($result)->toBe('Not Found');
});

test('find_by filter finds by age', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'age', 29);
    expect($result)->toBe(['name' => 'Sara', 'age' => 29]);
});

test('find_by filter handles empty collection', function () {
    $filter = new Data();
    $collection = [];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBeNull();
});

test('find_by filter handles collection with non-array items', function () {
    $filter = new Data();
    $collection = [
        'not an array',
        ['name' => 'Ryan', 'age' => 35],
        null,
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('find_by filter handles items without the specified key', function () {
    $filter = new Data();
    $collection = [
        ['age' => 35],
        ['name' => 'Ryan', 'age' => 35],
        ['title' => 'Developer'],
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('group_by filter groups collection by age', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->group_by($collection, 'age');

    expect($result)->toBe([
        35 => [['name' => 'Ryan', 'age' => 35]],
        29 => [
            ['name' => 'Sara', 'age' => 29],
            ['name' => 'Jimbob', 'age' => 29],
        ],
    ]);
});

test('group_by filter groups collection by name', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Ryan', 'age' => 30],
    ];

    $result = $filter->group_by($collection, 'name');

    expect($result)->toBe([
        'Ryan' => [
            ['name' => 'Ryan', 'age' => 35],
            ['name' => 'Ryan', 'age' => 30],
        ],
        'Sara' => [['name' => 'Sara', 'age' => 29]],
    ]);
});

test('group_by filter handles empty collection', function () {
    $filter = new Data();
    $collection = [];

    $result = $filter->group_by($collection, 'age');
    expect($result)->toBe([]);
});

test('group_by filter handles collection with non-array items', function () {
    $filter = new Data();
    $collection = [
        'not an array',
        ['name' => 'Ryan', 'age' => 35],
        null,
        ['name' => 'Sara', 'age' => 29],
    ];

    $result = $filter->group_by($collection, 'age');

    expect($result)->toBe([
        35 => [['name' => 'Ryan', 'age' => 35]],
        29 => [['name' => 'Sara', 'age' => 29]],
    ]);
});

test('group_by filter handles items without the specified key', function () {
    $filter = new Data();
    $collection = [
        ['age' => 35],
        ['name' => 'Ryan', 'age' => 35],
        ['title' => 'Developer'],
        ['name' => 'Sara', 'age' => 29],
    ];

    $result = $filter->group_by($collection, 'age');

    expect($result)->toBe([
        35 => [
            ['age' => 35],
            ['name' => 'Ryan', 'age' => 35],
        ],
        29 => [['name' => 'Sara', 'age' => 29]],
    ]);
});

test('group_by filter handles mixed data types as keys', function () {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'active' => true],
        ['name' => 'Sara', 'active' => false],
        ['name' => 'Jimbob', 'active' => true],
        ['name' => 'Alice', 'active' => null],
    ];

    $result = $filter->group_by($collection, 'active');

    expect($result)->toBe([
        1 => [ // PHP converts true to 1
            ['name' => 'Ryan', 'active' => true],
            ['name' => 'Jimbob', 'active' => true],
        ],
        0 => [['name' => 'Sara', 'active' => false]], // PHP converts false to 0
        '' => [['name' => 'Alice', 'active' => null]], // PHP converts null keys to empty string
    ]);
});
