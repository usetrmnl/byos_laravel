<?php

declare(strict_types=1);

use App\Models\Plugin;

/**
 * Tests for the Liquid where filter functionality
 *
 * The preprocessing in Plugin::applyLiquidReplacements() converts:
 * {% for item in collection | filter: "key", "value" %}
 * to:
 * {% assign _temp_xxx = collection | filter: "key", "value" %}{% for item in _temp_xxx %}
 */

test('where filter works when assigned to variable first', function () {
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
        ,
    ]);

    $result = $plugin->render('full');

    // Should output the high tide data
    $this->assertStringContainsString('"t":"2025-08-26 01:48"', $result);
    $this->assertStringContainsString('"v":"4.624"', $result);
    $this->assertStringContainsString('"type":"H"', $result);
    // Should not contain the low tide data
    $this->assertStringNotContainsString('"type":"L"', $result);
});

test('where filter works directly in for loop with preprocessing', function () {
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
        ,
    ]);

    $result = $plugin->render('full');

    // Should output the high tide data
    $this->assertStringContainsString('"t":"2025-08-26 01:48"', $result);
    $this->assertStringContainsString('"v":"4.624"', $result);
    $this->assertStringContainsString('"type":"H"', $result);
    // Should not contain the low tide data
    $this->assertStringNotContainsString('"type":"L"', $result);
});

test('where filter works directly in for loop with multiple matches', function () {
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
        ,
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
