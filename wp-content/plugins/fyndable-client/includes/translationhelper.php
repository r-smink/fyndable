<?php

namespace SSEOAIClient;

/**
 * Translation Helper
 * Generates .mo files from .po files
 */
class TranslationHelper
{
    /**
     * Generate MO file from PO file
     */
    public static function generateMoFile(string $poFile, string $moFile): bool
    {
        if (!file_exists($poFile)) {
            return false;
        }
        
        $poContent = file_get_contents($poFile);
        
        // Parse PO file
        $entries = [];
        $currentMsgid = '';
        $currentMsgstr = '';
        $inMsgid = false;
        $inMsgstr = false;
        
        $lines = explode("\n", $poContent);
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || $line[0] === '#') {
                // Save previous entry
                if ($currentMsgid !== '' && $currentMsgstr !== '') {
                    $entries[stripslashes($currentMsgid)] = stripslashes($currentMsgstr);
                }
                $currentMsgid = '';
                $currentMsgstr = '';
                $inMsgid = false;
                $inMsgstr = false;
                continue;
            }
            
            if (strpos($line, 'msgid ') === 0) {
                if ($currentMsgid !== '' && $currentMsgstr !== '') {
                    $entries[$currentMsgid] = $currentMsgstr;
                }
                $currentMsgid = substr($line, 6);
                $currentMsgid = trim($currentMsgid, '"');
                $currentMsgstr = '';
                $inMsgid = true;
                $inMsgstr = false;
            } elseif (strpos($line, 'msgstr ') === 0) {
                $currentMsgstr = substr($line, 7);
                $currentMsgstr = trim($currentMsgstr, '"');
                $inMsgid = false;
                $inMsgstr = true;
            } elseif ($line[0] === '"' && $inMsgid) {
                $currentMsgid .= trim($line, '"');
            } elseif ($line[0] === '"' && $inMsgstr) {
                $currentMsgstr .= trim($line, '"');
            }
        }
        
        // Save last entry
        if ($currentMsgid !== '' && $currentMsgstr !== '') {
            $entries[stripslashes($currentMsgid)] = stripslashes($currentMsgstr);
        }
        
        // Remove empty msgid (header)
        unset($entries['']);
        
        // Create MO file
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
            // Original string table entry
            $origTable .= pack('L', strlen($msgid));
            $origTable .= pack('L', $offset);
            $originals .= $msgid . "\0";
            $offset += strlen($msgid) + 1;
        }
        
        foreach ($entries as $msgid => $msgstr) {
            // Translation string table entry
            $transTable .= pack('L', strlen($msgstr));
            $transTable .= pack('L', $offset);
            $translations .= $msgstr . "\0";
            $offset += strlen($msgstr) + 1;
        }
        
        $mo .= $origTable . $transTable . $originals . $translations;
        
        return file_put_contents($moFile, $mo) !== false;
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
