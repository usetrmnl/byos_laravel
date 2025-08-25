<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginInlineTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_with_inline_templates(): void
    {
        $plugin = Plugin::factory()->create([
            'name' => 'Test Plugin',
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{% assign min = 1 %}
{% assign max = facts | size %}
{% assign diff = max | minus: min %}
{% assign randomNumber = "now" | date: "u" | modulo: diff | plus: min %}

{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% template title_bar %}
<div class="title_bar">
  <img class="image" src="https://res.jwq.lol/img/lumon.svg">
  <span class="title">{{ trmnl.plugin_settings.instance_name }}</span>
  <span class="instance">{{ instance }}</span>
</div>
{% endtemplate %}

<div class="view view--{{ size }}">
{% render "session",
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}

{% render "title_bar",
  trmnl: trmnl,
  instance: "Please try to enjoy each fact equally."
%}
</div>
LIQUID
            ,
            'data_payload' => [
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            ],
        ]);

        $result = $plugin->render('full');

        // Should render both templates
        // Check for any of the facts (since random number generation is non-deterministic)
        $this->assertTrue(
            str_contains($result, 'Fact 1') ||
            str_contains($result, 'Fact 2') ||
            str_contains($result, 'Fact 3')
        );
        $this->assertStringContainsString('Test Plugin', $result);
        $this->assertStringContainsString('Please try to enjoy each fact equally', $result);
        $this->assertStringContainsString('class="view view--full"', $result);
    }

    public function test_plugin_with_inline_templates_using_with_syntax(): void
    {
        $plugin = Plugin::factory()->create([
            'name' => 'Test Plugin',
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{% assign min = 1 %}
{% assign max = facts | size %}
{% assign diff = max | minus: min %}
{% assign randomNumber = "now" | date: "u" | modulo: diff | plus: min %}

{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% template title_bar %}
<div class="title_bar">
  <img class="image" src="https://res.jwq.lol/img/lumon.svg">
  <span class="title">{{ trmnl.plugin_settings.instance_name }}</span>
  <span class="instance">{{ instance }}</span>
</div>
{% endtemplate %}

<div class="view view--{{ size }}">
{% render "session" with
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}

{% render "title_bar" with
  trmnl: trmnl,
  instance: "Please try to enjoy each fact equally."
%}
</div>
LIQUID
            ,
            'data_payload' => [
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            ],
        ]);

        $result = $plugin->render('full');

        // Should render both templates
        // Check for any of the facts (since random number generation is non-deterministic)
        $this->assertTrue(
            str_contains($result, 'Fact 1') ||
            str_contains($result, 'Fact 2') ||
            str_contains($result, 'Fact 3')
        );
        $this->assertStringContainsString('Test Plugin', $result);
        $this->assertStringContainsString('Please try to enjoy each fact equally', $result);
        $this->assertStringContainsString('class="view view--full"', $result);
    }

    public function test_plugin_with_simple_inline_template(): void
    {
        $plugin = Plugin::factory()->create([
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{% template simple %}
<div class="simple">
  <h1>{{ title }}</h1>
  <p>{{ content }}</p>
</div>
{% endtemplate %}

{% render "simple",
  title: "Hello World",
  content: "This is a test"
%}
LIQUID
            ,
        ]);

        $result = $plugin->render('full');

        $this->assertStringContainsString('Hello World', $result);
        $this->assertStringContainsString('This is a test', $result);
        $this->assertStringContainsString('class="simple"', $result);
    }

    public function test_plugin_with_find_by_filter(): void
    {
        $plugin = Plugin::factory()->create([
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{% template user_info %}
<div class="user">
  <h2>{{ user.name }}</h2>
  <p>Age: {{ user.age }}</p>
</div>
{% endtemplate %}

{% assign found_user = collection | find_by: 'name', 'Ryan' %}
{% render "user_info", user: found_user %}
LIQUID
            ,
            'data_payload' => [
                'collection' => [
                    ['name' => 'Ryan', 'age' => 35],
                    ['name' => 'Sara', 'age' => 29],
                    ['name' => 'Jimbob', 'age' => 29],
                ],
            ],
        ]);

        $result = $plugin->render('full');

        // Should render the user info for Ryan
        $this->assertStringContainsString('Ryan', $result);
        $this->assertStringContainsString('Age: 35', $result);
        $this->assertStringContainsString('class="user"', $result);
    }

    public function test_plugin_with_find_by_filter_and_fallback(): void
    {
        $plugin = Plugin::factory()->create([
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{{ collection | find_by: 'name', 'ronak', 'Not Found' }}
LIQUID
            ,
            'data_payload' => [
                'collection' => [
                    ['name' => 'Ryan', 'age' => 35],
                    ['name' => 'Sara', 'age' => 29],
                    ['name' => 'Jimbob', 'age' => 29],
                ],
            ],
        ]);

        $result = $plugin->render('full');

        // Should return the fallback value
        $this->assertStringContainsString('Not Found', $result);
    }

    public function test_plugin_with_group_by_filter(): void
    {
        $plugin = Plugin::factory()->create([
            'markup_language' => 'liquid',
            'render_markup' => <<<'LIQUID'
{{ collection | group_by: 'age' | json }}
LIQUID
            ,
            'data_payload' => [
                'collection' => [
                    ['name' => 'Ryan', 'age' => 35],
                    ['name' => 'Sara', 'age' => 29],
                    ['name' => 'Jimbob', 'age' => 29],
                ],
            ],
        ]);

        $result = $plugin->render('full');

        // Should output JSON representation of grouped data
        $this->assertStringContainsString('"35":[{"name":"Ryan","age":35}]', $result);
        $this->assertStringContainsString('"29":[{"name":"Sara","age":29},{"name":"Jimbob","age":29}]', $result);
    }
}
