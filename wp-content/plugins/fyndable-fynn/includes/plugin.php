<?php
/**
 * Main plugin class.
 *
 * @package Fynn
 */

namespace Fynn;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        new Settings();
        new Assistant();
        new Frontend();
    }
}
