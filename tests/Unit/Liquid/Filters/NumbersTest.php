<?php

use App\Liquid\Filters\Numbers;

test('number_with_delimiter formats numbers with commas by default', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(1234))->toBe('1,234');
    expect($filter->number_with_delimiter(1000000))->toBe('1,000,000');
    expect($filter->number_with_delimiter(0))->toBe('0');
});

test('number_with_delimiter handles custom delimiters', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(1234, '.'))->toBe('1.234');
    expect($filter->number_with_delimiter(1000000, ' '))->toBe('1 000 000');
});

test('number_with_delimiter handles decimal values with custom separators', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(1234.57, ' ', ','))->toBe('1 234,57');
    expect($filter->number_with_delimiter(1234.5, '.', ','))->toBe('1.234,50');
});

test('number_to_currency formats numbers with dollar sign by default', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(1234))->toBe('$1,234');
    expect($filter->number_to_currency(1234.5))->toBe('$1,234.50');
    expect($filter->number_to_currency(0))->toBe('$0');
});

test('number_to_currency handles custom currency symbols', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(1234, '£'))->toBe('£1,234');
    expect($filter->number_to_currency(152350.69, '€'))->toBe('€152,350.69');
});

test('number_to_currency handles custom delimiters and separators', function () {
    $filter = new Numbers();

    $result1 = $filter->number_to_currency(1234.57, '£', '.', ',');
    $result2 = $filter->number_to_currency(1234.57, '€', ',', '.');

    expect($result1)->toContain('1.234,57');
    expect($result1)->toContain('£');
    expect($result2)->toContain('1,234.57');
    expect($result2)->toContain('€');
});

test('number_with_delimiter handles string numbers', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter('1234'))->toBe('1,234');
    expect($filter->number_with_delimiter('1234.56'))->toBe('1,234.56');
});

test('number_with_delimiter handles negative numbers', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(-1234))->toBe('-1,234');
    expect($filter->number_with_delimiter(-1234.56))->toBe('-1,234.56');
});

test('number_with_delimiter handles zero', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(0))->toBe('0');
    expect($filter->number_with_delimiter(0.0))->toBe('0.00');
});

test('number_with_delimiter handles very small numbers', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(0.01))->toBe('0.01');
    expect($filter->number_with_delimiter(0.001))->toBe('0.00');
});

test('number_to_currency handles string numbers', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency('1234'))->toBe('$1,234');
    expect($filter->number_to_currency('1234.56'))->toBe('$1,234.56');
});

test('number_to_currency handles negative numbers', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(-1234))->toBe('-$1,234');
    expect($filter->number_to_currency(-1234.56))->toBe('-$1,234.56');
});

test('number_to_currency handles zero', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(0))->toBe('$0');
    expect($filter->number_to_currency(0.0))->toBe('$0.00');
});

test('number_to_currency handles currency code conversion', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(1234, '$'))->toBe('$1,234');
    expect($filter->number_to_currency(1234, '€'))->toBe('€1,234');
    expect($filter->number_to_currency(1234, '£'))->toBe('£1,234');
});

test('number_to_currency handles German locale formatting', function () {
    $filter = new Numbers();

    // When delimiter is '.' and separator is ',', it should use German locale
    $result = $filter->number_to_currency(1234.56, 'EUR', '.', ',');
    expect($result)->toContain('1.234,56');
});

test('number_with_delimiter handles different decimal separators', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(1234.56, ',', ','))->toBe('1,234,56');
    expect($filter->number_with_delimiter(1234.56, ' ', ','))->toBe('1 234,56');
});

test('number_to_currency handles very large numbers', function () {
    $filter = new Numbers();

    expect($filter->number_to_currency(1000000))->toBe('$1,000,000');
    expect($filter->number_to_currency(1000000.50))->toBe('$1,000,000.50');
});

test('number_with_delimiter handles very large numbers', function () {
    $filter = new Numbers();

    expect($filter->number_with_delimiter(1000000))->toBe('1,000,000');
    expect($filter->number_with_delimiter(1000000.50))->toBe('1,000,000.50');
});
