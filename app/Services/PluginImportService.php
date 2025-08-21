<?php

namespace App\Services;

use App\Models\Plugin;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class PluginImportService
{
    /**
     * Import a plugin from a ZIP file
     *
     * @param  UploadedFile  $zipFile  The uploaded ZIP file
     * @param  User  $user  The user importing the plugin
     * @return Plugin The created plugin instance
     *
     * @throws Exception If the ZIP file is invalid or required files are missing
     */
    public function importFromZip(UploadedFile $zipFile, User $user): Plugin
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

            // Find the required files (settings.yml and full.liquid/full.blade.php)
            $filePaths = $this->findRequiredFiles($tempDir);

            // Validate that we found the required files
            if (! $filePaths['settingsYamlPath'] || ! $filePaths['fullLiquidPath']) {
                throw new Exception('Invalid ZIP structure. Required files settings.yml and full.liquid/full.blade.php are missing.');
            }

            // Parse settings.yml
            $settingsYaml = File::get($filePaths['settingsYamlPath']);
            $settings = Yaml::parse($settingsYaml);

            // Read full.liquid content
            $fullLiquid = File::get($filePaths['fullLiquidPath']);
            $fullLiquid = '<div class="view view--{{ size }}">'."\n".$fullLiquid."\n".'</div>';

            // Prepend shared.liquid content if available
            if ($filePaths['sharedLiquidPath'] && File::exists($filePaths['sharedLiquidPath'])) {
                $sharedLiquid = File::get($filePaths['sharedLiquidPath']);
                $fullLiquid = $sharedLiquid."\n".$fullLiquid;
            }

            // Check if the file ends with .liquid to set markup language
            $markupLanguage = 'blade';
            if (pathinfo($filePaths['fullLiquidPath'], PATHINFO_EXTENSION) === 'liquid') {
                $markupLanguage = 'liquid';
            }

            // Ensure custom_fields is properly formatted
            if (! isset($settings['custom_fields']) || ! is_array($settings['custom_fields'])) {
                $settings['custom_fields'] = [];
            }

            // Create configuration template with the custom fields
            $configurationTemplate = [
                'custom_fields' => $settings['custom_fields'],
            ];

            // Extract default values from custom_fields and populate configuration
            $configuration = [];
            if (isset($settings['custom_fields']) && is_array($settings['custom_fields'])) {
                foreach ($settings['custom_fields'] as $field) {
                    if (isset($field['keyname']) && isset($field['default'])) {
                        $configuration[$field['keyname']] = $field['default'];
                    }
                }
            }

            // Create a new plugin
            $plugin = Plugin::create([
                'user_id' => $user->id,
                'name' => $settings['name'] ?? 'Imported Plugin',
                'data_stale_minutes' => $settings['refresh_interval'] ?? 15,
                'data_strategy' => $settings['strategy'] ?? 'static',
                'polling_url' => $settings['polling_url'] ?? null,
                'polling_verb' => $settings['polling_verb'] ?? 'get',
                'polling_header' => isset($settings['polling_headers'])
                    ? str_replace('=', ':', $settings['polling_headers'])
                    : null,
                'polling_body' => $settings['polling_body'] ?? null,
                'markup_language' => $markupLanguage,
                'render_markup' => $fullLiquid,
                'configuration_template' => $configurationTemplate,
                'configuration' => $configuration,
                'data_payload' => json_decode($settings['static_data'] ?? '{}', true),
            ]);

            return $plugin;

        } finally {
            // Clean up temporary directory
            Storage::deleteDirectory($tempDirName);
        }
    }

    /**
     * Find required files in the extracted ZIP directory
     *
     * @param  string  $tempDir  The temporary directory path
     * @return array Array containing paths to required files
     */
    private function findRequiredFiles(string $tempDir): array
    {
        $settingsYamlPath = null;
        $fullLiquidPath = null;
        $sharedLiquidPath = null;

        // First, check if files are directly in the src folder
        if (File::exists($tempDir.'/src/settings.yml')) {
            $settingsYamlPath = $tempDir.'/src/settings.yml';

            // Check for full.liquid or full.blade.php
            if (File::exists($tempDir.'/src/full.liquid')) {
                $fullLiquidPath = $tempDir.'/src/full.liquid';
            } elseif (File::exists($tempDir.'/src/full.blade.php')) {
                $fullLiquidPath = $tempDir.'/src/full.blade.php';
            }

            // Check for shared.liquid in the same directory
            if (File::exists($tempDir.'/src/shared.liquid')) {
                $sharedLiquidPath = $tempDir.'/src/shared.liquid';
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
                }

                // If we found both required files, break the loop
                if ($settingsYamlPath && $fullLiquidPath) {
                    break;
                }
            }

            // If we found the files but they're not in the src folder,
            // check if they're in the root of the ZIP or in a subfolder
            if ($settingsYamlPath && $fullLiquidPath) {
                // If the files are in the root of the ZIP, create a src folder and move them there
                $srcDir = dirname($settingsYamlPath);

                // If the parent directory is not named 'src', create a src directory
                if (basename($srcDir) !== 'src') {
                    $newSrcDir = $tempDir.'/src';
                    File::makeDirectory($newSrcDir, 0755, true);

                    // Copy the files to the src directory
                    File::copy($settingsYamlPath, $newSrcDir.'/settings.yml');
                    File::copy($fullLiquidPath, $newSrcDir.'/full.liquid');

                    // Copy shared.liquid if it exists
                    if ($sharedLiquidPath) {
                        File::copy($sharedLiquidPath, $newSrcDir.'/shared.liquid');
                        $sharedLiquidPath = $newSrcDir.'/shared.liquid';
                    }

                    // Update the paths
                    $settingsYamlPath = $newSrcDir.'/settings.yml';
                    $fullLiquidPath = $newSrcDir.'/full.liquid';
                }
            }
        }

        return [
            'settingsYamlPath' => $settingsYamlPath,
            'fullLiquidPath' => $fullLiquidPath,
            'sharedLiquidPath' => $sharedLiquidPath,
        ];
    }
}
