<?php
/**
 * Plugin bootstrap
 */
defined('WPINC') || exit;

if (version_compare(phpversion(), '7.1', '<')) {
	/**
	 * Display an admin error notice when PHP is older the version 7.1
	 * Hook it to the 'admin_notices' action.
	 */
	function debloat_old_php_admin_error_notice() {
		
		$message = sprintf(esc_html__(
			'The %2$sDebloat%3$s plugin requires %2$sPHP 7.1+%3$s to run properly. Please contact your web hosting company and ask them to update the PHP version of your site.%4$s Your current version of PHP has reached end-of-life is %2$shighly insecure: %1$s%3$s', 'debloat'), 
			phpversion(), 
			'<strong>', 
			'</strong>', 
			'<br>'
		);

		printf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post($message));
	}
	
	add_action('admin_notices', 'debloat_old_php_admin_error_notice');
	
	// bail
	return;
}

/**
 * Launch the plugin
 */
require_once plugin_dir_path(__FILE__) . 'inc/plugin.php';

$plugin = \Sphere\Debloat\Plugin::get_instance();
$plugin->plugin_file = __FILE__;

// Init on plugins loaded
add_action('plugins_loaded', array($plugin, 'init'));

/**
 * Register activation and deactivation hooks
 */

register_activation_hook(DEBLOAT_PLUGIN_FILE, function() {
	// Noop
});

register_deactivation_hook(DEBLOAT_PLUGIN_FILE, function() {
	// Noop
});