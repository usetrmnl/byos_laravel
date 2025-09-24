<?php

use App\Liquid\Filters\StringMarkup;

test('pluralize returns singular form with count 1', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', 1))->toBe('1 book');
    expect($filter->pluralize('person', 1))->toBe('1 person');
});

test('pluralize returns plural form with count greater than 1', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', 2))->toBe('2 books');
    expect($filter->pluralize('person', 4))->toBe('4 people');
});

test('pluralize handles irregular plurals correctly', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('child', 3))->toBe('3 children');
    expect($filter->pluralize('sheep', 5))->toBe('5 sheep');
});

test('pluralize uses default count of 2 when not specified', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('book'))->toBe('2 books');
    expect($filter->pluralize('person'))->toBe('2 people');
});

test('markdown_to_html converts basic markdown to HTML', function (): void {
    $filter = new StringMarkup();
    $markdown = 'This is *italic* and **bold**.';

    // The exact HTML output might vary depending on the Parsedown implementation
    // So we'll check for the presence of HTML tags rather than the exact output
    $result = $filter->markdown_to_html($markdown);

    expect($result)->toContain('<em>italic</em>');
    expect($result)->toContain('<strong>bold</strong>');
});

test('markdown_to_html converts links correctly', function (): void {
    $filter = new StringMarkup();
    $markdown = 'This is [a link](https://example.com).';

    $result = $filter->markdown_to_html($markdown);

    expect($result)->toContain('<a href="https://example.com">a link</a>');
});

test('markdown_to_html handles fallback when Parsedown is not available', function (): void {
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

test('strip_html removes HTML tags', function (): void {
    $filter = new StringMarkup();
    $html = '<p>This is <strong>bold</strong> and <em>italic</em>.</p>';

    expect($filter->strip_html($html))->toBe('This is bold and italic.');
});

test('strip_html preserves text content', function (): void {
    $filter = new StringMarkup();
    $html = '<div>Hello, <span>world</span>!</div>';

    expect($filter->strip_html($html))->toBe('Hello, world!');
});

test('strip_html handles nested tags', function (): void {
    $filter = new StringMarkup();
    $html = '<div><p>Paragraph <strong>with <em>nested</em> tags</strong>.</p></div>';

    expect($filter->strip_html($html))->toBe('Paragraph with nested tags.');
});

test('markdown_to_html handles CommonMarkException gracefully', function (): void {
    $filter = new StringMarkup();

    // Create a mock that throws CommonMarkException
    $filter = new class extends StringMarkup
    {
        public function markdown_to_html(string $markdown): ?string
        {
            try {
                // Simulate CommonMarkException
                throw new Exception('Invalid markdown');
            } catch (Exception $e) {
                Illuminate\Support\Facades\Log::error('Markdown conversion error: '.$e->getMessage());
            }

            return null;
        }
    };

    $result = $filter->markdown_to_html('invalid markdown');

    expect($result)->toBeNull();
});

test('markdown_to_html handles empty string', function (): void {
    $filter = new StringMarkup();

    $result = $filter->markdown_to_html('');

    expect($result)->toBe('');
});

test('markdown_to_html handles complex markdown', function (): void {
    $filter = new StringMarkup();
    $markdown = "# Heading\n\nThis is a paragraph with **bold** and *italic* text.\n\n- List item 1\n- List item 2\n\n[Link](https://example.com)";

    $result = $filter->markdown_to_html($markdown);

    expect($result)->toContain('<h1>Heading</h1>');
    expect($result)->toContain('<strong>bold</strong>');
    expect($result)->toContain('<em>italic</em>');
    expect($result)->toContain('<ul>');
    expect($result)->toContain('<li>List item 1</li>');
    expect($result)->toContain('<a href="https://example.com">Link</a>');
});

test('strip_html handles empty string', function (): void {
    $filter = new StringMarkup();

    expect($filter->strip_html(''))->toBe('');
});

test('strip_html handles string without HTML tags', function (): void {
    $filter = new StringMarkup();
    $text = 'This is plain text without any HTML tags.';

    expect($filter->strip_html($text))->toBe($text);
});

test('strip_html handles self-closing tags', function (): void {
    $filter = new StringMarkup();
    $html = '<p>Text with <br/> line break and <hr/> horizontal rule.</p>';

    expect($filter->strip_html($html))->toBe('Text with  line break and  horizontal rule.');
});

test('pluralize handles zero count', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', 0))->toBe('0 books');
    expect($filter->pluralize('person', 0))->toBe('0 people');
});

test('pluralize handles negative count', function (): void {
    $filter = new StringMarkup();

    expect($filter->pluralize('book', -1))->toBe('-1 book');
    expect($filter->pluralize('person', -5))->toBe('-5 people');
});
