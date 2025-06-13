<?php

declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('imports plugin from valid zip file', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with the required structure
    $zipContent = createMockZipFile([
        'src/settings.yml' => getValidSettingsYaml(),
        'src/full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin')
        ->and($plugin->data_stale_minutes)->toBe(30)
        ->and($plugin->data_strategy)->toBe('static')
        ->and($plugin->markup_language)->toBe('liquid')
        ->and($plugin->configuration_template)->toHaveKey('custom_fields')
        ->and($plugin->configuration)->toHaveKey('api_key')
        ->and($plugin->configuration['api_key'])->toBe('default-api-key');
});

it('imports plugin with shared.liquid file', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'src/settings.yml' => getValidSettingsYaml(),
        'src/full.liquid' => getValidFullLiquid(),
        'src/shared.liquid' => '{% comment %}Shared styles{% endcomment %}',
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin->render_markup)->toContain('{% comment %}Shared styles{% endcomment %}')
        ->and($plugin->render_markup)->toContain('<div class="view view--{{ size }}">');
});

it('imports plugin with files in root directory', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'settings.yml' => getValidSettingsYaml(),
        'full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->name)->toBe('Test Plugin');
});

it('throws exception for invalid zip file', function () {
    $user = User::factory()->create();

    $zipFile = UploadedFile::fake()->createWithContent('invalid.zip', 'not a zip file');

    $pluginImportService = new PluginImportService();
    expect(fn () => $pluginImportService->importFromZip($zipFile, $user))
        ->toThrow(Exception::class, 'Could not open the ZIP file.');
});

it('throws exception for missing required files', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'src/settings.yml' => getValidSettingsYaml(),
        // Missing full.liquid
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    expect(fn () => $pluginImportService->importFromZip($zipFile, $user))
        ->toThrow(Exception::class, 'Invalid ZIP structure. Required files settings.yml and full.liquid/full.blade.php are missing.');
});

it('sets default values when settings are missing', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'src/settings.yml' => "name: Minimal Plugin\n",
        'src/full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin->name)->toBe('Minimal Plugin')
        ->and($plugin->data_stale_minutes)->toBe(15) // default value
        ->and($plugin->data_strategy)->toBe('static') // default value
        ->and($plugin->polling_verb)->toBe('get'); // default value
});

it('handles blade markup language correctly', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'src/settings.yml' => getValidSettingsYaml(),
        'src/full.blade.php' => '<div>Blade template</div>',
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('test-plugin.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin->markup_language)->toBe('blade');
});

// Helper methods
function createMockZipFile(array $files): string
{
    $zip = new ZipArchive();
    $tempFile = tempnam(sys_get_temp_dir(), 'test_zip_');

    $zip->open($tempFile, ZipArchive::CREATE);

    foreach ($files as $path => $content) {
        $zip->addFromString($path, $content);
    }

    $zip->close();

    $content = file_get_contents($tempFile);
    unlink($tempFile);

    return $content;
}

function getValidSettingsYaml(): string
{
    return <<<'YAML'
name: Test Plugin
refresh_interval: 30
strategy: static
polling_verb: get
static_data: '{"test": "data"}'
custom_fields:
  - keyname: api_key
    field_type: text
    default: default-api-key
    label: API Key
YAML;
}

function getValidFullLiquid(): string
{
    return <<<'LIQUID'
<div class="plugin-content">
  <h1>{{ data.title }}</h1>
  <p>{{ data.description }}</p>
</div>
LIQUID;
}
