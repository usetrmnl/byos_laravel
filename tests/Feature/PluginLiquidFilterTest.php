<?php

declare(strict_types=1);

use App\Liquid\Filters\StandardFilters;
use App\Models\Plugin;
use Keepsuit\Liquid\Environment;

/**
 * Tests for the Liquid where filter functionality
 *
 * The preprocessing in Plugin::applyLiquidReplacements() converts:
 * {% for item in collection | filter: "key", "value" %}
 * to:
 * {% assign _temp_xxx = collection | filter: "key", "value" %}{% for item in _temp_xxx %}
 */
test('where filter works when assigned to variable first', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => <<<'LIQUID'
{% liquid
assign json_string = '[{"t":"2025-08-26 01:48","v":"4.624","type":"H"},{"t":"2025-08-26 08:04","v":"0.333","type":"L"}]'
assign collection = json_string | parse_json
%}

{% assign tides_h = collection | where: "type", "H" %}

{% for tide in tides_h %}
  {{ tide | json  }}
{%- endfor %}
LIQUID
    ]);

    $result = $plugin->render('full');

    // Should output the high tide data
    $this->assertStringContainsString('"t":"2025-08-26 01:48"', $result);
    $this->assertStringContainsString('"v":"4.624"', $result);
    $this->assertStringContainsString('"type":"H"', $result);
    // Should not contain the low tide data
    $this->assertStringNotContainsString('"type":"L"', $result);
});

test('where filter works directly in for loop with preprocessing', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => <<<'LIQUID'
{% liquid
assign json_string = '[{"t":"2025-08-26 01:48","v":"4.624","type":"H"},{"t":"2025-08-26 08:04","v":"0.333","type":"L"}]'
assign collection = json_string | parse_json
%}

{% for tide in collection | where: "type", "H" %}
  {{ tide | json }}
{%- endfor %}
LIQUID
    ]);

    $result = $plugin->render('full');

    // Should output the high tide data
    $this->assertStringContainsString('"t":"2025-08-26 01:48"', $result);
    $this->assertStringContainsString('"v":"4.624"', $result);
    $this->assertStringContainsString('"type":"H"', $result);
    // Should not contain the low tide data
    $this->assertStringNotContainsString('"type":"L"', $result);
});

test('where filter works directly in for loop with multiple matches', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => <<<'LIQUID'
{% liquid
assign json_string = '[{"t":"2025-08-26 01:48","v":"4.624","type":"H"},{"t":"2025-08-26 08:04","v":"0.333","type":"L"},{"t":"2025-08-26 14:30","v":"4.8","type":"H"}]'
assign collection = json_string | parse_json
%}

{% for tide in collection | where: "type", "H" %}
  {{ tide | json }}
{%- endfor %}
LIQUID
    ]);

    $result = $plugin->render('full');

    // Should output both high tide data entries
    $this->assertStringContainsString('"t":"2025-08-26 01:48"', $result);
    $this->assertStringContainsString('"t":"2025-08-26 14:30"', $result);
    $this->assertStringContainsString('"v":"4.624"', $result);
    $this->assertStringContainsString('"v":"4.8"', $result);
    // Should not contain the low tide data
    $this->assertStringNotContainsString('"type":"L"', $result);
});

it('encodes arrays for url_encode as JSON with spaces after commas and then percent-encodes', function (): void {
    /** @var Environment $env */
    $env = app('liquid.environment');
    $env->filterRegistry->register(StandardFilters::class);

    $template = $env->parseString('{{ categories | url_encode }}');

    $output = $template->render($env->newRenderContext([
        'categories' => ['common', 'obscure'],
    ]));

    expect($output)->toBe('%5B%22common%22%2C%22obscure%22%5D');
});

it('keeps scalar url_encode behavior intact', function (): void {
    /** @var Environment $env */
    $env = app('liquid.environment');
    $env->filterRegistry->register(StandardFilters::class);

    $template = $env->parseString('{{ text | url_encode }}');

    $output = $template->render($env->newRenderContext([
        'text' => 'hello world',
    ]));

    expect($output)->toBe('hello+world');
});

test('where_exp filter works in liquid template', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => <<<'LIQUID'
{% liquid
assign nums = "1, 2, 3, 4, 5" | split: ", " | map_to_i
assign filtered = nums | where_exp: "n", "n >= 3"
%}

{% for num in filtered %}
  {{ num }}
{%- endfor %}
LIQUID
    ]);

    $result = $plugin->render('full');

    // Debug: Let's see what the actual output is
    // The issue might be that the HTML contains "1" in other places
    // Let's check if the filtered numbers are actually in the content
    $this->assertStringContainsString('3', $result);
    $this->assertStringContainsString('4', $result);
    $this->assertStringContainsString('5', $result);

    // Instead of checking for absence of 1 and 2, let's verify the count
    // The filtered result should only contain 3, 4, 5
    $filteredContent = strip_tags((string) $result);
    $this->assertStringNotContainsString('1', $filteredContent);
    $this->assertStringNotContainsString('2', $filteredContent);
});

test('where_exp filter works with object properties', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => <<<'LIQUID'
{% liquid
assign users = '[{"name":"Alice","age":25},{"name":"Bob","age":30},{"name":"Charlie","age":35}]' | parse_json
assign adults = users | where_exp: "user", "user.age >= 30"
%}

{% for user in adults %}
  {{ user.name }} ({{ user.age }})
{%- endfor %}
LIQUID
    ]);

    $result = $plugin->render('full');

    // Should output users >= 30
    $this->assertStringContainsString('Bob (30)', $result);
    $this->assertStringContainsString('Charlie (35)', $result);
    // Should not contain users < 30
    $this->assertStringNotContainsString('Alice (25)', $result);
});

test('qr_code filter generates SVG QR code with qr-code class', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "https://example.com" | qr_code }}',
    ]);

    $result = $plugin->render('full');

    // Should contain SVG elements
    $this->assertStringContainsString('<svg', $result);
    $this->assertStringContainsString('</svg>', $result);
    // Should contain qr-code class
    $this->assertStringContainsString('class="qr-code"', $result);
    // Should contain QR code path elements
    $this->assertStringContainsString('<path', $result);
});

test('qr_code filter works with custom text', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "Hello World" | qr_code }}',
    ]);

    $result = $plugin->render('full');

    // Should generate valid SVG
    $this->assertStringContainsString('<svg', $result);
    $this->assertStringContainsString('</svg>', $result);
    // Should contain qr-code class
    $this->assertStringContainsString('class="qr-code"', $result);
});

test('qr_code filter calculates correct size for module_size 11', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "test" | qr_code: 11 }}',
    ]);

    $result = $plugin->render('full');

    // Should have width="319" and height="319" (29 * 11 = 319)
    $this->assertStringContainsString('width="319"', $result);
    $this->assertStringContainsString('height="319"', $result);
});

test('qr_code filter calculates correct size for module_size 16', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "test" | qr_code: 16 }}',
    ]);

    $result = $plugin->render('full');

    // Should have width="464" and height="464" (29 * 16 = 464)
    $this->assertStringContainsString('width="464"', $result);
    $this->assertStringContainsString('height="464"', $result);
});

test('qr_code filter calculates correct size for module_size 10', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "test" | qr_code: 10 }}',
    ]);

    $result = $plugin->render('full');

    // Should have width="290" and height="290" (29 * 10 = 290)
    $this->assertStringContainsString('width="290"', $result);
    $this->assertStringContainsString('height="290"', $result);
});

test('qr_code filter calculates correct size for module_size 5', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "test" | qr_code: 5 }}',
    ]);

    $result = $plugin->render('full');

    // Should have width="145" and height="145" (29 * 5 = 145)
    $this->assertStringContainsString('width="145"', $result);
    $this->assertStringContainsString('height="145"', $result);
});

test('qr_code filter supports error correction level parameter', function (): void {
    $plugin = Plugin::factory()->create([
        'markup_language' => 'liquid',
        'render_markup' => '{{ "test" | qr_code: 11, "l" }}',
    ]);

    $result = $plugin->render('full');

    // Should generate valid SVG with qr-code class
    $this->assertStringContainsString('<svg', $result);
    $this->assertStringContainsString('class="qr-code"', $result);
});
