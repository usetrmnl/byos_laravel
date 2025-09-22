<?php

declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
        ->toThrow(Exception::class, 'Invalid ZIP structure. Required files settings.yml and full.liquid are missing.');
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

it('imports plugin from monorepo with zip_entry_path parameter', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with plugin in a subdirectory
    $zipContent = createMockZipFile([
        'example-plugin/settings.yml' => getValidSettingsYaml(),
        'example-plugin/full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('monorepo.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin');
});

it('imports plugin from monorepo with src subdirectory', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with plugin in a subdirectory with src folder
    $zipContent = createMockZipFile([
        'example-plugin/src/settings.yml' => getValidSettingsYaml(),
        'example-plugin/src/full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('monorepo.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin');
});

it('imports plugin from monorepo with shared.liquid in subdirectory', function () {
    $user = User::factory()->create();

    $zipContent = createMockZipFile([
        'example-plugin/settings.yml' => getValidSettingsYaml(),
        'example-plugin/full.liquid' => getValidFullLiquid(),
        'example-plugin/shared.liquid' => '{% comment %}Monorepo shared styles{% endcomment %}',
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('monorepo.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin->render_markup)->toContain('{% comment %}Monorepo shared styles{% endcomment %}')
        ->and($plugin->render_markup)->toContain('<div class="view view--{{ size }}">');
});

it('imports plugin from URL with zip_entry_path parameter', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with plugin in a subdirectory
    $zipContent = createMockZipFile([
        'example-plugin/settings.yml' => getValidSettingsYaml(),
        'example-plugin/full.liquid' => getValidFullLiquid(),
    ]);

    // Mock the HTTP response
    Http::fake([
        'https://github.com/example/repo/archive/refs/heads/main.zip' => Http::response($zipContent, 200),
    ]);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromUrl(
        'https://github.com/example/repo/archive/refs/heads/main.zip',
        $user,
        'example-plugin'
    );

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://github.com/example/repo/archive/refs/heads/main.zip';
    });
});

it('imports plugin from URL with zip_entry_path and src subdirectory', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with plugin in a subdirectory with src folder
    $zipContent = createMockZipFile([
        'example-plugin/src/settings.yml' => getValidSettingsYaml(),
        'example-plugin/src/full.liquid' => getValidFullLiquid(),
    ]);

    // Mock the HTTP response
    Http::fake([
        'https://github.com/example/repo/archive/refs/heads/main.zip' => Http::response($zipContent, 200),
    ]);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromUrl(
        'https://github.com/example/repo/archive/refs/heads/main.zip',
        $user,
        'example-plugin'
    );

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin');
});

it('imports plugin from GitHub monorepo with repository-named directory', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file that simulates GitHub's ZIP structure with repository-named directory
    $zipContent = createMockZipFile([
        'example-repo-main/another-plugin/src/settings.yml' => "name: Other Plugin\nrefresh_interval: 60\nstrategy: static\npolling_verb: get\nstatic_data: '{}'\ncustom_fields: []",
        'example-repo-main/another-plugin/src/full.liquid' => '<div>Other content</div>',
        'example-repo-main/example-plugin/src/settings.yml' => getValidSettingsYaml(),
        'example-repo-main/example-plugin/src/full.liquid' => getValidFullLiquid(),
    ]);

    // Mock the HTTP response
    Http::fake([
        'https://github.com/example/repo/archive/refs/heads/main.zip' => Http::response($zipContent, 200),
    ]);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromUrl(
        'https://github.com/example/repo/archive/refs/heads/main.zip',
        $user,
        'example-repo-main/example-plugin'
    );

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin'); // Should be from example-plugin, not other-plugin
});

it('finds required files in simple ZIP structure', function () {
    $user = User::factory()->create();

    // Create a simple ZIP file with just one plugin
    $zipContent = createMockZipFile([
        'example-repo-main/example-plugin/src/settings.yml' => getValidSettingsYaml(),
        'example-repo-main/example-plugin/src/full.liquid' => getValidFullLiquid(),
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('simple.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin');
});

it('finds required files in GitHub monorepo structure with zip_entry_path', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file that simulates GitHub's ZIP structure
    $zipContent = createMockZipFile([
        'example-repo-main/example-plugin/src/settings.yml' => getValidSettingsYaml(),
        'example-repo-main/example-plugin/src/full.liquid' => getValidFullLiquid(),
        'example-repo-main/other-plugin/src/settings.yml' => "name: Other Plugin\nrefresh_interval: 60\nstrategy: static\npolling_verb: get\nstatic_data: '{}'\ncustom_fields: []",
        'example-repo-main/other-plugin/src/full.liquid' => '<div>Other content</div>',
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('monorepo.zip', $zipContent);

    $pluginImportService = new PluginImportService();
    $plugin = $pluginImportService->importFromZip($zipFile, $user, 'example-repo-main/example-plugin');

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Test Plugin'); // Should be from example-plugin, not other-plugin
});

it('imports specific plugin from monorepo zip with zip_entry_path parameter', function () {
    $user = User::factory()->create();

    // Create a mock ZIP file with 2 plugins in a monorepo structure
    $zipContent = createMockZipFile([
        'example-plugin/settings.yml' => getValidSettingsYaml(),
        'example-plugin/full.liquid' => getValidFullLiquid(),
        'example-plugin/shared.liquid' => '{% comment %}Monorepo shared styles{% endcomment %}',
        'example-plugin2/settings.yml' => "name: Example Plugin 2\nrefresh_interval: 45\nstrategy: static\npolling_verb: get\nstatic_data: '{}'\ncustom_fields: []",
        'example-plugin2/full.liquid' => '<div class="plugin2-content">Plugin 2 content</div>',
        'example-plugin2/shared.liquid' => '{% comment %}Plugin 2 shared styles{% endcomment %}',
    ]);

    $zipFile = UploadedFile::fake()->createWithContent('monorepo.zip', $zipContent);

    $pluginImportService = new PluginImportService();

    // This test will fail because importFromZip doesn't support zip_entry_path parameter yet
    // The logic needs to be implemented to specify which plugin to import from the monorepo
    $plugin = $pluginImportService->importFromZip($zipFile, $user, 'example-plugin2');

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->user_id)->toBe($user->id)
        ->and($plugin->name)->toBe('Example Plugin 2') // Should import example-plugin2, not example-plugin
        ->and($plugin->render_markup)->toContain('{% comment %}Plugin 2 shared styles{% endcomment %}')
        ->and($plugin->render_markup)->toContain('<div class="plugin2-content">Plugin 2 content</div>');
});

// Helper methods
function createMockZipFile(array $files): string
{
    $zip = new ZipArchive();

    $tempFileName = 'test_zip_'.uniqid().'.zip';
    $tempFile = Storage::path($tempFileName);

    $zip->open($tempFile, ZipArchive::CREATE);

    foreach ($files as $path => $content) {
        $zip->addFromString($path, $content);
    }

    $zip->close();

    $content = file_get_contents($tempFile);

    Storage::delete($tempFileName);

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
