<?php

namespace AISEOAssistant;

/**
 * Minimal PSR-4 like autoloader scoped to this plugin.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . strtolower(str_replace('\\', '/', $relative)) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});
