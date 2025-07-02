<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Keepsuit\LaravelLiquid\Facades\Liquid;
use Illuminate\Support\Facades\App;
use Keepsuit\Liquid\Exceptions\LiquidException;

class Plugin extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'data_payload' => 'json',
        'data_payload_updated_at' => 'datetime',
        'is_native' => 'boolean',
        'markup_language' => 'string',
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

    public function isDataStale(): bool
    {
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
                $headerLines = explode("\n", trim($this->polling_header));
                foreach ($headerLines as $line) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }

            $httpRequest = Http::withHeaders($headers);

            if ($this->polling_verb === 'post' && $this->polling_body) {
                $httpRequest = $httpRequest->withBody($this->polling_body);
            }

            // Make the request based on the verb
            if ($this->polling_verb === 'post') {
                $response = $httpRequest->post($this->polling_url)->json();
            } else {
                $response = $httpRequest->get($this->polling_url)->json();
            }

            $this->update([
                'data_payload' => $response,
                'data_payload_updated_at' => now(),
            ]);
        }
    }

    /**
     * Render the plugin's markup
     * @throws LiquidException
     */
    public function render(string $size = 'full', bool $standalone = true): string
    {
        if ($this->render_markup) {
            $renderedContent = '';

            if ($this->markup_language === 'liquid') {
                $environment = App::make('liquid.environment');
                $template = $environment->parseString($this->render_markup);
                $context = $environment->newRenderContext(data: ['size' => $size, 'data' => $this->data_payload]);
                $renderedContent = $template->render($context);
            } else {
                $renderedContent = Blade::render($this->render_markup, ['size' => $size, 'data' => $this->data_payload]);
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
                    ])->render(),
                ])->render();
            }

            return view($this->render_markup_view, [
                'size' => $size,
                'data' => $this->data_payload,
            ])->render();

        }

        return '<p>No render markup yet defined for this plugin.</p>';
    }
}
