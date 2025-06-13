<?php

declare(strict_types=1);

namespace App\Liquid\FileSystems;

use Keepsuit\Liquid\Contracts\LiquidFileSystem;

/**
 * A file system that allows registering inline templates defined with the template tag
 */
class InlineTemplatesFileSystem implements LiquidFileSystem
{
    /**
     * @var array<string, string>
     */
    protected array $templates = [];

    /**
     * Register a template with the given name and content
     */
    public function register(string $name, string $content): void
    {
        $this->templates[$name] = $content;
    }

    /**
     * Check if a template exists
     */
    public function hasTemplate(string $templateName): bool
    {
        return isset($this->templates[$templateName]);
    }

    /**
     * Get all registered template names
     *
     * @return array<string>
     */
    public function getTemplateNames(): array
    {
        return array_keys($this->templates);
    }

    /**
     * Clear all registered templates
     */
    public function clear(): void
    {
        $this->templates = [];
    }

    public function readTemplateFile(string $templateName): string
    {
        if (!isset($this->templates[$templateName])) {
            throw new \InvalidArgumentException("Template '{$templateName}' not found in inline templates");
        }

        return $this->templates[$templateName];
    }
} 