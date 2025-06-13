<?php

declare(strict_types=1);

namespace App\Liquid\Tags;

use App\Liquid\FileSystems\InlineTemplatesFileSystem;
use Keepsuit\Liquid\Exceptions\SyntaxException;
use Keepsuit\Liquid\Nodes\BodyNode;
use Keepsuit\Liquid\Nodes\Raw;
use Keepsuit\Liquid\Nodes\VariableLookup;
use Keepsuit\Liquid\Parse\TagParseContext;
use Keepsuit\Liquid\Render\RenderContext;
use Keepsuit\Liquid\TagBlock;

/**
 * The {% template [name] %} tag block is used to define custom templates within the context of the current Liquid template.
 * These templates are registered with the InlineTemplatesFileSystem and can be rendered using the render tag.
 */
class TemplateTag extends TagBlock
{
    protected string $templateName;
    protected Raw $body;

    public static function tagName(): string
    {
        return 'template';
    }

    public static function hasRawBody(): bool
    {
        return true;
    }

    public function parse(TagParseContext $context): static
    {
        // Get the template name from the tag parameters
        $templateNameExpression = $context->params->expression();
        
        $this->templateName = match (true) {
            is_string($templateNameExpression) => trim($templateNameExpression),
            is_numeric($templateNameExpression) => (string) $templateNameExpression,
            $templateNameExpression instanceof VariableLookup => (string) $templateNameExpression,
            default => throw new SyntaxException("Template name must be a string, number, or variable"),
        };

        // Validate template name (letters, numbers, underscores, and slashes only)
        if (!preg_match('/^[a-zA-Z0-9_\/]+$/', $this->templateName)) {
            throw new SyntaxException("Invalid template name '{$this->templateName}' - template names must contain only letters, numbers, underscores, and slashes");
        }

        $context->params->assertEnd();

        assert($context->body instanceof BodyNode);

        $body = $context->body->children()[0] ?? null;
        $this->body = match (true) {
            $body instanceof Raw => $body,
            default => throw new SyntaxException('template tag must have a single raw body'),
        };

        // Register the template with the file system during parsing
        $fileSystem = $context->getParseContext()->environment->fileSystem;
        if ($fileSystem instanceof InlineTemplatesFileSystem) {
            // Store the raw content for later rendering
            $fileSystem->register($this->templateName, $this->body->value);
        }

        return $this;
    }

    public function render(RenderContext $context): string
    {
        // Get the file system from the environment
        $fileSystem = $context->environment->fileSystem;

        if (!$fileSystem instanceof InlineTemplatesFileSystem) {
            // If no inline file system is available, just return empty string
            // This allows the template to be used in contexts where inline templates aren't supported
            return '';
        }

        // Register the template with the file system
        $fileSystem->register($this->templateName, $this->body->render($context));

        // Return empty string as template tags don't output anything
        return '';
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function getBody(): Raw
    {
        return $this->body;
    }
} 