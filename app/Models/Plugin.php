<?php

namespace App\Models;

use App\Liquid\FileSystems\InlineTemplatesFileSystem;
use App\Liquid\Filters\Data;
use App\Liquid\Filters\Localization;
use App\Liquid\Filters\Numbers;
use App\Liquid\Filters\StringMarkup;
use App\Liquid\Filters\Uniqueness;
use App\Liquid\Tags\TemplateTag;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Keepsuit\Liquid\Exceptions\LiquidException;
use Keepsuit\Liquid\Extensions\StandardExtension;

class Plugin extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'data_payload' => 'json',
        'data_payload_updated_at' => 'datetime',
        'is_native' => 'boolean',
        'markup_language' => 'string',
        'configuration' => 'json',
        'configuration_template' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hasMissingRequiredConfigurationFields(): bool
    {
        if (! isset($this->configuration_template['custom_fields']) || empty($this->configuration_template['custom_fields'])) {
            return false;
        }

        foreach ($this->configuration_template['custom_fields'] as $field) {
            // Skip fields as they are informational only
            if ($field['field_type'] === 'author_bio') {
                continue;
            }

            if ($field['field_type'] === 'copyable') {
                continue;
            }

            if ($field['field_type'] === 'copyable_webhook_url') {
                continue;
            }

            $fieldKey = $field['keyname'] ?? $field['key'] ?? $field['name'];

            // Check if field is required (not marked as optional)
            $isRequired = ! isset($field['optional']) || $field['optional'] !== true;

            if ($isRequired) {
                $currentValue = $this->configuration[$fieldKey] ?? null;

                // If the field has a default value and no current value is set, it's not missing
                if (($currentValue === null || $currentValue === '' || (is_array($currentValue) && empty($currentValue))) && ! isset($field['default'])) {
                    return true; // Found a required field that is not set and has no default
                }
            }
        }

        return false; // All required fields are set
    }

    public function isDataStale(): bool
    {
        if ($this->data_strategy === 'webhook') {
            // Treat as stale if any webhook event has occurred in the past hour
            return $this->data_payload_updated_at && $this->data_payload_updated_at->gt(now()->subHour());
        }
        if (! $this->data_payload_updated_at || ! $this->data_stale_minutes) {
            return true;
        }

        return $this->data_payload_updated_at->addMinutes($this->data_stale_minutes)->isPast();
    }

    public function updateDataPayload(): void
    {
        if ($this->data_strategy === 'polling' && $this->polling_url) {

            $headers = ['User-Agent' => 'usetrmnl/byos_laravel', 'Accept' => 'application/json'];

            if ($this->polling_header) {
                // Resolve Liquid variables in the polling header
                $resolvedHeader = $this->resolveLiquidVariables($this->polling_header);
                $headerLines = explode("\n", trim($resolvedHeader));
                foreach ($headerLines as $line) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }

            // Split URLs by newline and filter out empty lines
            $urls = array_filter(
                array_map('trim', explode("\n", $this->polling_url)),
                fn ($url) => ! empty($url)
            );

            // If only one URL, use the original logic without nesting
            if (count($urls) === 1) {
                $url = reset($urls);
                $httpRequest = Http::withHeaders($headers);

                if ($this->polling_verb === 'post' && $this->polling_body) {
                    // Resolve Liquid variables in the polling body
                    $resolvedBody = $this->resolveLiquidVariables($this->polling_body);
                    $httpRequest = $httpRequest->withBody($resolvedBody);
                }

                // Resolve Liquid variables in the polling URL
                $resolvedUrl = $this->resolveLiquidVariables($url);

                try {
                    // Make the request based on the verb
                    if ($this->polling_verb === 'post') {
                        $response = $httpRequest->post($resolvedUrl)->json();
                    } else {
                        $response = $httpRequest->get($resolvedUrl)->json();
                    }

                    $this->update([
                        'data_payload' => $response,
                        'data_payload_updated_at' => now(),
                    ]);
                } catch (Exception $e) {
                    Log::warning("Failed to fetch data from URL {$resolvedUrl}: ".$e->getMessage());
                    $this->update([
                        'data_payload' => ['error' => 'Failed to fetch data'],
                        'data_payload_updated_at' => now(),
                    ]);
                }

                return;
            }

            // Multiple URLs - use nested response logic
            $combinedResponse = [];

            foreach ($urls as $index => $url) {
                $httpRequest = Http::withHeaders($headers);

                if ($this->polling_verb === 'post' && $this->polling_body) {
                    // Resolve Liquid variables in the polling body
                    $resolvedBody = $this->resolveLiquidVariables($this->polling_body);
                    $httpRequest = $httpRequest->withBody($resolvedBody);
                }

                // Resolve Liquid variables in the polling URL
                $resolvedUrl = $this->resolveLiquidVariables($url);

                try {
                    // Make the request based on the verb
                    if ($this->polling_verb === 'post') {
                        $response = $httpRequest->post($resolvedUrl)->json();
                    } else {
                        $response = $httpRequest->get($resolvedUrl)->json();
                    }

                    // Check if response is an array at root level
                    if (is_array($response) && array_keys($response) === range(0, count($response) - 1)) {
                        // Response is a sequential array, nest under .data
                        $combinedResponse["IDX_{$index}"] = ['data' => $response];
                    } else {
                        // Response is an object or associative array, keep as is
                        $combinedResponse["IDX_{$index}"] = $response;
                    }
                } catch (Exception $e) {
                    // Log error and continue with other URLs
                    Log::warning("Failed to fetch data from URL {$resolvedUrl}: ".$e->getMessage());
                    $combinedResponse["IDX_{$index}"] = ['error' => 'Failed to fetch data'];
                }
            }

            $this->update([
                'data_payload' => $combinedResponse,
                'data_payload_updated_at' => now(),
            ]);
        }
    }

    /**
     * Apply Liquid template replacements (converts 'with' syntax to comma syntax)
     */
    private function applyLiquidReplacements(string $template): string
    {
        $replacements = [
            'date: "%N"' => 'date: "u"',
            '%-m/%-d/%Y' => 'm/d/Y',
        ];

        // Apply basic replacements
        $template = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Convert {% render "template" with %} syntax to {% render "template", %} syntax
        $template = preg_replace(
            '/{%\s*render\s+([^}]+?)\s+with\s+/i',
            '{% render $1, ',
            $template
        );

        return $template;
    }

    /**
     * Resolve Liquid variables in a template string using the Liquid template engine
     *
     * @param  string  $template  The template string containing Liquid variables
     * @return string The resolved template with variables replaced with their values
     *
     * @throws LiquidException
     */
    public function resolveLiquidVariables(string $template): string
    {
        // Get configuration variables - make them available at root level
        $variables = $this->configuration ?? [];

        // Use the Liquid template engine to resolve variables
        $environment = App::make('liquid.environment');
        $liquidTemplate = $environment->parseString($template);
        $context = $environment->newRenderContext(data: $variables);

        return $liquidTemplate->render($context);
    }

    /**
     * Render the plugin's markup
     *
     * @throws LiquidException
     */
    public function render(string $size = 'full', bool $standalone = true): string
    {
        if ($this->render_markup) {
            $renderedContent = '';

            if ($this->markup_language === 'liquid') {
                // Create a custom environment with inline templates support
                $inlineFileSystem = new InlineTemplatesFileSystem();
                $environment = new \Keepsuit\Liquid\Environment(
                    fileSystem: $inlineFileSystem,
                    extensions: [new StandardExtension()]
                );

                // Register all custom filters
                $environment->filterRegistry->register(Numbers::class);
                $environment->filterRegistry->register(Data::class);
                $environment->filterRegistry->register(StringMarkup::class);
                $environment->filterRegistry->register(Uniqueness::class);
                $environment->filterRegistry->register(Localization::class);

                // Register the template tag for inline templates
                $environment->tagRegistry->register(TemplateTag::class);

                // Apply Liquid replacements (including 'with' syntax conversion)
                $processedMarkup = $this->applyLiquidReplacements($this->render_markup);

                $template = $environment->parseString($processedMarkup);
                $context = $environment->newRenderContext(
                    data: [
                        'size' => $size,
                        'data' => $this->data_payload,
                        'config' => $this->configuration ?? [],
                        ...(is_array($this->data_payload) ? $this->data_payload : []),
                        'trmnl' => [
                            'user' => [
                                'utc_offset' => '0',
                                'name' => $this->user->name ?? 'Unknown User',
                                'locale' => 'en',
                                'time_zone_iana' => config('app.timezone'),
                            ],
                            'plugin_settings' => [
                                'instance_name' => $this->name,
                                'strategy' => $this->data_strategy,
                                'dark_mode' => 'no',
                                'no_screen_padding' => 'no',
                                'polling_headers' => $this->polling_header,
                                'polling_url' => $this->polling_url,
                                'custom_fields_values' => [
                                    ...(is_array($this->configuration) ? $this->configuration : []),
                                ],
                            ],
                        ],
                    ]
                );
                $renderedContent = $template->render($context);
            } else {
                $renderedContent = Blade::render($this->render_markup, [
                    'size' => $size,
                    'data' => $this->data_payload,
                    'config' => $this->configuration ?? [],
                ]);
            }

            if ($standalone) {
                return view('trmnl-layouts.single', [
                    'slot' => $renderedContent,
                ])->render();
            }

            return $renderedContent;
        }

        if ($this->render_markup_view) {
            if ($standalone) {
                return view('trmnl-layouts.single', [
                    'slot' => view($this->render_markup_view, [
                        'size' => $size,
                        'data' => $this->data_payload,
                        'config' => $this->configuration ?? [],
                    ])->render(),
                ])->render();
            }

            return view($this->render_markup_view, [
                'size' => $size,
                'data' => $this->data_payload,
                'config' => $this->configuration ?? [],
            ])->render();

        }

        return '<p>No render markup yet defined for this plugin.</p>';
    }

    /**
     * Get a configuration value by key
     */
    public function getConfiguration(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }
}
