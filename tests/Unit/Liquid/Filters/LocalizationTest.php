<?php

use App\Liquid\Filters\Localization;

test('l_date formats date with default format', function (): void {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date);

    // Default format is 'Y-m-d', which should output something like '2025-01-11'
    // The exact output might vary depending on the locale, but it should contain the year, month, and day
    expect($result)->toContain('2025');
    expect($result)->toContain('01');
    expect($result)->toContain('11');
});

test('l_date formats date with custom format', function (): void {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date, '%y %b');

    // Format '%y %b' should output something like '25 Jan'
    // The month name might vary depending on the locale
    expect($result)->toContain('25');
    // We can't check for 'Jan' specifically as it might be localized
});

test('l_date handles DateTime objects', function (): void {
    $filter = new Localization();
    $date = new DateTimeImmutable('2025-01-11');

    $result = $filter->l_date($date, 'Y-m-d');

    expect($result)->toContain('2025-01-11');
});

test('l_word translates common words', function (): void {
    $filter = new Localization();

    expect($filter->l_word('today', 'de'))->toBe('heute');
});

test('l_word returns original word if no translation exists', function (): void {
    $filter = new Localization();

    expect($filter->l_word('hello', 'es-ES'))->toBe('hello');
    expect($filter->l_word('world', 'ko'))->toBe('world');
});

test('l_word is case-insensitive', function (): void {
    $filter = new Localization();

    expect($filter->l_word('TODAY', 'de'))->toBe('heute');
});

test('l_word returns original word for unknown locales', function (): void {
    $filter = new Localization();

    expect($filter->l_word('today', 'unknown-locale'))->toBe('today');
});

test('l_date handles locale parameter', function (): void {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date, 'Y-m-d', 'de');

    // The result should still contain the date components
    expect($result)->toContain('2025');
    expect($result)->toContain('01');
    expect($result)->toContain('11');
});

test('l_date handles null locale parameter', function (): void {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date, 'Y-m-d', null);

    // Should work the same as default
    expect($result)->toContain('2025');
    expect($result)->toContain('01');
    expect($result)->toContain('11');
});

test('l_date handles different date formats with locale', function (): void {
    $filter = new Localization();
    $date = '2025-01-11';

    $result = $filter->l_date($date, '%B %d, %Y', 'en');

    // Should contain the month name and date
    expect($result)->toContain('2025');
    expect($result)->toContain('11');
});

test('l_date handles DateTimeInterface objects with locale', function (): void {
    $filter = new Localization();
    $date = new DateTimeImmutable('2025-01-11');

    $result = $filter->l_date($date, 'Y-m-d', 'fr');

    // Should still format correctly
    expect($result)->toContain('2025');
    expect($result)->toContain('01');
    expect($result)->toContain('11');
});

test('l_date handles invalid date gracefully', function (): void {
    $filter = new Localization();
    $invalidDate = 'invalid-date';

    // This should throw an exception or return a default value
    // The exact behavior depends on Carbon's implementation
    expect(fn (): string => $filter->l_date($invalidDate))->toThrow(Exception::class);
});

test('l_word handles empty string', function (): void {
    $filter = new Localization();

    expect($filter->l_word('', 'de'))->toBe('');
});

test('l_word handles special characters', function (): void {
    $filter = new Localization();

    // Test with a word that has special characters
    expect($filter->l_word('café', 'de'))->toBe('café');
});

test('l_word handles numeric strings', function (): void {
    $filter = new Localization();

    expect($filter->l_word('123', 'de'))->toBe('123');
});
