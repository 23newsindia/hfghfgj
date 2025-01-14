<?php

namespace Sphere\Debloat;

/**
 * The Plugin Bootstrap and Setup.
 * 
 * Dual acts as a container and a facade.
 * 
 * @author  asadkn
 * @since   1.0.0
 * 
 * @method static FileSystem  file_system()  Extends \WP_Filesystem_Base
 * @method static Process process()
 * @method static Options    options()
 * @method static DelayLoad  delay_load()
 * @method static FileCache  file_cache()
 * @method static OptimizeCss\GoogleFonts google_fonts()
 */
class Plugin 
{
	/**
	 * Plugin version
	 */
	const VERSION = '1.2.8';

	public static $instance;

	/**
	 * @var string Can be 'dev' for debgging.
	 */
	public $env = 'production';

	/**
	 * Path to plugin folder, trailing slashed.
	 */
	public $dir_path;
	public $dir_url;

	public $plugin_file;

	/**
	 * A pseudo service container
	 */
	public $container;

	/**
	 * Set it hooks on init.
	 */
	public function init() 
	{
		$this->dir_path = plugin_dir_path($this->plugin_file);
		$this->dir_url  = plugin_dir_url($this->plugin_file);

		// Fire up the main autoloader.
		require_once $this->dir_path . 'inc/autoloader.php';
		new Autoloader([
			'Sphere\Debloat\\'  => $this->dir_path . 'inc', 
		]);

		// Composer autoloader.
		require_once $this->dir_path . 'vendor/autoload.php';

		/**
		 * Setup and init common requires.
		 */
		// Options object with bound constructor args.
		$this->container['options'] = $this->shared(
			__NAMESPACE__ . '\Options', 
			[
				['debloat_options', 'debloat_options_js', 'debloat_options_general']
			]
		);

		// The process handler.
		$this->container['process'] = $this->shared(__NAMESPACE__ . '\Process');

		// File system with lazy init singleton.
		$this->container['file_system'] = $this->shared(__NAMESPACE__ . '\FileSystem');

		// Delay Load assets.
		$this->container['delay_load'] = $this->shared(__NAMESPACE__ . '\DelayLoad');

		// File cache handler.
		$this->container['file_cache'] = $this->shared(__NAMESPACE__ . '\FileCache');

		// Google Fonts is a singleton.
		$this->container['google_fonts'] = $this->shared(__NAMESPACE__ . '\OptimizeCss\GoogleFonts');
		
		/**
		 * Admin only requires
		 * 
		 * We load these only on Admin side to keep things lean and performant.
		 * 
		 * Note on CMB2: 
		 *  It's used ONLY as an admin side dependency and never even
		 *  loaded on the frontend. Use native WP options API on front.
		 */
		if (is_admin() || defined('WP_CLI')) {

			$admin = new Admin;
			$admin->init();

			// We don't want CMB2 backend loaded at all in AJAX requests (admin-ajax.php passes is_admin() test)
			if (!wp_doing_ajax()) {
				require_once $this->dir_path . 'vendor/cmb2/init.php';
			}

			// Path bug fix for cmb2 in composer
			add_filter('cmb2_meta_box_url', function() {
				return self::get_instance()->dir_url . 'vendor/cmb2';
			});

			// WP-CLI commands.
			if (class_exists('\WP_CLI')) {
				\WP_CLI::add_command('debloat', '\Sphere\Debloat\WpCli\Commands');
			}
		}

		// Utility functions.
		require_once $this->dir_path . 'inc/util.php';

		$this->register_hooks();

		// Load the options.
		self::options()->init();

		// Init process to setup hooks.
		self::process()->init();
	}

	/**
	 * Creates a single instance class for container.
	 * 
	 * @param string     $class  Fully-qualifed class name
	 * @param array|null $args   Bound args to pass to constructor
	 */
	public function shared($class, $args = null) 
	{
		return function($fresh = false) use ($class, $args) {
			static $object;

			if (!$object || $fresh) {

				if (!$args) {
					$object = new $class;
				}
				else {
					$ref = new \ReflectionClass($class);
					$object = $ref->newInstanceArgs($args);
				}
			}

			return $object;
		};
	}

	/**
	 * Setup hooks actions.
	 */
	public function register_hooks()
	{
		// Translations
		add_action('plugins_loaded', array($this, 'load_textdomain'));
	}

	/**
	 * Setup translations.
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'debloat',
			false,
			basename($this->dir_path) . '/languages'
		);
	}

	/**
	 * Gets an object from container.
	 */
	public function get($name, $args = array()) 
	{
		if (!isset($this->container[$name])) {
			throw new \Exception("No container exists with key '{$name}'");
		}

		$object = $this->container[$name];

		if (is_callable($object)) {
			return call_user_func_array($object, $args);
		}
		else if (is_string($object)) {
			$object = new $object;
		}

		return $object;
	}

	/**
	 * @uses self::get()
	 */
	public static function __callStatic($name, $args = array())
	{
		return self::get_instance()->get($name, $args);
	}

	/**
	 * @return $this
	 */
	public static function get_instance()
	{
		if (self::$instance == null) {
			self::$instance = new static();
		}

		return self::$instance;
	}
}