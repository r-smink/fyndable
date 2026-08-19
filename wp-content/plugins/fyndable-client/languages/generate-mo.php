<?php
/**
 * Simple PO to MO converter
 * Run this file to generate .mo files from .po files
 *
 * This is a standalone CLI fallback. Inside WordPress, the
 * TranslationHelper class uses WordPress's native POMO library
 * for reliable, spec-compliant MO generation.
 */

function po_to_mo($po_file, $mo_file) {
    $po_content = file_get_contents($po_file);

    // Parse PO file
    $entries = [];
    $current_msgid = '';
    $current_msgstr = '';
    $in_msgid = false;
    $in_msgstr = false;

    $flush = function () use (&$entries, &$current_msgid, &$current_msgstr, &$in_msgid, &$in_msgstr) {
        if ($current_msgid !== '' || $current_msgstr !== '') {
            $key = stripslashes($current_msgid);
            $val = stripslashes($current_msgstr);
            if ($key !== '') {
                $entries[$key] = $val;
            }
        }
        $current_msgid = '';
        $current_msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
    };

    // Normalize line endings
    $lines = explode("\n", str_replace("\r\n", "\n", $po_content));
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            $flush();
            continue;
        }

        if (strpos($line, 'msgid ') === 0) {
            $flush();
            $current_msgid = trim(substr($line, 6), '"');
            $in_msgid = true;
            $in_msgstr = false;
        } elseif (strpos($line, 'msgstr ') === 0) {
            $current_msgstr = trim(substr($line, 7), '"');
            $in_msgid = false;
            $in_msgstr = true;
        } elseif ($line[0] === '"' && $in_msgid) {
            $current_msgid .= trim($line, '"');
        } elseif ($line[0] === '"' && $in_msgstr) {
            $current_msgstr .= trim($line, '"');
        }
    }

    $flush();

    // Remove empty msgid (header)
    unset($entries['']);

    // Create MO file
    $mo = '';

    // Magic number
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

    $orig_table = '';
    $trans_table = '';

    foreach ($entries as $msgid => $msgstr) {
        // Original string table entry
        $orig_table .= pack('L', strlen($msgid));
        $orig_table .= pack('L', $offset);
        $originals .= $msgid . "\0";
        $offset += strlen($msgid) + 1;
    }

    foreach ($entries as $msgid => $msgstr) {
        // Translation string table entry
        $trans_table .= pack('L', strlen($msgstr));
        $trans_table .= pack('L', $offset);
        $translations .= $msgstr . "\0";
        $offset += strlen($msgstr) + 1;
    }

    $mo .= $orig_table . $trans_table . $originals . $translations;

    file_put_contents($mo_file, $mo);

    return true;
}

// Generate MO file
$po_file = __DIR__ . '/ai-seo-client-nl_NL.po';
$mo_file = __DIR__ . '/ai-seo-client-nl_NL.mo';

if (file_exists($po_file)) {
    po_to_mo($po_file, $mo_file);
    echo "Generated: $mo_file\n";
} else {
    echo "PO file not found: $po_file\n";
}
