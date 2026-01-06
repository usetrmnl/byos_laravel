<?php

namespace App\Models;

use App\Liquid\FileSystems\InlineTemplatesFileSystem;
use App\Liquid\Filters\Data;
use App\Liquid\Filters\Date;
use App\Liquid\Filters\Localization;
use App\Liquid\Filters\Numbers;
use App\Liquid\Filters\StandardFilters;
use App\Liquid\Filters\StringMarkup;
use App\Liquid\Filters\Uniqueness;
use App\Liquid\Tags\TemplateTag;
use App\Services\Plugin\Parsers\ResponseParserRegistry;
use App\Services\PluginImportService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Keepsuit\LaravelLiquid\LaravelLiquidExtension;
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
        'no_bleed' => 'boolean',
        'dark_mode' => 'boolean',
        'preferred_renderer' => 'string',
        'plugin_type' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });

        static::updating(function ($model): void {
            // Reset image cache when markup changes
            if ($model->isDirty('render_markup')) {
                $model->current_image = null;
            }
        });

        // Sanitize configuration template on save
        static::saving(function ($model): void {
            $model->sanitizeTemplate();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // sanitize configuration template descriptions and help texts (since they allow HTML rendering)
    protected function sanitizeTemplate(): void
    {
        $template = $this->configuration_template;

        if (isset($template['custom_fields']) && is_array($template['custom_fields'])) {
            foreach ($template['custom_fields'] as &$field) {
                if (isset($field['description'])) {
                    $field['description'] = \Stevebauman\Purify\Facades\Purify::clean($field['description']);
                }
                if (isset($field['help_text'])) {
                    $field['help_text'] = \Stevebauman\Purify\Facades\Purify::clean($field['help_text']);
                }
            }

            $this->configuration_template = $template;
        }
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
                if ((in_array($currentValue, [null, '', []], true)) && ! isset($field['default'])) {
                    return true; // Found a required field that is not set and has no default
                }
            }
        }

        return false; // All required fields are set
    }

    public function isDataStale(): bool
    {
        // Image webhook plugins don't use data staleness - images are pushed directly
        if ($this->plugin_type === 'image_webhook') {
            return false;
        }

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
                $headerLines = explode("\n", mb_trim($resolvedHeader));
                foreach ($headerLines as $line) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[mb_trim($parts[0])] = mb_trim($parts[1]);
                    }
                }
            }

            // Resolve Liquid variables in the entire polling_url field first, then split by newline
            $resolvedPollingUrls = $this->resolveLiquidVariables($this->polling_url);
            $urls = array_filter(
                array_map('trim', explode("\n", $resolvedPollingUrls)),
                fn ($url): bool => ! empty($url)
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

                // URL is already resolved, use it directly
                $resolvedUrl = $url;

                try {
                    // Make the request based on the verb
                    $httpResponse = $this->polling_verb === 'post' ? $httpRequest->post($resolvedUrl) : $httpRequest->get($resolvedUrl);

                    $response = $this->parseResponse($httpResponse);

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

                // URL is already resolved, use it directly
                $resolvedUrl = $url;

                try {
                    // Make the request based on the verb
                    $httpResponse = $this->polling_verb === 'post' ? $httpRequest->post($resolvedUrl) : $httpRequest->get($resolvedUrl);

                    $response = $this->parseResponse($httpResponse);

                    // Check if response is an array at root level
                    if (array_keys($response) === range(0, count($response) - 1)) {
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

    private function parseResponse(Response $httpResponse): array
    {
        $parsers = app(ResponseParserRegistry::class)->getParsers();

        foreach ($parsers as $parser) {
            $parserName = class_basename($parser);

            try {
                $result = $parser->parse($httpResponse);

                if ($result !== null) {
                    return $result;
                }
            } catch (Exception $e) {
                Log::warning("Failed to parse {$parserName} response: ".$e->getMessage());
            }
        }

        return ['error' => 'Failed to parse response'];
    }

    /**
     * Apply Liquid template replacements (converts 'with' syntax to comma syntax)
     */
    private function applyLiquidReplacements(string $template): string
    {

        $replacements = [];

        // Apply basic replacements
        $template = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Convert Ruby/strftime date formats to PHP date formats
        $template = $this->convertDateFormats($template);

        // Convert {% render "template" with %} syntax to {% render "template", %} syntax
        $template = preg_replace(
            '/{%\s*render\s+([^}]+?)\s+with\s+/i',
            '{% render $1, ',
            $template
        );

        // Convert for loops with filters to use temporary variables
        // This handles: {% for item in collection | filter: "key", "value" %}
        // Converts to: {% assign temp_filtered = collection | filter: "key", "value" %}{% for item in temp_filtered %}
        $template = preg_replace_callback(
            '/{%\s*for\s+(\w+)\s+in\s+([^|%}]+)\s*\|\s*([^%}]+)%}/',
            function (array $matches): string {
                $variableName = mb_trim($matches[1]);
                $collection = mb_trim($matches[2]);
                $filter = mb_trim($matches[3]);
                $tempVarName = '_temp_'.uniqid();

                return "{% assign {$tempVarName} = {$collection} | {$filter} %}{% for {$variableName} in {$tempVarName} %}";
            },
            (string) $template
        );

        return $template;
    }

    /**
     * Convert Ruby/strftime date formats to PHP date formats in Liquid templates
     */
    private function convertDateFormats(string $template): string
    {
        // Handle date filter formats: date: "format" or date: 'format'
        $template = preg_replace_callback(
            '/date:\s*(["\'])([^"\']+)\1/',
            function (array $matches): string {
                $quote = $matches[1];
                $format = $matches[2];
                $convertedFormat = \App\Liquid\Utils\ExpressionUtils::strftimeToPhpFormat($format);

                return 'date: '.$quote.$convertedFormat.$quote;
            },
            $template
        );

        // Handle l_date filter formats: l_date: "format" or l_date: 'format'
        $template = preg_replace_callback(
            '/l_date:\s*(["\'])([^"\']+)\1/',
            function (array $matches): string {
                $quote = $matches[1];
                $format = $matches[2];
                $convertedFormat = \App\Liquid\Utils\ExpressionUtils::strftimeToPhpFormat($format);

                return 'l_date: '.$quote.$convertedFormat.$quote;
            },
            (string) $template
        );

        return $template;
    }

    /**
     * Check if a template contains a Liquid for loop pattern
     *
     * @param  string  $template  The template string to check
     * @return bool True if the template contains a for loop pattern
     */
    private function containsLiquidForLoop(string $template): bool
    {
        return preg_match('/{%-?\s*for\s+/i', $template) === 1;
    }

    /**
     * Resolve Liquid variables in a template string using the Liquid template engine
     *
     * Uses the external trmnl-liquid renderer when:
     * - preferred_renderer is 'trmnl-liquid'
     * - External renderer is enabled in config
     * - Template contains a Liquid for loop pattern
     *
     * Otherwise uses the internal PHP-based Liquid renderer.
     *
     * @param  string  $template  The template string containing Liquid variables
     * @return string The resolved template with variables replaced with their values
     *
     * @throws LiquidException
     * @throws Exception
     */
    public function resolveLiquidVariables(string $template): string
    {
        // Get configuration variables - make them available at root level
        $variables = $this->configuration ?? [];

        // Check if external renderer should be used
        $useExternalRenderer = $this->preferred_renderer === 'trmnl-liquid'
            && config('services.trmnl.liquid_enabled')
            && $this->containsLiquidForLoop($template);

        if ($useExternalRenderer) {
            // Use external Ruby liquid renderer
            return $this->renderWithExternalLiquidRenderer($template, $variables);
        }

        // Use the Liquid template engine to resolve variables
        $environment = App::make('liquid.environment');
        $environment->filterRegistry->register(StandardFilters::class);
        $liquidTemplate = $environment->parseString($template);
        $context = $environment->newRenderContext(data: $variables);

        return $liquidTemplate->render($context);
    }

    /**
     * Render template using external Ruby liquid renderer
     *
     * @param  string  $template  The liquid template string
     * @param  array  $context  The render context data
     * @return string The rendered HTML
     *
     * @throws Exception
     */
    private function renderWithExternalLiquidRenderer(string $template, array $context): string
    {
        $liquidPath = config('services.trmnl.liquid_path');

        if (empty($liquidPath)) {
            throw new Exception('External liquid renderer path is not configured');
        }

        // HTML encode the template
        $encodedTemplate = htmlspecialchars($template, ENT_QUOTES, 'UTF-8');

        // Encode context as JSON
        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonContext === false) {
            throw new Exception('Failed to encode render context as JSON: '.json_last_error_msg());
        }

        // Validate argument sizes
        app(PluginImportService::class)->validateExternalRendererArguments($encodedTemplate, $jsonContext, $liquidPath);

        // Execute the external renderer
        $process = Process::run([
            $liquidPath,
            '--template',
            $encodedTemplate,
            '--context',
            $jsonContext,
        ]);

        if (! $process->successful()) {
            $errorOutput = $process->errorOutput() ?: $process->output();
            throw new Exception('External liquid renderer failed: '.$errorOutput);
        }

        return $process->output();
    }

    /**
     * Render the plugin's markup
     *
     * @throws LiquidException
     */
    public function render(string $size = 'full', bool $standalone = true, ?Device $device = null): string
    {
        if ($this->plugin_type !== 'recipe') {
            throw new InvalidArgumentException('Render method is only applicable for recipe plugins.');
        }

        if ($this->render_markup) {
            $renderedContent = '';

            if ($this->markup_language === 'liquid') {
                // Get timezone from user or fall back to app timezone
                $timezone = $this->user->timezone ?? config('app.timezone');

                // Calculate UTC offset in seconds
                $utcOffset = (string) Carbon::now($timezone)->getOffset();

                // Build render context
                $context = [
                    'size' => $size,
                    'data' => $this->data_payload,
                    'config' => $this->configuration ?? [],
                    ...(is_array($this->data_payload) ? $this->data_payload : []),
                    'trmnl' => [
                        'system' => [
                            'timestamp_utc' => now()->utc()->timestamp,
                        ],
                        'user' => [
                            'utc_offset' => $utcOffset,
                            'name' => $this->user->name ?? 'Unknown User',
                            'locale' => 'en',
                            'time_zone_iana' => $timezone,
                        ],
                        'plugin_settings' => [
                            'instance_name' => $this->name,
                            'strategy' => $this->data_strategy,
                            'dark_mode' => $this->dark_mode ? 'yes' : 'no',
                            'no_screen_padding' => $this->no_bleed ? 'yes' : 'no',
                            'polling_headers' => $this->polling_header,
                            'polling_url' => $this->polling_url,
                            'custom_fields_values' => [
                                ...(is_array($this->configuration) ? $this->configuration : []),
                            ],
                        ],
                    ],
                ];

                // Check if external renderer should be used
                if ($this->preferred_renderer === 'trmnl-liquid' && config('services.trmnl.liquid_enabled')) {
                    // Use external Ruby renderer - pass raw template without preprocessing
                    $renderedContent = $this->renderWithExternalLiquidRenderer($this->render_markup, $context);
                } else {
                    // Use PHP keepsuit/liquid renderer
                    // Create a custom environment with inline templates support
                    $inlineFileSystem = new InlineTemplatesFileSystem();
                    $environment = new \Keepsuit\Liquid\Environment(
                        fileSystem: $inlineFileSystem,
                        extensions: [new StandardExtension(), new LaravelLiquidExtension()]
                    );

                    // Register all custom filters
                    $environment->filterRegistry->register(Data::class);
                    $environment->filterRegistry->register(Date::class);
                    $environment->filterRegistry->register(Localization::class);
                    $environment->filterRegistry->register(Numbers::class);
                    $environment->filterRegistry->register(StringMarkup::class);
                    $environment->filterRegistry->register(Uniqueness::class);

                    // Register the template tag for inline templates
                    $environment->tagRegistry->register(TemplateTag::class);

                    // Apply Liquid replacements (including 'with' syntax conversion)
                    $processedMarkup = $this->applyLiquidReplacements($this->render_markup);

                    $template = $environment->parseString($processedMarkup);
                    $liquidContext = $environment->newRenderContext(data: $context);
                    $renderedContent = $template->render($liquidContext);
                }
            } else {
                $renderedContent = Blade::render($this->render_markup, [
                    'size' => $size,
                    'data' => $this->data_payload,
                    'config' => $this->configuration ?? [],
                ]);
            }

            if ($standalone) {
                if ($size === 'full') {
                    return view('trmnl-layouts.single', [
                        'colorDepth' => $device?->colorDepth(),
                        'deviceVariant' => $device?->deviceVariant() ?? 'og',
                        'noBleed' => $this->no_bleed,
                        'darkMode' => $this->dark_mode,
                        'scaleLevel' => $device?->scaleLevel(),
                        'slot' => $renderedContent,
                    ])->render();
                }

                return view('trmnl-layouts.mashup', [
                    'mashupLayout' => $this->getPreviewMashupLayoutForSize($size),
                    'colorDepth' => $device?->colorDepth(),
                    'deviceVariant' => $device?->deviceVariant() ?? 'og',
                    'darkMode' => $this->dark_mode,
                    'scaleLevel' => $device?->scaleLevel(),
                    'slot' => $renderedContent,
                ])->render();

            }

            return $renderedContent;
        }

        if ($this->render_markup_view) {
            if ($standalone) {
                return view('trmnl-layouts.single', [
                    'colorDepth' => $device?->colorDepth(),
                    'deviceVariant' => $device?->deviceVariant() ?? 'og',
                    'noBleed' => $this->no_bleed,
                    'darkMode' => $this->dark_mode,
                    'scaleLevel' => $device?->scaleLevel(),
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

    public function getPreviewMashupLayoutForSize(string $size): string
    {
        return match ($size) {
            'half_vertical' => '1Lx1R',
            'quadrant' => '2x2',
            default => '1Tx1B',
        };
    }

    /**
     * Duplicate the plugin, copying all attributes and handling render_markup_view
     *
     * @param  int|null  $userId  Optional user ID for the duplicate. If not provided, uses the original plugin's user_id.
     * @return Plugin The newly created duplicate plugin
     */
    public function duplicate(?int $userId = null): self
    {
        // Get all attributes except id and uuid
        // Use toArray() to get cast values (respects JSON casts)
        $attributes = $this->toArray();
        unset($attributes['id'], $attributes['uuid']);

        // Handle render_markup_view - copy file content to render_markup
        if ($this->render_markup_view) {
            try {
                $basePath = resource_path('views/'.str_replace('.', '/', $this->render_markup_view));
                $paths = [
                    $basePath.'.blade.php',
                    $basePath.'.liquid',
                ];

                $fileContent = null;
                $markupLanguage = null;
                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        $fileContent = file_get_contents($path);
                        // Determine markup language based on file extension
                        $markupLanguage = str_ends_with($path, '.liquid') ? 'liquid' : 'blade';
                        break;
                    }
                }

                if ($fileContent !== null) {
                    $attributes['render_markup'] = $fileContent;
                    $attributes['markup_language'] = $markupLanguage;
                    $attributes['render_markup_view'] = null;
                } else {
                    // File doesn't exist, remove the view reference
                    $attributes['render_markup_view'] = null;
                }
            } catch (Exception $e) {
                // If file reading fails, remove the view reference
                $attributes['render_markup_view'] = null;
            }
        }

        // Append " (Copy)" to the name
        $attributes['name'] = $this->name.' (Copy)';

        // Set user_id - use provided userId or fall back to original plugin's user_id
        $attributes['user_id'] = $userId ?? $this->user_id;

        // Create and return the new plugin
        return self::create($attributes);
    }
}
