<?php
/**
 * Plugin Name: Fyndable Voorwaarden
 * Description: Toont de Algemene Voorwaarden (NL/EN, met taalknop) via de shortcode [fyndable_voorwaarden]. Starttaal: [fyndable_voorwaarden lang="en"].
 * Version: 1.2.0
 * Author: Fyndable
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('fyndable_voorwaarden', 'fyndable_voorwaarden_shortcode');

function fyndable_voorwaarden_shortcode($atts = []) {
    $atts = shortcode_atts(['lang' => ''], $atts, 'fyndable_voorwaarden');
    $lang = strtolower((string) $atts['lang']);
    if (!in_array($lang, ['nl', 'en'], true)) {
        // Geen taal opgegeven: volg de sitetaal (nl_* = Nederlands, anders Engels).
        $lang = strpos(determine_locale(), 'nl') === 0 ? 'nl' : 'en';
    }

    $html_path = plugin_dir_path(__FILE__) . 'Algemene_Voorwaarden_Fyndable.html';

    if (!file_exists($html_path)) {
        return 'Algemene Voorwaarden bestand niet gevonden.';
    }

    $html = file_get_contents($html_path);

    if ($html === false) {
        return 'Algemene Voorwaarden konden niet worden geladen.';
    }

    // Logo uit de pluginmap (relatieve src werkt niet in een srcdoc-iframe).
    $html = str_replace('src="logo_long_black.png"', 'src="' . esc_url(plugins_url('logo_long_black.png', __FILE__)) . '"', $html);
    $html = str_replace('<html lang="nl" data-lang="nl">', '<html lang="' . $lang . '" data-lang="' . $lang . '">', $html);

    $nav_click_script = '<script>
      document.querySelectorAll(".toc a").forEach(function(link) {
        link.addEventListener("click", function(e) {
          e.preventDefault();
          var target = document.querySelector(this.getAttribute("href"));
          if (target) target.scrollIntoView({ behavior: "smooth" });
        });
      });
    </script>';

    $html = str_replace('</body>', $nav_click_script . '</body>', $html);

    return '<iframe srcdoc="' . esc_attr($html) . '" title="' . esc_attr($lang === 'en' ? 'Terms and Conditions Fyndable' : 'Algemene Voorwaarden Fyndable') . '" style="width:100%;height:100vh;border:none;display:block;" loading="lazy" sandbox="allow-scripts"></iframe>';
}
