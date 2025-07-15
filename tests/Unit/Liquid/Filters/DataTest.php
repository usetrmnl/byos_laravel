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
