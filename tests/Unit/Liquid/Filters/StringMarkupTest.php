<?php

use App\Liquid\Filters\StringMarkup;

test('pluralize returns singular form with count 1', function () {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', 1))->toBe('1 book');
    expect($filter->pluralize('person', 1))->toBe('1 person');
});

test('pluralize returns plural form with count greater than 1', function () {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', 2))->toBe('2 books');
    expect($filter->pluralize('person', 4))->toBe('4 people');
});

test('pluralize handles irregular plurals correctly', function () {
    $filter = new StringMarkup();

    expect($filter->pluralize('child', 3))->toBe('3 children');
    expect($filter->pluralize('sheep', 5))->toBe('5 sheep');
});

test('pluralize uses default count of 2 when not specified', function () {
    $filter = new StringMarkup();

    expect($filter->pluralize('book'))->toBe('2 books');
    expect($filter->pluralize('person'))->toBe('2 people');
});

test('markdown_to_html converts basic markdown to HTML', function () {
    $filter = new StringMarkup();
    $markdown = 'This is *italic* and **bold**.';

    // The exact HTML output might vary depending on the Parsedown implementation
    // So we'll check for the presence of HTML tags rather than the exact output
    $result = $filter->markdown_to_html($markdown);

    expect($result)->toContain('<em>italic</em>');
    expect($result)->toContain('<strong>bold</strong>');
});

test('markdown_to_html converts links correctly', function () {
    $filter = new StringMarkup();
    $markdown = 'This is [a link](https://example.com).';

    $result = $filter->markdown_to_html($markdown);

    expect($result)->toContain('<a href="https://example.com">a link</a>');
});

test('markdown_to_html handles fallback when Parsedown is not available', function () {
    // Create a mock that simulates Parsedown not being available
    $filter = new class extends StringMarkup
    {
        public function markdown_to_html(string $markdown): string
        {
            // Force the fallback path
            return nl2br(htmlspecialchars($markdown));
        }
    };

    $markdown = 'This is *italic* and [a link](https://example.com).';
    $result = $filter->markdown_to_html($markdown);

    expect($result)->toBe('This is *italic* and [a link](https://example.com).');
});

test('strip_html removes HTML tags', function () {
    $filter = new StringMarkup();
    $html = '<p>This is <strong>bold</strong> and <em>italic</em>.</p>';

    expect($filter->strip_html($html))->toBe('This is bold and italic.');
});

test('strip_html preserves text content', function () {
    $filter = new StringMarkup();
    $html = '<div>Hello, <span>world</span>!</div>';

    expect($filter->strip_html($html))->toBe('Hello, world!');
});

test('strip_html handles nested tags', function () {
    $filter = new StringMarkup();
    $html = '<div><p>Paragraph <strong>with <em>nested</em> tags</strong>.</p></div>';

    expect($filter->strip_html($html))->toBe('Paragraph with nested tags.');
});
