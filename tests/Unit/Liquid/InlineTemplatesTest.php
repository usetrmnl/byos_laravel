<?php

declare(strict_types=1);

namespace Tests\Unit\Liquid;

use App\Liquid\FileSystems\InlineTemplatesFileSystem;
use App\Liquid\Filters\Data;
use App\Liquid\Filters\Date;
use App\Liquid\Filters\Localization;
use App\Liquid\Filters\Numbers;
use App\Liquid\Filters\StringMarkup;
use App\Liquid\Filters\Uniqueness;
use App\Liquid\Tags\TemplateTag;
use Keepsuit\Liquid\Environment;
use Keepsuit\Liquid\Exceptions\LiquidException;
use Keepsuit\Liquid\Extensions\StandardExtension;
use Keepsuit\Liquid\Tags\RenderTag;
use PHPUnit\Framework\TestCase;

class InlineTemplatesTest extends TestCase
{
    protected Environment $environment;

    protected InlineTemplatesFileSystem $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new InlineTemplatesFileSystem();
        $this->environment = new Environment(
            fileSystem: $this->fileSystem,
            extensions: [new StandardExtension()]
        );
        $this->environment->tagRegistry->register(TemplateTag::class);
        $this->environment->tagRegistry->register(RenderTag::class);
        $this->environment->filterRegistry->register(Data::class);
        $this->environment->filterRegistry->register(Date::class);
        $this->environment->filterRegistry->register(Localization::class);
        $this->environment->filterRegistry->register(Numbers::class);
        $this->environment->filterRegistry->register(StringMarkup::class);
        $this->environment->filterRegistry->register(Uniqueness::class);
    }

    public function test_template_tag_registers_template(): void
    {
        $template = $this->environment->parseString(<<<'LIQUID'
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
LIQUID
        );

        $context = $this->environment->newRenderContext(
            data: [
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'randomNumber' => 1,
                'size_mod' => '--large',
            ]
        );

        $result = $template->render($context);

        // Template tag should not output anything
        $this->assertEquals('', $result);

        // Template should be registered in the file system
        $this->assertTrue($this->fileSystem->hasTemplate('session'));

        $registeredTemplate = $this->fileSystem->readTemplateFile('session');
        $this->assertStringContainsString('{{ facts[randomNumber] }}', $registeredTemplate);
        $this->assertStringContainsString('{{ size_mod }}', $registeredTemplate);
    }

    public function test_template_tag_with_render_tag(): void
    {
        $template = $this->environment->parseString(<<<'LIQUID'
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

{% render "session",
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID
        );

        $context = $this->environment->newRenderContext(
            data: [
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'randomNumber' => 1,
                'trmnl' => ['plugin_settings' => ['instance_name' => 'Test']],
            ]
        );

        $result = $template->render($context);

        // Should render the template content
        $this->assertStringContainsString('Fact 2', $result); // facts[1]
        $this->assertStringContainsString('class="layout"', $result);
        $this->assertStringContainsString('class="value text--center"', $result);
    }

    public function test_apply_liquid_replacements_converts_with_syntax(): void
    {
        // This test simulates the applyLiquidReplacements method from the Plugin model
        $originalLiquid = <<<'LIQUID'
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

{% render "session" with
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID;

        // Apply the same replacement logic as in Plugin::applyLiquidReplacements
        $convertedLiquid = preg_replace(
            '/{%\s*render\s+([^}]+?)\s+with\s+/i',
            '{% render $1, ',
            $originalLiquid
        );

        // Verify the conversion worked
        $this->assertStringContainsString('{% render "session",', $convertedLiquid);
        $this->assertStringNotContainsString('{% render "session" with', $convertedLiquid);

        // Verify the rest of the content is preserved
        $this->assertStringContainsString('trmnl: trmnl,', $convertedLiquid);
        $this->assertStringContainsString('facts: facts,', $convertedLiquid);
    }

    public function test_template_tag_with_render_with_tag(): void
    {
        $originalLiquid = <<<'LIQUID'
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

{% render "session" with
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID;

        // Apply the same replacement logic as in applyLiquidReplacements
        $convertedLiquid = preg_replace(
            '/{%\s*render\s+([^}]+?)\s+with\s+/i',
            '{% render $1, ',
            $originalLiquid
        );

        $template = $this->environment->parseString($convertedLiquid);

        $context = $this->environment->newRenderContext(
            data: [
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'randomNumber' => 1,
                'trmnl' => ['plugin_settings' => ['instance_name' => 'Test']],
            ]
        );

        $result = $template->render($context);

        // Should render the template content
        $this->assertStringContainsString('Fact 2', $result); // facts[1]
        $this->assertStringContainsString('class="layout"', $result);
        $this->assertStringContainsString('class="value text--center"', $result);
    }

    public function test_template_tag_with_multiple_templates(): void
    {
        $template = $this->environment->parseString(<<<'LIQUID'
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
        );

        $context = $this->environment->newRenderContext(
            data: [
                'size' => 'full',
                'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'randomNumber' => 1,
                'trmnl' => ['plugin_settings' => ['instance_name' => 'Test Plugin']],
            ]
        );

        $result = $template->render($context);

        // Should render both templates
        $this->assertStringContainsString('Fact 2', $result);
        $this->assertStringContainsString('Test Plugin', $result);
        $this->assertStringContainsString('Please try to enjoy each fact equally', $result);
        $this->assertStringContainsString('class="view view--full"', $result);
    }

    public function test_template_tag_invalid_name(): void
    {
        $this->expectException(LiquidException::class);

        $template = $this->environment->parseString(<<<'LIQUID'
{% template invalid-name %}
<div>Content</div>
{% endtemplate %}
LIQUID
        );

        $context = $this->environment->newRenderContext();

        $template->render($context);
    }

    public function test_template_tag_without_file_system(): void
    {
        $template = $this->environment->parseString(<<<'LIQUID'
{% template session %}
<div>Content</div>
{% endtemplate %}
LIQUID
        );

        $context = $this->environment->newRenderContext();

        $result = $template->render($context);

        // Should not throw an error and should return empty string
        $this->assertEquals('', $result);
    }

    public function test_quotes_template_with_modulo_filter(): void
    {
        $template = $this->environment->parseString(<<<'LIQUID'
{% assign quotes_array = quotes[trmnl.plugin_settings.custom_fields_values.language] %}
{% assign random_index = 'now' | date: '%s' | modulo: quotes_array.size %}
{{ quotes_array[random_index] }}
LIQUID
        );

        $context = $this->environment->newRenderContext(
            data: [
                'quotes' => [
                    'english' => ['Demo Quote'],
                    'german' => ['Demo Zitat'],
                ],
                'trmnl' => [
                    'plugin_settings' => [
                        'custom_fields_values' => [
                            'language' => 'english',
                        ],
                    ],
                ],
            ]
        );

        $result = $template->render($context);
        // Should render a quote from the english array
        $this->assertStringContainsString('Demo Quote', $result);
        $this->assertStringNotContainsString('Demo Zitat', $result);
    }
}
