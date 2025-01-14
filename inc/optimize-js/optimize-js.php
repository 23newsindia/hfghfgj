<?php

namespace Sphere\Debloat;
use Sphere\Debloat\OptimizeJs\Script;

/**
 * JS Optimizations such as defer and delay.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class OptimizeJs
{
	protected $html;

	/**
	 * @var array
	 */
	protected $scripts = [];

	/**
	 * Include and exclude scripts for "delay".
	 *
	 * @var array
	 */
	protected $include_scripts = [];
	protected $exclude_scripts = [];

	/**
	 * Exclude scripts from defer.
	 */
	protected $exclude_defer = [];

	/**
	 * Scripts already delayed or deffered.
	 */
	protected $done_delay  = [];
	protected $done_defer = [];

	/**
	 * Scripts registered data from core.
	 */
	protected $enqueues = [];

	public function __construct(string $html)
	{
		$this->html = $html;
	}

	public function process()
	{
		$this->find_scripts();

		// Figure out valid scripts.
		$this->setup_valid_scripts();

		do_action('debloat/optimize_js_begin', $this);

		// Early First pass to setup valid parents for child dependency checks.
		array_map(function($script) {
			$this->should_defer_script($script);
		}, $this->scripts);

		/**
		 * Setup scripts defer and delays and render the replacements.
		 */
		$has_delayed = false;
		foreach ($this->scripts as $script) {

			// Has to be done for all scripts.
			if (Plugin::options()->minify_js) {
				$this->minify_js($script);
				$script->render = true;
			}

			// Defer script if not already deferred.
			if ($this->should_defer_script($script)) {
				$script->defer  = true;
				$script->render = true;

				$has_delayed = true;
			}

			// Should this script be delayed.
			if ($this->should_delay_script($script)) {
				$script->delay = true;
				$script->render = true;

				$has_delayed = true;
			}
			else {
				Util\debug_log('Skipping: ' . print_r($script, true));
			}

			if ($script->render) {
				$this->html = str_replace($script->orig_html, $script->render(), $this->html);
			}
		}

		if ($has_delayed) {
			Plugin::delay_load()->enable(true);
		}
		
		return $this->html;
	}

	public function find_scripts()
	{
		/**
		 * Collect all valid enqueues.
		 */
		$all_scripts  = [];
		foreach (wp_scripts()->registered as $handle => $script) {

			// Not an enqueued script, ignore.
			if (!in_array($handle, wp_scripts()->done)) {
				continue;
			}

			// Don't mutate original.
			$script = clone $script;

			$script->deps = array_map(function($id) {
				// jQuery JS should be mapped to core. Add -js suffix for others.
				return $id === 'jquery' ? 'jquery-core-js' : $id . '-js';
			}, $script->deps);

			// Add -js prefix to match the IDs that will retrieved from attrs.
			$handle .= '-js';
			$all_scripts[$handle] = $script;

			// Pseudo entry for extras, adding a dependency.
			if (isset($script->extra['after'])) {
				$all_scripts[$handle . '-after'] = (object) [
					'deps' => [$handle]
				];
			}
		}

		$this->enqueues = $all_scripts;

		/**
		 * Find all scripts.
		 */
		if (!preg_match_all('#<script.*?</script>#si', $this->html, $matches)) {
			return;
		}

		foreach ($matches[0] as $script) {
			$script = Script::from_tag($script);
			if ($script) {
				$script->deps = $this->enqueues[$script->id]->deps ?? [];

				// Note: There can be multiple scripts with same URL or id. So we don't use $script->id.
				$this->scripts[] = $script;

				// Inline script has jQuery content? Ensure dependency.
				if (
					!$script->url && 
					!in_array('jquery-core-js', $script->deps) && 
					strpos($script->content, 'jQuery') !== false
				) {
					$script->deps[] = 'jquery-core-js';
				}
			}
		}
	}

	/**
	 * Check if the script has a parent dependency that is delayed/deferred.
	 *
	 * @param Script $script
	 * @param array $valid
	 * @return boolean
	 */
	public function check_dependency(Script $script, array $valid)
	{
		// Check if one of parent dependencies is delayed/deferred.
		foreach ((array) $script->deps as $dep) {
			if (isset($valid[$dep])) {
				return true;
			}
		}

		// For translations, if wp-i18n-js is valid, so should be translations.
		if (preg_match('/-js-translations/', $script->id, $matches)) {
			if (isset($valid['wp-i18n-js'])) {
				return true;
			}
		}

		// Special case: Inline script with a parent dep. If parent is true, so is child.
		// Note: 'before' and 'extra' isn't accounted for and is not usually needed. Since 'extra' is 
		// usually localization and 'before' has to happen before anyways.
		if (preg_match('/(.+?-js)-(before|after)/', $script->id, $matches)) {
			$handle = $matches[1];

			// Parent was valid, so is the current child.
			if (isset($valid[$handle])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Should the script be delayed using one of the JS delay methods.
	 *
	 * @param Script $script
	 * @return boolean
	 */
	public function should_delay_script(Script $script)
	{
		if (!Plugin::options()->delay_js) {
			return false;
		}

		// Excludes should be handled before includes and parents check.
		foreach ($this->exclude_scripts as $exclude) {
			if (Util\asset_match($exclude, $script, 'orig_html')) {
				return false;
			}
		}

		// Delay all.
		if (Plugin::options()->delay_js_all) {
			return true;
		}

		if ($this->check_dependency($script, $this->done_delay)) {
			$this->done_delay[$script->id] = true;
			return true;
		}

		foreach ($this->include_scripts as $include) {
			if (Util\asset_match($include, $script, 'orig_html')) {
				$this->done_delay[$script->id] = true;
				return true;
			}
		}

		return false;
	}

	/**
	 * Should the script be deferred.
	 *
	 * @param Script $script
	 * @return boolean
	 */
	public function should_defer_script(Script $script) 
	{
		// Defer not enabled.
		if (!Plugin::options()->defer_js) {
			return false;
		}

		foreach ($this->exclude_defer as $exclude) {
			if (Util\asset_match($exclude, $script, 'orig_html')) {
				return false;
			}
		}

		// For inline scripts: By default not deferred, unless child of a deferred. 
		if (!$script->url) {
			if (Plugin::options()->defer_js_inline) {
				return true;
			}

			if ($this->check_dependency($script, $this->done_defer)) {
				$this->done_defer[$script->id] = true;
				return true;
			}

			return false;
		}

		// If defer or async attr already exists on original script.
		if ($script->defer || $script->async) {
			return false;
		}

		$this->done_defer[$script->id] = true;
		return true;
	}

	/**
	 * Setup includes and excludes based on options.
	 *
	 * @return void
	 */
	public function setup_valid_scripts()
	{	
		// Used by both delay and defer.
		$shared_excludes = [
			// Lazyloads should generally be loaded as early as possible and not deferred.
			'lazysizes.js',
			'lazyload.js',
			'lazysizes.min.js',
			'lazyLoadOptions',
			'lazyLoadThumb',
			'BunyadLazy',
		];

		/**
		 * Defer scripts.
		 */
		$defer_excludes = array_merge(
			Util\option_to_array(Plugin::options()->defer_js_excludes),
			$shared_excludes
		);

		$defer_excludes[] = 'id:-js-extra';
		$this->exclude_defer = apply_filters('debloat/defer_js_excludes', $defer_excludes);

		/**
		 * Delayed load scripts.
		 */
		$excludes = Util\option_to_array(Plugin::options()->delay_js_excludes);
		$excludes = array_merge($excludes, $shared_excludes, [
			// Jetpack stats.
			'url://stats.wp.com',
			'_stq.push',

			// WPML browser redirect.
			'browser-redirect/app.js',

			// Skip -js-extra as it's  global variables and localization that shouldn't be delayed.
			'id:-js-extra'
		]);

		$this->exclude_scripts = apply_filters('debloat/delay_js_excludes', $excludes);

		$this->include_scripts = array_merge(
			$this->include_scripts,
			Util\option_to_array(Plugin::options()->delay_js_includes)
		);

		// Enable delay adsense.
		if (Plugin::options()->delay_js_adsense) {
			$this->include_scripts[] = 'adsbygoogle.js';
		}

		$this->include_scripts = apply_filters('debloat/delay_js_includes', $this->include_scripts);
	}

	/**
	 * Minify the JS file, if possible.
	 *
	 * @param Script $script
	 * @return void
	 */
	public function minify_js(Script $script)
	{
		$minifier = new Minifier($script);
		$minifier->process();
	}
}