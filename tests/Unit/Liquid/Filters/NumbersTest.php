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

    expect($filter->number_to_currency(1234.57, '£', '.', ','))->toBe('1.234,57 £');
    expect($filter->number_to_currency(1234.57, '€', ',', '.'))->toBe('€1,234.57');
});
