<?php

use App\Liquid\Filters\Data;

test('json filter converts arrays to JSON', function (): void {
    $filter = new Data();
    $array = ['foo' => 'bar', 'baz' => 'qux'];

    expect($filter->json($array))->toBe('{"foo":"bar","baz":"qux"}');
});

test('json filter converts objects to JSON', function (): void {
    $filter = new Data();
    $object = new stdClass();
    $object->foo = 'bar';
    $object->baz = 'qux';

    expect($filter->json($object))->toBe('{"foo":"bar","baz":"qux"}');
});

test('json filter handles nested structures', function (): void {
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

test('json filter handles scalar values', function (): void {
    $filter = new Data();

    expect($filter->json('string'))->toBe('"string"');
    expect($filter->json(123))->toBe('123');
    expect($filter->json(true))->toBe('true');
    expect($filter->json(null))->toBe('null');
});

test('json filter preserves unicode characters', function (): void {
    $filter = new Data();
    $data = ['message' => 'Hello, 世界'];

    expect($filter->json($data))->toBe('{"message":"Hello, 世界"}');
});

test('json filter does not escape slashes', function (): void {
    $filter = new Data();
    $data = ['url' => 'https://example.com/path'];

    expect($filter->json($data))->toBe('{"url":"https://example.com/path"}');
});

test('find_by filter finds object by key-value pair', function (): void {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('find_by filter returns null when no match found', function (): void {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'ronak');
    expect($result)->toBeNull();
});

test('find_by filter returns fallback when no match found', function (): void {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'name', 'ronak', 'Not Found');
    expect($result)->toBe('Not Found');
});

test('find_by filter finds by age', function (): void {
    $filter = new Data();
    $collection = [
        ['name' => 'Ryan', 'age' => 35],
        ['name' => 'Sara', 'age' => 29],
        ['name' => 'Jimbob', 'age' => 29],
    ];

    $result = $filter->find_by($collection, 'age', 29);
    expect($result)->toBe(['name' => 'Sara', 'age' => 29]);
});

test('find_by filter handles empty collection', function (): void {
    $filter = new Data();
    $collection = [];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBeNull();
});

test('find_by filter handles collection with non-array items', function (): void {
    $filter = new Data();
    $collection = [
        'not an array',
        ['name' => 'Ryan', 'age' => 35],
        null,
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('find_by filter handles items without the specified key', function (): void {
    $filter = new Data();
    $collection = [
        ['age' => 35],
        ['name' => 'Ryan', 'age' => 35],
        ['title' => 'Developer'],
    ];

    $result = $filter->find_by($collection, 'name', 'Ryan');
    expect($result)->toBe(['name' => 'Ryan', 'age' => 35]);
});

test('group_by filter groups collection by age', function (): void {
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

test('group_by filter groups collection by name', function (): void {
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

test('group_by filter handles empty collection', function (): void {
    $filter = new Data();
    $collection = [];

    $result = $filter->group_by($collection, 'age');
    expect($result)->toBe([]);
});

test('group_by filter handles collection with non-array items', function (): void {
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

test('group_by filter handles items without the specified key', function (): void {
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

test('group_by filter handles mixed data types as keys', function (): void {
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

test('sample filter returns a random element from array', function (): void {
    $filter = new Data();
    $array = ['1', '2', '3', '4', '5'];

    $result = $filter->sample($array);
    expect($result)->toBeIn($array);
});

test('sample filter returns a random element from string array', function (): void {
    $filter = new Data();
    $array = ['cat', 'dog'];

    $result = $filter->sample($array);
    expect($result)->toBeIn($array);
});

test('sample filter returns null for empty array', function (): void {
    $filter = new Data();
    $array = [];

    $result = $filter->sample($array);
    expect($result)->toBeNull();
});

test('sample filter returns the only element from single element array', function (): void {
    $filter = new Data();
    $array = ['single'];

    $result = $filter->sample($array);
    expect($result)->toBe('single');
});

test('sample filter works with mixed data types', function (): void {
    $filter = new Data();
    $array = [1, 'string', true, null, ['nested']];

    $result = $filter->sample($array);
    expect($result)->toBeIn($array);
});

test('parse_json filter parses JSON string to array', function (): void {
    $filter = new Data();
    $jsonString = '[{"a":1,"b":"c"},"d"]';

    $result = $filter->parse_json($jsonString);
    expect($result)->toBe([['a' => 1, 'b' => 'c'], 'd']);
});

test('parse_json filter parses simple JSON object', function (): void {
    $filter = new Data();
    $jsonString = '{"name":"John","age":30,"city":"New York"}';

    $result = $filter->parse_json($jsonString);
    expect($result)->toBe(['name' => 'John', 'age' => 30, 'city' => 'New York']);
});

test('parse_json filter parses JSON array', function (): void {
    $filter = new Data();
    $jsonString = '["apple","banana","cherry"]';

    $result = $filter->parse_json($jsonString);
    expect($result)->toBe(['apple', 'banana', 'cherry']);
});

test('parse_json filter parses nested JSON structure', function (): void {
    $filter = new Data();
    $jsonString = '{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}],"total":2}';

    $result = $filter->parse_json($jsonString);
    expect($result)->toBe([
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ],
        'total' => 2,
    ]);
});

test('parse_json filter handles primitive values', function (): void {
    $filter = new Data();

    expect($filter->parse_json('"hello"'))->toBe('hello');
    expect($filter->parse_json('123'))->toBe(123);
    expect($filter->parse_json('true'))->toBe(true);
    expect($filter->parse_json('false'))->toBe(false);
    expect($filter->parse_json('null'))->toBe(null);
});

test('map_to_i filter converts string numbers to integers', function (): void {
    $filter = new Data();
    $input = ['1', '2', '3', '4', '5'];

    expect($filter->map_to_i($input))->toBe([1, 2, 3, 4, 5]);
});

test('map_to_i filter handles mixed string numbers', function (): void {
    $filter = new Data();
    $input = ['5', '4', '3', '2', '1'];

    expect($filter->map_to_i($input))->toBe([5, 4, 3, 2, 1]);
});

test('map_to_i filter handles decimal strings', function (): void {
    $filter = new Data();
    $input = ['1.5', '2.7', '3.0'];

    expect($filter->map_to_i($input))->toBe([1, 2, 3]);
});

test('map_to_i filter handles empty array', function (): void {
    $filter = new Data();
    $input = [];

    expect($filter->map_to_i($input))->toBe([]);
});

test('where_exp filter returns string as array when input is string', function (): void {
    $filter = new Data();
    $input = 'just a string';

    expect($filter->where_exp($input, 'la', 'le'))->toBe(['just a string']);
});

test('where_exp filter filters numbers with comparison', function (): void {
    $filter = new Data();
    $input = [1, 2, 3, 4, 5];

    expect($filter->where_exp($input, 'n', 'n >= 3'))->toBe([3, 4, 5]);
});

test('where_exp filter filters numbers with greater than', function (): void {
    $filter = new Data();
    $input = [1, 2, 3, 4, 5];

    expect($filter->where_exp($input, 'n', 'n > 2'))->toBe([3, 4, 5]);
});

test('where_exp filter filters numbers with less than', function (): void {
    $filter = new Data();
    $input = [1, 2, 3, 4, 5];

    expect($filter->where_exp($input, 'n', 'n < 4'))->toBe([1, 2, 3]);
});

test('where_exp filter filters numbers with equality', function (): void {
    $filter = new Data();
    $input = [1, 2, 3, 4, 5];

    expect($filter->where_exp($input, 'n', 'n == 3'))->toBe([3]);
});

test('where_exp filter filters numbers with not equal', function (): void {
    $filter = new Data();
    $input = [1, 2, 3, 4, 5];

    expect($filter->where_exp($input, 'n', 'n != 3'))->toBe([1, 2, 4, 5]);
});

test('where_exp filter filters objects by property', function (): void {
    $filter = new Data();
    $input = [
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Bob', 'age' => 30],
        ['name' => 'Charlie', 'age' => 35],
    ];

    expect($filter->where_exp($input, 'person', 'person.age >= 30'))->toBe([
        ['name' => 'Bob', 'age' => 30],
        ['name' => 'Charlie', 'age' => 35],
    ]);
});

test('where_exp filter filters objects by string property', function (): void {
    $filter = new Data();
    $input = [
        ['name' => 'Alice', 'role' => 'admin'],
        ['name' => 'Bob', 'role' => 'user'],
        ['name' => 'Charlie', 'role' => 'admin'],
    ];

    expect($filter->where_exp($input, 'user', 'user.role == "admin"'))->toBe([
        ['name' => 'Alice', 'role' => 'admin'],
        ['name' => 'Charlie', 'role' => 'admin'],
    ]);
});

test('where_exp filter handles and operator', function (): void {
    $filter = new Data();
    $input = [
        ['name' => 'Alice', 'age' => 25, 'active' => true],
        ['name' => 'Bob', 'age' => 30, 'active' => false],
        ['name' => 'Charlie', 'age' => 35, 'active' => true],
    ];

    expect($filter->where_exp($input, 'person', 'person.age >= 30 and person.active == true'))->toBe([
        ['name' => 'Charlie', 'age' => 35, 'active' => true],
    ]);
});

test('where_exp filter handles or operator', function (): void {
    $filter = new Data();
    $input = [
        ['name' => 'Alice', 'age' => 25, 'role' => 'admin'],
        ['name' => 'Bob', 'age' => 30, 'role' => 'user'],
        ['name' => 'Charlie', 'age' => 35, 'role' => 'user'],
    ];

    expect($filter->where_exp($input, 'person', 'person.age < 30 or person.role == "admin"'))->toBe([
        ['name' => 'Alice', 'age' => 25, 'role' => 'admin'],
    ]);
});

test('where_exp filter handles simple boolean expressions', function (): void {
    $filter = new Data();
    $input = [
        ['name' => 'Alice', 'active' => true],
        ['name' => 'Bob', 'active' => false],
        ['name' => 'Charlie', 'active' => true],
    ];

    expect($filter->where_exp($input, 'person', 'person.active'))->toBe([
        ['name' => 'Alice', 'active' => true],
        ['name' => 'Charlie', 'active' => true],
    ]);
});

test('where_exp filter handles empty array', function (): void {
    $filter = new Data();
    $input = [];

    expect($filter->where_exp($input, 'n', 'n >= 3'))->toBe([]);
});

test('where_exp filter handles associative array', function (): void {
    $filter = new Data();
    $input = [
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ];

    expect($filter->where_exp($input, 'n', 'n >= 2'))->toBe([2, 3]);
});

test('where_exp filter handles non-array input', function (): void {
    $filter = new Data();
    $input = 123;

    expect($filter->where_exp($input, 'n', 'n >= 3'))->toBe([]);
});

test('where_exp filter handles null input', function (): void {
    $filter = new Data();
    $input = null;

    expect($filter->where_exp($input, 'n', 'n >= 3'))->toBe([]);
});
