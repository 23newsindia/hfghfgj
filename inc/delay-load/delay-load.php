<?php

namespace Sphere\Debloat;

use Sphere\Debloat\OptimizeCss\Stylesheet;
use Sphere\Debloat\OptimizeJs\Script;

/**
 * Delay load assets singleton.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class DelayLoad
{
	public $enabled = true;
	public $use_js = false;
	public $js_type;

	public $preload = [];
	
	/**
	 * Note: Should be used a singleton.
	 */
	public function __construct()
	{
		$this->register_hooks();
	}

	public function register_hooks()
	{
		// add_action('wp_enqueue_scripts', [$this, 'register_assets']);
	}

	/**
	 * Enable injection of required scripts and preloads, as needed. 
	 *
	 * @param null|true $use_js Whether to inject delay/defer JS. null keeps the current.
	 * @return void
	 */
	public function enable($use_js = null)
	{
		$this->enabled = true;

        if ($use_js === true) {
            $this->use_js = $use_js;
        }
	}

	public function disable()
	{
		$this->enabled = false;
	}

	/**
	 * Enqueue a preload.
	 *
	 * @param Stylesheet|Script $asset
	 * @return void
	 */
	public function add_preload($asset)
	{
		$this->preload[] = $asset;
	}

	/**
	 * Add extras to the provided HTML buffer.
	 *
	 * @param string $html
	 * @return string
	 */
	public function render($html)
	{
		if (!$this->enabled) {
			return $html;
		}

		$js = $this->render_js();
		$preloads = $this->render_preloads();

		$append = $preloads . $js;

		// Add to body or at the end.
		// Note: Has to be at the end to ensure all <script> tags with defer has been added.
		if (strpos($html, '</body>') !== false) {
			$html = str_replace('</body>', $append . "\n</body>", $html);
		} else {
			$html .=  $append;
		}


		return $html;
	}

	protected function render_preloads()
	{
		$preloads = [];
		foreach ($this->preload as $preload) {
			$media = 'all';
			$type  = ($preload instanceof Stylesheet) ? 'style' : 'script';

			if ($type === 'style') {
				$media = $preload->media ?: $media;
			}

			$preloads[] = sprintf(
				'<link rel="prefetch" href="%1$s" as="%2$s" media="%3$s" />',
				esc_url($preload->get_content_url()),
				$type,
				esc_attr($media)
			);
		}

		return implode('', $preloads);
	}

	/**
	 * Get JS enqueues and inline scripts as needed.
	 *
	 * @return string
	 */
	protected function render_js()
	{
		if (!$this->use_js) {
			return '';
		}

		$js  = '';
		$min = Plugin::get_instance()->env !== 'dev' ? '.min' : '';

		// Defer JS is always inline.
		$js .= sprintf(
			'<script data-cfasync="false">%s</script>',
			Plugin::file_system()->get_contents(
				Plugin::get_instance()->dir_path . 'inc/delay-load/js/defer-load'. $min .'.js'
			)
		);

		// Delay load JS comes after, just in case, to not mess up readyState.
		if ($this->js_type === 'inline') {
			$js .= sprintf(
				'<script data-cfasync="false">%s</script>',
				Plugin::file_system()->get_contents(
					Plugin::get_instance()->dir_path . 'inc/delay-load/js/delay-load'. $min .'.js'
				)
			);

		} else {

			$js .= sprintf(
				'<script type="text/javascript" src="%s" data-cfasync="false"></script>',
				esc_url(Plugin::get_instance()->dir_url . 'inc/delay-load/js/delay-load'. $min .'.js?ver=' . Plugin::VERSION)
			);
		}

		if (!$js) {
			return '';
		}

		/**
		 * Add configs.
		 */
		$configs = [
			'cssDelayType' => Plugin::options()->delay_css_type,
			'jsDelayType'  => Plugin::options()->delay_js_type,
			'jsDelayMax'   => Plugin::options()->delay_js_max
		];

		$js = sprintf(
			'<script>var debloatConfig = %1$s;</script>%2$s',
			json_encode($configs),
			$js
		);

		return $js;
	}
	
	/**
	 * Check for conditionals for delay load.
	 *
	 * @return boolean
	 */
	public function should_delay_css()
	{
		if (!Plugin::options()->delay_css_load) {
			return false;
		}

		$valid = Plugin::process()->check_enabled(Plugin::options()->delay_css_on);

		return apply_filters('debloat/should_delay_css', $valid);
	}
}