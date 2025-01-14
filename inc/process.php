<?php

namespace Sphere\Debloat;

/**
 * Debloat processing setup for JS and CSS optimizations.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class Process
{
	/**
	 * Init happens too early around plugins_loaded
	 */
	public function init()
	{
		// Setup a few extra options.
		Plugin::options()->delay_css_type = 'interact';
		Plugin::options()->delay_js_type  = 'interact';

		// Setup at init but before template_redirect.
		add_action('init', [$this, 'setup']);
	}

	/**
	 * Setup filters for processing
	 * 
	 * @since 1.1.0
	 */
	public function setup()
	{
		if ($this->should_process()) {

			// Load integrations.
			$integrations = array_unique(array_merge(
				(array) Plugin::options()->integrations_js,
				(array) Plugin::options()->integrations_css
			));

			if ($integrations) {
				if (in_array('elementor', $integrations) && class_exists('\Elementor\Plugin', false)) {
					new Integrations\Elementor;
				}

				if (in_array('wpbakery', $integrations) && class_exists('Vc_Manager')) {
					new Integrations\Wpbakery;
				}
			}

			/**
			 * Process HTML for inline and local stylesheets.
			 * 
			 * wp_ob_end_flush_all() will take care of flushing it.
			 * 
			 * Note: Autoptimize starts at priority 2 so we use 3 to process BEFORE AO.
			 */
			add_action('template_redirect', function() {
				if (!apply_filters('debloat/should_process', true)) {
					return false;
				}

				// Can't go in should_process() as that's too early.
				if (function_exists('\amp_is_request') && \amp_is_request()) {
					return false;
				}

				// Shouldn't process feeds, embeds (iframe), or robots.txt request.
				if (\is_feed() || \is_embed() || \is_robots()) {
					return false;
				}

				ob_start([$this, 'process_markup']);
			}, -999);

			// DEBUG: Devs if your output is disappearing - which you need for debugging,
			// uncomment below and comment the init action above.
			// add_action('template_redirect', function() { ob_start(); }, -999);
			// add_action('shutdown', function() {
			// 	$content = ob_get_clean();
			// 	echo $this->process_markup($content);
			// }, -10);
		}
	}

	/**
	 * Process DOM Markup provided with the html.
	 * 
	 * @param  string $html
	 * @return string
	 */
	public function process_markup($html)
	{
		do_action('debloat/process_markup', $this);

		if (!$this->is_valid_markup($html)) {
			return $html;
		}

		$dom = null;
		
		if ($this->should_optimize_css()) {
			$dom      = $this->get_dom($html);
			$optimize = new OptimizeCss($dom, $html);
			$html     = $optimize->process();
		}

		if ($this->should_optimize_js()) {
			$optimize_js = new OptimizeJs($html);
			$html = $optimize_js->process();
		}

		// Add delay load JS and extras as needed.
		$html = Plugin::delay_load()->render($html);

		// Failed at processing DOM, return original.
		if (!$dom) {
			return $html;
		}

		return $html;
	}

	public function is_valid_markup($html)
	{
		if (stripos($html, '<html') === false) {
			return false;
		}

		return true;
	}

	/**
	 * Get DOM object for the provided HTML.
	 *
	 * @param string $html
	 * @return boolean|\DOMDocument
	 */
	protected function get_dom($html)
	{
		if (!$html) {
			return false;
		}

		$libxml_previous = libxml_use_internal_errors(true);
		$dom    = new \DOMDocument();
		$result = $dom->loadHTML($html);

		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous);

		// Deprecating xpath querying.
		// if ($result) {
			// $dom->xpath = new \DOMXPath($dom);
		// }

		return $result ? $dom : false;
	}

	/**
	 * Should any processing be done at all.
	 * 
	 * @return boolean
	 */
	public function should_process()
	{
		if (is_admin()) {
			return false;
		}

		if (function_exists('is_customize_preview') && is_customize_preview()) {
			return false;
		}

		if (isset($_GET['nodebloat'])) {
			return false;
		}

		if (Util\is_elementor()) {
			return false;
		}

		// WPBakery Page Builder. vc_is_page_editable() isn't reliable at all hooks.
        if (!empty($_GET['vc_editable'])) {
			return false;
        }

		if (Plugin::options()->disable_for_admins && current_user_can('manage_options')) {
			return false;
		}

		return true;
	}

	/**
	 * Should the JS be optimized.
	 *
	 * @return boolean
	 */
	public function should_optimize_js()
	{
		$valid = true;
		return apply_filters('debloat/should_optimize_js', $valid);
	}

	/**
	 * Should the CSS be optimized.
	 *
	 * @return boolean
	 */
	public function should_optimize_css()
	{
		$valid = Plugin::options()->remove_css || Plugin::options()->optimize_css;
		return apply_filters('debloat/should_optimize_css', $valid);
	}

	/**
	 * Conditions test to see if current page matches in the provided valid conditions.
	 *
	 * @param array $enable_on
	 * @return boolean
	 */
	public function check_enabled(array $enable_on)
	{
		if (in_array('all', $enable_on)) {
			return true;
		}

		$conditions = [
			'pages'      => 'is_page',
			'posts'      => 'is_single',
			'archives'   => 'is_archive',
			'archive'    => 'is_archive', // Alias
			'categories' => 'is_category',
			'tags'       => 'is_tag',
			'search'     => 'is_search',
			'404'        => 'is_404',
			'home' => function() {
				return is_home() || is_front_page();
			},
		];

		$satisfy = false;
		foreach ($enable_on as $key) {
			if (!isset($conditions[$key]) || !is_callable($conditions[$key])) {
				continue;
			}

			$satisfy = call_user_func($conditions[$key]);
			
			// Stop going further in loop once satisfied.
			if ($satisfy) {
				break;
			}
		}

		return $satisfy;
	}
}