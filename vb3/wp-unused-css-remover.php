<?php
/**
 * Plugin Name: WP Unused CSS Remover
 * Plugin URI: https://example.com/plugins/wp-unused-css-remover
 * Description: A WordPress plugin to remove unused CSS from your website
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-unused-css-remover
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_UNUSED_CSS_REMOVER_PATH', plugin_dir_path(__FILE__));
define('WP_UNUSED_CSS_REMOVER_URL', plugin_dir_url(__FILE__));

// Require Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load utility functions
require_once __DIR__ . '/src/Util/functions.php';

// Initialize the plugin
function wp_unused_css_remover_init() {
    // Initialize classes
    $plugin = new Sphere\Debloat\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'wp_unused_css_remover_init');

// Activation hook
register_activation_hook(__FILE__, 'wp_unused_css_remover_activate');
function wp_unused_css_remover_activate() {
    // Add activation code here
    flush_rewrite_rules();
}


// wp-unused-css-remover.php
add_action('plugins_loaded', function() {
    if (!is_admin()) {
        add_action('template_redirect', function() {
            $plugin = new Sphere\Debloat\Plugin();
            $plugin->init();
        }, 0);
    }
});






// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_unused_css_remover_deactivate');
function wp_unused_css_remover_deactivate() {
    // Add deactivation code here
    flush_rewrite_rules();
}
