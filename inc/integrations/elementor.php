<?php

namespace Sphere\Debloat\Integrations;

use Sphere\Debloat\Plugin;

/**
 * Rules specific to Elementor plugin.
 */
class Elementor 
{
	public $allow_selectors;
	
	public function __construct()
	{
		$this->register_hooks();
	}

	public function register_hooks()
	{
		add_action('wp', [$this, 'setup']);
	}

	public function setup()
	{
		if (in_array('elementor', Plugin::options()->integrations_css)) {
			$this->setup_remove_css();
		}

		if (in_array('elementor', Plugin::options()->integrations_js)) {
			$this->setup_delay_js();
		}
	}

	/**
	 * Special rules related to remove css when Elementor is active.
	 *
	 * @return void
	 */
	public function setup_remove_css()
	{
		// Add all Elementor frontend files for CSS processing.
		add_filter('debloat/remove_css_includes', function($include) {

			$include[] = 'id:elementor-frontend-css';
			$include[] = 'id:elementor-pro-css';
			// $include[] = 'elementor/*font-awesome';
			return $include;
		});

		add_filter('debloat/remove_css_excludes', function($exclude, \Sphere\Debloat\RemoveCss $remove_css) {

			// Don't bother with animations CSS file as it won't remove much.
			if (!empty($remove_css->used_markup['classes']['elementor-invisible'])) {
				$exclude[] = 'id:elementor-animations';
			}

			return $exclude;
		}, 10, 2);

		/**
		 * Elementor selectors extras.
		 */
		$this->allow_selectors = [
			[
				'type'   => 'any',
				'sheet'  => 'id:elementor-',
				'search' => [
					'*.e--ua-*',
					'.elementor-loading',
					'.elementor-invisible',
					'.elementor-background-video-embed',
				]
			],
			[
				'type'  => 'class',
				'class' => 'elementor-invisible',
				'sheet' => 'id:elementor-',
				'search' => [
					'.animated'
				]
			],
			[
				'type'  => 'class',
				'class' => 'elementor-invisible',
				'sheet' => 'id:elementor-',
				'search' => [
					'.animated'
				]
			],
		];
		
		if (is_user_logged_in()) {
			$this->allow_selectors[] = [
				'type'  => 'any',
				'sheet' => 'id:elementor-',
				'search' => [
					'#wp-admin-bar*',
					'*#wpadminbar*',
				]
			];
		}

		if (defined('ELEMENTOR_PRO_VERSION')) {
			$this->allow_selectors = array_merge($this->allow_selectors, [
				[
					'type'  => 'class',
					'class' => 'elementor-posts-container',
					// 'sheet' => 'id:elementor-',
					'search' => [
						'.elementor-posts-container',
						'.elementor-has-item-ratio'
					]
				],
			]);
		}

		add_filter('debloat/allow_css_selectors', function($allow, \Sphere\Debloat\RemoveCss $remove_css) {

			$html = $remove_css->html;
			if (strpos($html, 'background_slideshow_gallery') !== false) {
				array_push($this->allow_selectors, ...[
					[
						'type'   => 'any',
						'sheet'  => 'id:elementor-',
						'search' => [
							'*.swiper-*',
							'*.elementor-background-slideshow*',
							'.elementor-ken-burns*',
						]
					],
				]);
			}

			return array_merge($allow, $this->allow_selectors);
		}, 10, 2);
	}

	/**
	 * Special rules related to Delay JS when Elementor is active.
	 * 
	 * @return void
	 */
	public function setup_delay_js()
	{
		add_filter('debloat/delay_js_includes', function($include) {
			$include[] = 'url:elementor/*';
		
			// Admin bar should also be delayed as elementor creates admin bar items later
			// and the events won't register.
			$include[] = 'wp-includes/js/admin-bar*.js';

			return $include;
		});
	}
}