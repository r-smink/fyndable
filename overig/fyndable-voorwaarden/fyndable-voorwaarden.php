<?php
/**
 * Plugin Name: Fyndable Voorwaarden
 * Description: Toont de Algemene Voorwaarden via de shortcode [fyndable_voorwaarden].
 * Version: 1.0.0
 * Author: Fyndable
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('fyndable_voorwaarden', 'fyndable_voorwaarden_shortcode');

function fyndable_voorwaarden_shortcode() {
    $html_path = plugin_dir_path(__FILE__) . 'Algemene_Voorwaarden_Fyndable.html';

    if (!file_exists($html_path)) {
        return 'Algemene Voorwaarden bestand niet gevonden.';
    }

    $html = file_get_contents($html_path);

    if ($html === false) {
        return 'Algemene Voorwaarden konden niet worden geladen.';
    }

    $logo_url = 'https://dev.fyndable.ai/wp-content/uploads/2026/08/FYN-LOGO-DIA-WHITE300ppi.png';
    $logo     = '<img class="brand-logo" src="' . esc_url($logo_url) . '" alt="Fyndable" style="height:40px;width:auto;display:block;">';

    $html = str_replace('<div class="brand-mark">F</div>', $logo, $html);

    $nav_click_script = '<script>
      document.querySelectorAll("#toc a").forEach(function(link) {
        link.addEventListener("click", function(e) {
          e.preventDefault();
          var target = document.querySelector(this.getAttribute("href"));
          if (target) target.scrollIntoView({ behavior: "smooth" });
        });
      });
    </script>';

    $html = str_replace('</body>', $nav_click_script . '</body>', $html);

    return '<iframe srcdoc="' . esc_attr($html) . '" title="Algemene Voorwaarden Fyndable" style="width:100%;height:100vh;border:none;display:block;" loading="lazy" sandbox="allow-scripts"></iframe>';
}
