<?php

namespace App\Services;

use App\Models\Plugin;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class PluginImportService
{
    /**
     * Validate YAML settings
     *
     * @param  array  $settings  The parsed YAML settings
     *
     * @throws Exception
     */
    private function validateYAML(array $settings): void
    {
        if (! isset($settings['custom_fields']) || ! is_array($settings['custom_fields'])) {
            return;
        }

        foreach ($settings['custom_fields'] as $field) {
            if (isset($field['field_type']) && $field['field_type'] === 'multi_string') {

                if (isset($field['default']) && str_contains((string) $field['default'], ',')) {
                    throw new Exception("Validation Error: The default value for multistring fields like `{$field['keyname']}` cannot contain commas.");
                }

                if (isset($field['placeholder']) && str_contains((string) $field['placeholder'], ',')) {
                    throw new Exception("Validation Error: The placeholder value for multistring fields like `{$field['keyname']}` cannot contain commas.");
                }

            }
        }
    }

    /**
     * Import a plugin from a ZIP file
     *
     * @param  UploadedFile  $zipFile  The uploaded ZIP file
     * @param  User  $user  The user importing the plugin
     * @param  string|null  $zipEntryPath  Optional path to specific plugin in monorepo
     * @return Plugin The created plugin instance
     *
     * @throws Exception If the ZIP file is invalid or required files are missing
     */
    public function importFromZip(UploadedFile $zipFile, User $user, ?string $zipEntryPath = null): Plugin
    {
        // Create a temporary directory using Laravel's temporary directory helper
        $tempDirName = 'temp/'.uniqid('plugin_import_', true);
        Storage::makeDirectory($tempDirName);
        $tempDir = Storage::path($tempDirName);

        try {
            // Get the real path of the temporary file
            $zipFullPath = $zipFile->getRealPath();

            // Extract the ZIP file using ZipArchive
            $zip = new ZipArchive();
            if ($zip->open($zipFullPath) !== true) {
                throw new Exception('Could not open the ZIP file.');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Find the required files (settings.yml and full.liquid/full.blade.php/shared.liquid/shared.blade.php)
            $filePaths = $this->findRequiredFiles($tempDir, $zipEntryPath);

            // Validate that we found the required files
            if (! $filePaths['settingsYamlPath']) {
                throw new Exception('Invalid ZIP structure. Required file settings.yml is missing.');
            }

            // Validate that we have at least one template file
            if (! $filePaths['fullLiquidPath'] && ! $filePaths['sharedLiquidPath'] && ! $filePaths['sharedBladePath']) {
                throw new Exception('Invalid ZIP structure. At least one of the following files is required: full.liquid, full.blade.php, shared.liquid, or shared.blade.php.');
            }

            // Parse settings.yml
            $settingsYaml = File::get($filePaths['settingsYamlPath']);
            $settings = Yaml::parse($settingsYaml);
            $this->validateYAML($settings);

            // Determine which template file to use and read its content
            $templatePath = null;
            $markupLanguage = 'blade';

            if ($filePaths['fullLiquidPath']) {
                $templatePath = $filePaths['fullLiquidPath'];
                $fullLiquid = File::get($templatePath);

                // Prepend shared.liquid or shared.blade.php content if available
                if ($filePaths['sharedLiquidPath'] && File::exists($filePaths['sharedLiquidPath'])) {
                    $sharedLiquid = File::get($filePaths['sharedLiquidPath']);
                    $fullLiquid = $sharedLiquid."\n".$fullLiquid;
                } elseif ($filePaths['sharedBladePath'] && File::exists($filePaths['sharedBladePath'])) {
                    $sharedBlade = File::get($filePaths['sharedBladePath']);
                    $fullLiquid = $sharedBlade."\n".$fullLiquid;
                }

                // Check if the file ends with .liquid to set markup language
                if (pathinfo((string) $templatePath, PATHINFO_EXTENSION) === 'liquid') {
                    $markupLanguage = 'liquid';
                    $fullLiquid = '<div class="view view--{{ size }}">'."\n".$fullLiquid."\n".'</div>';
                }
            } elseif ($filePaths['sharedLiquidPath']) {
                $templatePath = $filePaths['sharedLiquidPath'];
                $fullLiquid = File::get($templatePath);
                $markupLanguage = 'liquid';
                $fullLiquid = '<div class="view view--{{ size }}">'."\n".$fullLiquid."\n".'</div>';
            } elseif ($filePaths['sharedBladePath']) {
                $templatePath = $filePaths['sharedBladePath'];
                $fullLiquid = File::get($templatePath);
                $markupLanguage = 'blade';
            }

            // Ensure custom_fields is properly formatted
            if (! isset($settings['custom_fields']) || ! is_array($settings['custom_fields'])) {
                $settings['custom_fields'] = [];
            }

            // Normalize options in custom_fields (convert non-named values to named values)
            $settings['custom_fields'] = $this->normalizeCustomFieldsOptions($settings['custom_fields']);

            // Create configuration template with the custom fields
            $configurationTemplate = [
                'custom_fields' => $settings['custom_fields'],
            ];

            $plugin_updated = isset($settings['id'])
                            && Plugin::where('user_id', $user->id)->where('trmnlp_id', $settings['id'])->exists();
            // Create a new plugin
            $plugin = Plugin::updateOrCreate(
                [
                    'user_id' => $user->id, 'trmnlp_id' => $settings['id'] ?? Uuid::v7(),
                ],
                [
                    'user_id' => $user->id,
                    'name' => $settings['name'] ?? 'Imported Plugin',
                    'trmnlp_id' => $settings['id'] ?? Uuid::v7(),
                    'data_stale_minutes' => $settings['refresh_interval'] ?? 15,
                    'data_strategy' => $settings['strategy'] ?? 'static',
                    'polling_url' => $settings['polling_url'] ?? null,
                    'polling_verb' => $settings['polling_verb'] ?? 'get',
                    'polling_header' => isset($settings['polling_headers'])
                        ? str_replace('=', ':', $settings['polling_headers'])
                        : null,
                    'polling_body' => $settings['polling_body'] ?? null,
                    'markup_language' => $markupLanguage,
                    'render_markup' => $fullLiquid ?? null,
                    'configuration_template' => $configurationTemplate,
                    'data_payload' => json_decode($settings['static_data'] ?? '{}', true),
                ]);

            if (! $plugin_updated) {
                // Extract default values from custom_fields and populate configuration
                $configuration = [];
                foreach ($settings['custom_fields'] as $field) {
                    if (isset($field['keyname']) && isset($field['default'])) {
                        $configuration[$field['keyname']] = $field['default'];
                    }
                }
                // set only if plugin is new
                $plugin->update([
                    'configuration' => $configuration,
                ]);
            }
            $plugin['trmnlp_yaml'] = $settingsYaml;

            return $plugin;

        } finally {
            // Clean up temporary directory
            Storage::deleteDirectory($tempDirName);
        }
    }

    /**
     * Import a plugin from a ZIP URL
     *
     * @param  string  $zipUrl  The URL to the ZIP file
     * @param  User  $user  The user importing the plugin
     * @param  string|null  $zipEntryPath  Optional path to specific plugin in monorepo
     * @param  string|null  $preferredRenderer  Optional preferred renderer (e.g., 'trmnl-liquid')
     * @param  string|null  $iconUrl  Optional icon URL to set on the plugin
     * @param  bool  $allowDuplicate  If true, generate a new UUID for trmnlp_id if a plugin with the same trmnlp_id already exists
     * @return Plugin The created plugin instance
     *
     * @throws Exception If the ZIP file is invalid or required files are missing
     */
    public function importFromUrl(string $zipUrl, User $user, ?string $zipEntryPath = null, $preferredRenderer = null, ?string $iconUrl = null, bool $allowDuplicate = false): Plugin
    {
        // Download the ZIP file
        $response = Http::timeout(60)->get($zipUrl);

        if (! $response->successful()) {
            throw new Exception('Could not download the ZIP file from the provided URL.');
        }

        // Create a temporary file
        $tempDirName = 'temp/'.uniqid('plugin_import_', true);
        Storage::makeDirectory($tempDirName);
        $tempDir = Storage::path($tempDirName);
        $zipPath = $tempDir.'/plugin.zip';

        // Save the downloaded content to a temporary file
        File::put($zipPath, $response->body());

        try {
            // Extract the ZIP file using ZipArchive
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Could not open the downloaded ZIP file.');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Find the required files (settings.yml and full.liquid/full.blade.php/shared.liquid/shared.blade.php)
            $filePaths = $this->findRequiredFiles($tempDir, $zipEntryPath);

            // Validate that we found the required files
            if (! $filePaths['settingsYamlPath']) {
                throw new Exception('Invalid ZIP structure. Required file settings.yml is missing.');
            }

            // Validate that we have at least one template file
            if (! $filePaths['fullLiquidPath'] && ! $filePaths['sharedLiquidPath'] && ! $filePaths['sharedBladePath']) {
                throw new Exception('Invalid ZIP structure. At least one of the following files is required: full.liquid, full.blade.php, shared.liquid, or shared.blade.php.');
            }

            // Parse settings.yml
            $settingsYaml = File::get($filePaths['settingsYamlPath']);
            $settings = Yaml::parse($settingsYaml);
            $this->validateYAML($settings);

            // Determine which template file to use and read its content
            $templatePath = null;
            $markupLanguage = 'blade';

            if ($filePaths['fullLiquidPath']) {
                $templatePath = $filePaths['fullLiquidPath'];
                $fullLiquid = File::get($templatePath);

                // Prepend shared.liquid or shared.blade.php content if available
                if ($filePaths['sharedLiquidPath'] && File::exists($filePaths['sharedLiquidPath'])) {
                    $sharedLiquid = File::get($filePaths['sharedLiquidPath']);
                    $fullLiquid = $sharedLiquid."\n".$fullLiquid;
                } elseif ($filePaths['sharedBladePath'] && File::exists($filePaths['sharedBladePath'])) {
                    $sharedBlade = File::get($filePaths['sharedBladePath']);
                    $fullLiquid = $sharedBlade."\n".$fullLiquid;
                }

                // Check if the file ends with .liquid to set markup language
                if (pathinfo((string) $templatePath, PATHINFO_EXTENSION) === 'liquid') {
                    $markupLanguage = 'liquid';
                    $fullLiquid = '<div class="view view--{{ size }}">'."\n".$fullLiquid."\n".'</div>';
                }
            } elseif ($filePaths['sharedLiquidPath']) {
                $templatePath = $filePaths['sharedLiquidPath'];
                $fullLiquid = File::get($templatePath);
                $markupLanguage = 'liquid';
                $fullLiquid = '<div class="view view--{{ size }}">'."\n".$fullLiquid."\n".'</div>';
            } elseif ($filePaths['sharedBladePath']) {
                $templatePath = $filePaths['sharedBladePath'];
                $fullLiquid = File::get($templatePath);
                $markupLanguage = 'blade';
            }

            // Ensure custom_fields is properly formatted
            if (! isset($settings['custom_fields']) || ! is_array($settings['custom_fields'])) {
                $settings['custom_fields'] = [];
            }

            // Normalize options in custom_fields (convert non-named values to named values)
            $settings['custom_fields'] = $this->normalizeCustomFieldsOptions($settings['custom_fields']);

            // Create configuration template with the custom fields
            $configurationTemplate = [
                'custom_fields' => $settings['custom_fields'],
            ];

            // Determine the trmnlp_id to use
            $trmnlpId = $settings['id'] ?? Uuid::v7();

            // If allowDuplicate is true and a plugin with this trmnlp_id already exists, generate a new UUID
            if ($allowDuplicate && isset($settings['id']) && Plugin::where('user_id', $user->id)->where('trmnlp_id', $settings['id'])->exists()) {
                $trmnlpId = Uuid::v7();
            }

            $plugin_updated = ! $allowDuplicate && isset($settings['id'])
                            && Plugin::where('user_id', $user->id)->where('trmnlp_id', $settings['id'])->exists();

            // Create a new plugin
            $plugin = Plugin::updateOrCreate(
                [
                    'user_id' => $user->id, 'trmnlp_id' => $trmnlpId,
                ],
                [
                    'user_id' => $user->id,
                    'name' => $settings['name'] ?? 'Imported Plugin',
                    'trmnlp_id' => $trmnlpId,
                    'data_stale_minutes' => $settings['refresh_interval'] ?? 15,
                    'data_strategy' => $settings['strategy'] ?? 'static',
                    'polling_url' => $settings['polling_url'] ?? null,
                    'polling_verb' => $settings['polling_verb'] ?? 'get',
                    'polling_header' => isset($settings['polling_headers'])
                        ? str_replace('=', ':', $settings['polling_headers'])
                        : null,
                    'polling_body' => $settings['polling_body'] ?? null,
                    'markup_language' => $markupLanguage,
                    'render_markup' => $fullLiquid ?? null,
                    'configuration_template' => $configurationTemplate,
                    'data_payload' => json_decode($settings['static_data'] ?? '{}', true),
                    'preferred_renderer' => $preferredRenderer,
                    'icon_url' => $iconUrl,
                ]);

            if (! $plugin_updated) {
                // Extract default values from custom_fields and populate configuration
                $configuration = [];
                foreach ($settings['custom_fields'] as $field) {
                    if (isset($field['keyname']) && isset($field['default'])) {
                        $configuration[$field['keyname']] = $field['default'];
                    }
                }
                // set only if plugin is new
                $plugin->update([
                    'configuration' => $configuration,
                ]);
            }
            $plugin['trmnlp_yaml'] = $settingsYaml;

            return $plugin;

        } finally {
            // Clean up temporary directory
            Storage::deleteDirectory($tempDirName);
        }
    }

    private function findRequiredFiles(string $tempDir, ?string $zipEntryPath = null): array
    {
        $settingsYamlPath = null;
        $fullLiquidPath = null;
        $sharedLiquidPath = null;
        $sharedBladePath = null;

        // If zipEntryPath is specified, look for files in that specific directory first
        if ($zipEntryPath) {
            $targetDir = $tempDir.'/'.$zipEntryPath;
            if (File::exists($targetDir)) {
                // Check if files are directly in the target directory
                if (File::exists($targetDir.'/settings.yml')) {
                    $settingsYamlPath = $targetDir.'/settings.yml';

                    if (File::exists($targetDir.'/full.liquid')) {
                        $fullLiquidPath = $targetDir.'/full.liquid';
                    } elseif (File::exists($targetDir.'/full.blade.php')) {
                        $fullLiquidPath = $targetDir.'/full.blade.php';
                    }

                    if (File::exists($targetDir.'/shared.liquid')) {
                        $sharedLiquidPath = $targetDir.'/shared.liquid';
                    } elseif (File::exists($targetDir.'/shared.blade.php')) {
                        $sharedBladePath = $targetDir.'/shared.blade.php';
                    }
                }

                // Check if files are in src subdirectory of target directory
                if (! $settingsYamlPath && File::exists($targetDir.'/src/settings.yml')) {
                    $settingsYamlPath = $targetDir.'/src/settings.yml';

                    if (File::exists($targetDir.'/src/full.liquid')) {
                        $fullLiquidPath = $targetDir.'/src/full.liquid';
                    } elseif (File::exists($targetDir.'/src/full.blade.php')) {
                        $fullLiquidPath = $targetDir.'/src/full.blade.php';
                    }

                    if (File::exists($targetDir.'/src/shared.liquid')) {
                        $sharedLiquidPath = $targetDir.'/src/shared.liquid';
                    } elseif (File::exists($targetDir.'/src/shared.blade.php')) {
                        $sharedBladePath = $targetDir.'/src/shared.blade.php';
                    }
                }

                // If we found the required files in the target directory, return them
                if ($settingsYamlPath && ($fullLiquidPath || $sharedLiquidPath || $sharedBladePath)) {
                    return [
                        'settingsYamlPath' => $settingsYamlPath,
                        'fullLiquidPath' => $fullLiquidPath,
                        'sharedLiquidPath' => $sharedLiquidPath,
                        'sharedBladePath' => $sharedBladePath,
                    ];
                }
            }
        }

        // First, check if files are directly in the src folder
        if (File::exists($tempDir.'/src/settings.yml')) {
            $settingsYamlPath = $tempDir.'/src/settings.yml';

            // Check for full.liquid or full.blade.php
            if (File::exists($tempDir.'/src/full.liquid')) {
                $fullLiquidPath = $tempDir.'/src/full.liquid';
            } elseif (File::exists($tempDir.'/src/full.blade.php')) {
                $fullLiquidPath = $tempDir.'/src/full.blade.php';
            }

            // Check for shared.liquid or shared.blade.php in the same directory
            if (File::exists($tempDir.'/src/shared.liquid')) {
                $sharedLiquidPath = $tempDir.'/src/shared.liquid';
            } elseif (File::exists($tempDir.'/src/shared.blade.php')) {
                $sharedBladePath = $tempDir.'/src/shared.blade.php';
            }
        } else {
            // Search for the files in the extracted directory structure
            $directories = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($directories);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                $filepath = $file->getPathname();

                if ($filename === 'settings.yml') {
                    $settingsYamlPath = $filepath;
                } elseif ($filename === 'full.liquid' || $filename === 'full.blade.php') {
                    $fullLiquidPath = $filepath;
                } elseif ($filename === 'shared.liquid') {
                    $sharedLiquidPath = $filepath;
                } elseif ($filename === 'shared.blade.php') {
                    $sharedBladePath = $filepath;
                }
            }

            // Check if shared.liquid or shared.blade.php exists in the same directory as full.liquid
            if ($settingsYamlPath && $fullLiquidPath && ! $sharedLiquidPath && ! $sharedBladePath) {
                $fullLiquidDir = dirname((string) $fullLiquidPath);
                if (File::exists($fullLiquidDir.'/shared.liquid')) {
                    $sharedLiquidPath = $fullLiquidDir.'/shared.liquid';
                } elseif (File::exists($fullLiquidDir.'/shared.blade.php')) {
                    $sharedBladePath = $fullLiquidDir.'/shared.blade.php';
                }
            }

            // If we found the files but they're not in the src folder,
            // check if they're in the root of the ZIP or in a subfolder
            if ($settingsYamlPath && ($fullLiquidPath || $sharedLiquidPath || $sharedBladePath)) {
                // If the files are in the root of the ZIP, create a src folder and move them there
                $srcDir = dirname((string) $settingsYamlPath);

                // If the parent directory is not named 'src', create a src directory
                if (basename($srcDir) !== 'src') {
                    $newSrcDir = $tempDir.'/src';
                    File::makeDirectory($newSrcDir, 0755, true);

                    // Copy the files to the src directory
                    File::copy($settingsYamlPath, $newSrcDir.'/settings.yml');

                    // Copy full.liquid or full.blade.php if it exists
                    if ($fullLiquidPath) {
                        $extension = pathinfo((string) $fullLiquidPath, PATHINFO_EXTENSION);
                        File::copy($fullLiquidPath, $newSrcDir.'/full.'.$extension);
                        $fullLiquidPath = $newSrcDir.'/full.'.$extension;
                    }

                    // Copy shared.liquid or shared.blade.php if it exists
                    if ($sharedLiquidPath) {
                        File::copy($sharedLiquidPath, $newSrcDir.'/shared.liquid');
                        $sharedLiquidPath = $newSrcDir.'/shared.liquid';
                    } elseif ($sharedBladePath) {
                        File::copy($sharedBladePath, $newSrcDir.'/shared.blade.php');
                        $sharedBladePath = $newSrcDir.'/shared.blade.php';
                    }

                    // Update the paths
                    $settingsYamlPath = $newSrcDir.'/settings.yml';
                }
            }
        }

        return [
            'settingsYamlPath' => $settingsYamlPath,
            'fullLiquidPath' => $fullLiquidPath,
            'sharedLiquidPath' => $sharedLiquidPath,
            'sharedBladePath' => $sharedBladePath,
        ];
    }

    /**
     * Normalize options in custom_fields by converting non-named values to named values
     * This ensures that options like ["true", "false"] become [["true" => "true"], ["false" => "false"]]
     *
     * @param  array  $customFields  The custom_fields array from settings
     * @return array The normalized custom_fields array
     */
    private function normalizeCustomFieldsOptions(array $customFields): array
    {
        foreach ($customFields as &$field) {
            // Only process select fields with options
            if (isset($field['field_type']) && $field['field_type'] === 'select' && isset($field['options']) && is_array($field['options'])) {
                $normalizedOptions = [];
                foreach ($field['options'] as $option) {
                    // If option is already a named value (array with key-value pair), keep it as is
                    if (is_array($option)) {
                        $normalizedOptions[] = $option;
                    } else {
                        // Convert non-named value to named value
                        // Convert boolean to string, use lowercase for label
                        $value = is_bool($option) ? ($option ? 'true' : 'false') : (string) $option;
                        $normalizedOptions[] = [$value => $value];
                    }
                }
                $field['options'] = $normalizedOptions;

                // Normalize default value to match normalized option values
                if (isset($field['default'])) {
                    $default = $field['default'];
                    // If default is boolean, convert to string to match normalized options
                    if (is_bool($default)) {
                        $field['default'] = $default ? 'true' : 'false';
                    } else {
                        // Convert to string to ensure consistency
                        $field['default'] = (string) $default;
                    }
                }
            }
        }

        return $customFields;
    }

    /**
     * Validate that template and context are within command-line argument limits
     *
     * @param  string  $template  The liquid template string
     * @param  string  $jsonContext  The JSON-encoded context
     * @param  string  $liquidPath  The path to the liquid renderer executable
     *
     * @throws Exception If the template or context exceeds argument limits
     */
    public function validateExternalRendererArguments(string $template, string $jsonContext, string $liquidPath): void
    {
        // MAX_ARG_STRLEN on Linux is typically 131072 (128KB) for individual arguments
        // ARG_MAX is the total size of all arguments (typically 2MB on modern systems)
        $maxIndividualArgLength = 131072; // 128KB - MAX_ARG_STRLEN limit
        $maxTotalArgLength = $this->getMaxArgumentLength();

        // Check individual argument sizes (template and context are the largest)
        if (mb_strlen($template) > $maxIndividualArgLength || mb_strlen($jsonContext) > $maxIndividualArgLength) {
            throw new Exception('Context too large for external liquid renderer. Reduce the size of the Payload or Template.');
        }

        // Calculate total size of all arguments (path + flags + template + context)
        // Add overhead for path, flags, and separators (conservative estimate: ~200 bytes)
        $totalArgSize = mb_strlen($liquidPath) + mb_strlen('--template') + mb_strlen($template)
            + mb_strlen('--context') + mb_strlen($jsonContext) + 200;

        if ($totalArgSize > $maxTotalArgLength) {
            throw new Exception('Context too large for external liquid renderer. Reduce the size of the Payload or Template.');
        }
    }

    /**
     * Get the maximum argument length for command-line arguments
     *
     * @return int Maximum argument length in bytes
     */
    private function getMaxArgumentLength(): int
    {
        // Try to get ARG_MAX from system using getconf
        $argMax = null;
        if (function_exists('shell_exec')) {
            $result = @shell_exec('getconf ARG_MAX 2>/dev/null');
            if ($result !== null && is_numeric(mb_trim($result))) {
                $argMax = (int) mb_trim($result);
            }
        }

        // Use conservative fallback if ARG_MAX cannot be determined
        // ARG_MAX on macOS is typically 262144 (256KB), on Linux it's usually 2097152 (2MB)
        // We use 200KB as a conservative limit that works on both systems
        // Note: ARG_MAX includes environment variables, so we leave headroom
        return $argMax !== null ? min($argMax, 204800) : 204800;
    }
}
