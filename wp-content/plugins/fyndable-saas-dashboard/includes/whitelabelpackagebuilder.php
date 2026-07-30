<?php

namespace SSEOAISaaS;

/**
 * White-Label Package Builder
 *
 * Builds a re-branded copy of the client plugin as a downloadable .zip.
 */
class WhiteLabelPackageBuilder
{
    /**
     * Generate a white-labeled client plugin zip and return the temp file path.
     *
     * @param string $companyName Desired brand name.
     * @return string|\WP_Error Path to the generated zip, or WP_Error on failure.
     */
    public function buildClientZip(string $companyName): string|\WP_Error
    {
        if (empty($companyName)) {
            return new \WP_Error('missing_company', __('Company name is required to build a white-label package.', 'sseo-ai-saas'));
        }

        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_missing', __('The ZipArchive PHP extension is required to build packages.', 'sseo-ai-saas'));
        }

        $companySlug = $this->sanitizeSlug($companyName);
        if (empty($companySlug)) {
            $companySlug = 'agency';
        }

        $folderName = $companySlug . '-client';
        $zipName = $folderName . '.zip';

        $sourceDir = $this->resolveSourceDirectory();
        if (is_wp_error($sourceDir)) {
            return $sourceDir;
        }

        $tmpDir = get_temp_dir();
        if (!is_writable($tmpDir)) {
            return new \WP_Error('temp_not_writable', __('Temporary directory is not writable.', 'sseo-ai-saas'));
        }

        $tmpZip = $tmpDir . wp_unique_filename($tmpDir, $zipName);

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new \WP_Error('zip_open_failed', __('Unable to create zip archive.', 'sseo-ai-saas'));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($realPath, strlen($sourceDir) + 1));
            if ($this->shouldSkipFile($relativePath)) {
                continue;
            }

            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }

            if ($this->shouldRebrandContent($relativePath)) {
                $content = $this->rebrandContent($content, $companyName, $relativePath);
            }

            $zipPath = $folderName . '/' . $relativePath;
            $zip->addFromString($zipPath, $content);
        }

        $zip->close();

        return $tmpZip;
    }

    /**
     * Determine the source directory for the client plugin.
     *
     * Prefers the installed fyndable-client plugin directory, then a versions zip.
     */
    private function resolveSourceDirectory(): string|\WP_Error
    {
        $installed = WP_PLUGIN_DIR . '/fyndable-client';
        if (is_dir($installed)) {
            return $installed;
        }

        // Fallback to packaged version zip (extract to temp).
        $latestVersion = \get_option('sseo_ai_saas_latest_version', '1.4');
        $versionZips = [
            ABSPATH . 'versions/fyndable-client_v' . $latestVersion . '.zip',
            SSEO_AI_SAAS_PLUGIN_DIR . 'versions/fyndable-client_v' . $latestVersion . '.zip',
            SSEO_AI_SAAS_PLUGIN_DIR . '../versions/fyndable-client_v' . $latestVersion . '.zip',
            ABSPATH . 'versions/fyndable-client_v1.4.zip',
            SSEO_AI_SAAS_PLUGIN_DIR . 'versions/fyndable-client_v1.4.zip',
            SSEO_AI_SAAS_PLUGIN_DIR . '../versions/fyndable-client_v1.4.zip',
        ];

        foreach ($versionZips as $zipPath) {
            $zipPath = realpath($zipPath);
            if ($zipPath && file_exists($zipPath)) {
                $extracted = $this->extractClientZip($zipPath);
                if (!is_wp_error($extracted)) {
                    return $extracted;
                }
            }
        }

        return new \WP_Error('source_not_found', __('Client plugin source not found.', 'sseo-ai-saas'));
    }

    /**
     * Extract a packaged client zip to a temporary directory.
     */
    private function extractClientZip(string $zipPath): string|\WP_Error
    {
        $tmpDir = get_temp_dir() . wp_unique_filename(get_temp_dir(), 'wl-client-extract');
        if (!wp_mkdir_p($tmpDir)) {
            return new \WP_Error('extract_mkdir_failed', __('Could not create extraction directory.', 'sseo-ai-saas'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return new \WP_Error('zip_open_failed', __('Could not open client source zip.', 'sseo-ai-saas'));
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // The zip likely contains a single root folder; resolve it.
        $entries = array_diff(scandir($tmpDir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $tmpDir . '/' . $entry;
            if (is_dir($path)) {
                return $path;
            }
        }

        return $tmpDir;
    }

    /**
     * Files/folders to skip in the package.
     */
    private function shouldSkipFile(string $relativePath): bool
    {
        $skip = [
            '.git',
            '.github',
            '.vscode',
            'node_modules',
            'vendor',
            'composer.lock',
            'package-lock.json',
            '.DS_Store',
            'Thumbs.db',
        ];

        $parts = explode('/', $relativePath);
        foreach ($parts as $part) {
            if (in_array($part, $skip, true)) {
                return true;
            }
        }

        // Skip any existing zip files inside the source.
        if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'zip') {
            return true;
        }

        return false;
    }

    /**
     * Decide whether a file's contents should be re-branded.
     */
    private function shouldRebrandContent(string $relativePath): bool
    {
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $rebrandExtensions = ['php', 'md', 'txt', 'po', 'json', 'css', 'js', 'html'];
        return in_array($ext, $rebrandExtensions, true);
    }

    /**
     * Replace visible Fyndable branding with the agency company name.
     */
    private function rebrandContent(string $content, string $companyName, string $relativePath = ''): string
    {
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return $this->rebrandJson($content, $companyName);
        }

        if ($ext === 'po') {
            $escapedCompany = addcslashes($companyName, '"\\');
            $content = str_replace('Fyndable', $escapedCompany, $content);
        } else {
            // Replace the capitalized brand name across comments, strings and UI text.
            $content = str_replace('Fyndable', $companyName, $content);
        }

        // Ensure the plugin header reflects the agency brand.
        $content = preg_replace('/^Plugin Name: .*$/m', 'Plugin Name: ' . $companyName, $content);
        $content = preg_replace('/^Author: .*$/m', 'Author: ' . $companyName, $content);

        if (preg_match('/^Description: .*$/m', $content)) {
            $content = preg_replace('/^Description: .*$/m', 'Description: Advanced AI-powered SEO plugin by ' . $companyName, $content);
        }

        return $content;
    }

    /**
     * Replace Fyndable branding inside JSON translation files by decoding and
     * re-encoding, so quotes and special characters are handled safely.
     */
    private function rebrandJson(string $content, string $companyName): string
    {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return str_replace('Fyndable', $companyName, $content);
        }

        array_walk_recursive($data, function (&$value) use ($companyName) {
            if (is_string($value)) {
                $value = str_replace('Fyndable', $companyName, $value);
            }
        });

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : $content;
    }

    /**
     * Create a safe directory/zip slug from the company name.
     */
    private function sanitizeSlug(string $companyName): string
    {
        $slug = sanitize_title($companyName);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        return trim($slug, '-');
    }
}
