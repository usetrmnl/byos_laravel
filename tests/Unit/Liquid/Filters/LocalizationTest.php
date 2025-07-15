<?php

use App\Liquid\Filters\Localization;

test('l_date formats date with default format', function () {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date);

    // Default format is 'Y-m-d', which should output something like '2025-01-11'
    // The exact output might vary depending on the locale, but it should contain the year, month, and day
    expect($result)->toContain('2025');
    expect($result)->toContain('01');
    expect($result)->toContain('11');
});

test('l_date formats date with custom format', function () {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date, '%y %b');

    // Format '%y %b' should output something like '25 Jan'
    // The month name might vary depending on the locale
    expect($result)->toContain('25');
    // We can't check for 'Jan' specifically as it might be localized
});

test('l_date handles DateTime objects', function () {
    $filter = new Localization();
    $date = new DateTimeImmutable('2025-01-11');

    $result = $filter->l_date($date, 'Y-m-d');

    expect($result)->toContain('2025-01-11');
});

test('l_word translates common words', function () {
    $filter = new Localization();

    expect($filter->l_word('today', 'de'))->toBe('heute');
});

test('l_word returns original word if no translation exists', function () {
    $filter = new Localization();

    expect($filter->l_word('hello', 'es-ES'))->toBe('hello');
    expect($filter->l_word('world', 'ko'))->toBe('world');
});

test('l_word is case-insensitive', function () {
    $filter = new Localization();

    expect($filter->l_word('TODAY', 'de'))->toBe('heute');
});

test('l_word returns original word for unknown locales', function () {
    $filter = new Localization();

    expect($filter->l_word('today', 'unknown-locale'))->toBe('today');
});
