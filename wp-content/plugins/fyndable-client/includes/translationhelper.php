<?php

namespace SSEOAIClient;

/**
 * Translation Helper
 * Generates .mo files from .po files using WordPress's native POMO library
 * for reliable, spec-compliant binary MO output.
 */
class TranslationHelper
{
    /**
     * Generate MO file from PO file.
     *
     * Uses WordPress's built-in POMO\PO and POMO\MO classes when available
     * (i.e. inside WordPress). Falls back to a minimal custom generator for
     * standalone CLI use.
     */
    public static function generateMoFile(string $poFile, string $moFile): bool
    {
        if (!file_exists($poFile)) {
            return false;
        }

        // Preferred path: use WordPress's native POMO parser/writer.
        if (self::generateWithPomo($poFile, $moFile)) {
            return true;
        }

        // Fallback: minimal custom generator (e.g. for CLI use outside WP).
        return self::generateWithFallback($poFile, $moFile);
    }

    /**
     * Generate using WordPress POMO classes.
     */
    private static function generateWithPomo(string $poFile, string $moFile): bool
    {
        if (!class_exists('PO') && !class_exists('POMO\PO')) {
            // Try to load POMO manually if WordPress hasn't loaded it yet.
            $pomoPo = ABSPATH . 'wp-includes/pomo/po.php';
            if (defined('ABSPATH') && file_exists($pomoPo)) {
                require_once $pomoPo;
            } else {
                return false;
            }
        }

        if (!class_exists('MO') && !class_exists('POMO\MO')) {
            $pomoMo = ABSPATH . 'wp-includes/pomo/mo.php';
            if (defined('ABSPATH') && file_exists($pomoMo)) {
                require_once $pomoMo;
            } else {
                return false;
            }
        }

        $poClass = class_exists('PO') ? 'PO' : 'POMO\PO';
        $moClass = class_exists('MO') ? 'MO' : 'POMO\MO';

        $po = new $poClass();
        if (!$po->import_from_file($poFile)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TranslationHelper: PO import failed for ' . $poFile);
            }
            return false;
        }

        $mo = new $moClass();
        $mo->entries = $po->entries;

        if (!$mo->export_to_file($moFile)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TranslationHelper: MO export failed for ' . $moFile);
            }
            return false;
        }

        return true;
    }

    /**
     * Fallback generator for use outside WordPress (e.g. CLI).
     * Produces a valid MO file with proper unescaping.
     */
    private static function generateWithFallback(string $poFile, string $moFile): bool
    {
        $entries = self::parsePoFile($poFile);
        if (empty($entries)) {
            return false;
        }

        // Remove empty msgid (header)
        unset($entries['']);

        $mo = '';

        // Magic number (little endian)
        $mo .= pack('L', 0x950412de);

        // Revision
        $mo .= pack('L', 0);

        // Number of strings
        $count = count($entries);
        $mo .= pack('L', $count);

        // Offset of table with original strings
        $mo .= pack('L', 28);

        // Offset of table with translation strings
        $mo .= pack('L', 28 + $count * 8);

        // Size of hashing table (not used)
        $mo .= pack('L', 0);

        // Offset of hashing table (not used)
        $mo .= pack('L', 0);

        // Build string tables
        $originals = '';
        $translations = '';
        $offset = 28 + $count * 16;

        $origTable = '';
        $transTable = '';

        foreach ($entries as $msgid => $msgstr) {
            $origTable .= pack('L', strlen($msgid));
            $origTable .= pack('L', $offset);
            $originals .= $msgid . "\0";
            $offset += strlen($msgid) + 1;
        }

        foreach ($entries as $msgid => $msgstr) {
            $transTable .= pack('L', strlen($msgstr));
            $transTable .= pack('L', $offset);
            $translations .= $msgstr . "\0";
            $offset += strlen($msgstr) + 1;
        }

        $mo .= $origTable . $transTable . $originals . $translations;

        return file_put_contents($moFile, $mo) !== false;
    }

    /**
     * Parse a .po file into an associative array [msgid => msgstr].
     * Properly handles multi-line entries and escape sequences.
     */
    private static function parsePoFile(string $poFile): array
    {
        $content = file_get_contents($poFile);
        if ($content === false) {
            return [];
        }

        $entries = [];
        $currentMsgid = '';
        $currentMsgstr = '';
        $inMsgid = false;
        $inMsgstr = false;

        // Normalize line endings
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        $flush = function () use (&$entries, &$currentMsgid, &$currentMsgstr, &$inMsgid, &$inMsgstr): void {
            if ($currentMsgid !== '' || $currentMsgstr !== '') {
                $key = stripslashes($currentMsgid);
                $val = stripslashes($currentMsgstr);
                if ($key !== '') {
                    $entries[$key] = $val;
                }
            }
            $currentMsgid = '';
            $currentMsgstr = '';
            $inMsgid = false;
            $inMsgstr = false;
        };

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                $flush();
                continue;
            }

            if (strpos($line, 'msgid ') === 0) {
                $flush();
                $currentMsgid = trim(substr($line, 6), '"');
                $inMsgid = true;
                $inMsgstr = false;
            } elseif (strpos($line, 'msgstr ') === 0) {
                $currentMsgstr = trim(substr($line, 7), '"');
                $inMsgid = false;
                $inMsgstr = true;
            } elseif ($line[0] === '"' && $inMsgid) {
                $currentMsgid .= trim($line, '"');
            } elseif ($line[0] === '"' && $inMsgstr) {
                $currentMsgstr .= trim($line, '"');
            }
        }

        $flush();

        return $entries;
    }

    /**
     * Generate all MO files from PO files in languages directory
     */
    public static function generateAllMoFiles(): array
    {
        $languagesDir = SSEO_AI_CLIENT_PLUGIN_DIR . 'languages/';
        $results = [];

        if (!is_dir($languagesDir)) {
            return $results;
        }

        $poFiles = glob($languagesDir . '*.po');

        foreach ($poFiles as $poFile) {
            $moFile = str_replace('.po', '.mo', $poFile);
            $success = self::generateMoFile($poFile, $moFile);
            $results[basename($poFile)] = $success;
        }

        return $results;
    }
}
