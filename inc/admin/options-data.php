<?php

namespace Sphere\Debloat\Admin;

/**
 * Options data.
 */
class OptionsData
{
	/**
	 * Common shared data and options.
	 *
	 * @param string $key
	 * @return array
	 */
	public static function get_common($key = '')
	{
		$_common = [];
		$_common['enable_on'] = [
			'all'         => esc_html__('All Pages', 'debloat'),
			'single'      => esc_html__('Single Post/Article', 'debloat'),
			'pages'       => esc_html__('Pages', 'delobat'),
			'home'        => esc_html__('Homepage', 'delobat'),
			'archives'    => esc_html__('Archives', 'delobat'),
			'categories'  => esc_html__('Categories', 'delobat'),
			'search'      => esc_html__('Search', 'delobat'),
		];

		return $key ? $_common[$key] : $_common;
	}

	public static function get_css()
	{
		$options = [];
		$options[] = [
			'name'    => esc_html__('Optimize CSS', 'debloat'),
			// 'description' => 'foo',
			'type'    => 'title',
			'id'      => '_optimize_css',
		];

		$options[] = [
			'id'      => 'optimize_css',
			'name'    => esc_html__('Fix Render-Blocking CSS', 'debloat'),
			'desc'    => esc_html__('Enable CSS Optimizations to fix Render-blocking CSS.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
		];
		$options[] = [
			'id'      => 'optimize_css_to_inline',
			'name'    => esc_html__('Inline Optimized CSS', 'debloat'),
			'desc'    => esc_html__('Inline the CSS to prevent flash of unstyled content. Highly recommended.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => ['data-conditional-id' => 'optimize_css'],
		];
		$options[] = [
			'id'      => 'optimize_gfonts_inline',
			'name'    => esc_html__('Inline Google Fonts CSS', 'debloat'),
			'desc'    => esc_html__('Inline the Google Fonts CSS for a big boost on FCP and slight on LCP on mobile. Highly recommended.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => ['data-conditional-id' => 'optimize_css'],
		];
		$options[] = [
			'id'      => 'optimize_css_minify',
			'name'    => esc_html__('Minify CSS', 'debloat'),
			'desc'    => esc_html__('Minify CSS to reduced the CSS size.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => ['data-conditional-id' => 'optimize_css'],
		];
		$options[] = [
			'id'      => 'optimize_css_excludes',
			'name'    => esc_html__('Exclude Styles', 'debloat'),
			'desc'    =>
				esc_html__('Enter one per line to exclude certain CSS files from this optimizations. Examples:', 'debloat')
				. ' <code>id:my-css-id</code>
				<br /><code>wp-content/themes/my-theme/style.css</code>
				<br /><code>wp-content/themes/my-theme*</code>
				',
			'type'    => 'textarea_small',
			'default' => '',
			'attributes' => ['data-conditional-id' => 'optimize_css'],
		];
		$options[] = [
			'id'      => 'integrations_css',
			'name'    => esc_html__('Enable Plugin Integrations', 'debloat'),
			'desc'    => esc_html__('Special pre-made rules for CSS, specific to plugins, are applied if enabled.', 'debloat'),
			'type'    => 'multicheck_inline',
			'options' => [
				'elementor' => 'Elementor',
				'wpbakery'  => 'WPBakery Page Builder',
			],
			'default'    => ['elementor', 'wpbakery'],
			'select_all_button' => false,
		];

		$options[] = [
			'id'      => 'optimize_gfonts',
			'name'    => esc_html__('Optimize Google Fonts', 'debloat'),
			'desc'    => esc_html__('Add preconnect hints and add display swap for Google Fonts.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 1,
		];

		$options[] = [
			'name'    => esc_html__('Optimize CSS: Remove Unused', 'debloat'),
			// 'description' => 'foo',
			'type'    => 'title',
			'id'      => '_remove_unused',
		];

		$options[] = [
			'id'      => 'remove_css',
			'name'    => esc_html__('Remove Unused CSS', 'debloat'),
			'desc'    => esc_html__('This is an expensive process. DO NOT use without a cache plugin.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
		];

		$options[] = [
			'id'      => 'remove_css_all',
			'name'    => esc_html__('Remove from All Stylesheets', 'debloat'),
			'desc'    => esc_html__('WARNING: Only use if you are sure your plugins and themes dont add classes using JS. May also be enabled when delay loading all the original CSS.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'      => 'remove_css_plugins',
			'name'    => esc_html__('Enable for Plugins CSS', 'debloat'),
			'desc'    => esc_html__('Removed unused CSS on all plugins CSS files.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => [
				'data-conditional-id' => [
					['key' => 'remove_css'],
					['key' => 'remove_css_all', 'value' => 'off'],
				]
			],
		];

		$options[] = [
			'id'      => 'remove_css_theme',
			'name'    => esc_html__('Enable for Theme CSS', 'debloat'),
			'desc'    => esc_html__('Removed unused CSS from all theme CSS files.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => [
				'data-conditional-id' => [
					['key' => 'remove_css'],
					['key' => 'remove_css_all', 'value' => 'off'],
				]
			],
		];

		$options[] = [
			'id'      => 'remove_css_includes',
			'name'    => esc_html__('Target Stylesheets', 'debloat'),
			'desc'    =>
				esc_html__('Will remove unused CSS from these targets. You may use an ID or the part of the URL. Examples:', 'debloat')
				. ' <code>id:my-css-id</code>
				<br /><code>wp-content/themes/my-theme/style.css</code>
				<br /><code>wp-content/themes/my-theme*</code>: All theme stylesheets.
				<br /><code>plugins/plugin-slug/*</code>: All stylesheets for plugin-slug.
				',
			'type'    => 'textarea_small',
			'default' => 'id:wp-block-library',
			'attributes' => [
				'data-conditional-id' => [
					['key' => 'remove_css'],
					['key' => 'remove_css_all', 'value' => 'off'],
				]
			],
		];

		$options[] = [
			'id'      => 'remove_css_excludes',
			'name'    => esc_html__('Exclude Stylesheets', 'debloat'),
			'desc'    =>
				esc_html__('Enter one per line to exclude certain CSS files from this optimizations. Examples:', 'debloat')
				. ' <code>id:my-css-id</code>
				<br /><code>wp-content/themes/my-theme/style.css</code>
				<br /><code>wp-content/themes/my-theme*</code>
				',
			'type'    => 'textarea_small',
			'default' => '',
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'      => 'allow_css_selectors',
			'name'    => esc_html__('Always Keep Selectors', 'debloat'),
			'desc'    =>
				esc_html__('Enter one per line. Partial or full matches for selectors (if any of these keywords found, the selector will be kept). Examples:', 'debloat')
				. ' <code>.myclass</code>
				<br /><code>.myclass*</code>: Will match selectors starting with .myclass, .myclass-xyz, .myclass_xyz etc.
				<br /><code>.myclass *</code>: Selectors starting with .myclass, .myclass .sub-class and so on.
				<br /><code>*.myclass *</code>: For matching .xyz .myclass, .myclass, .xyz .myclass .xyz and so on.
				',
			'type'    => 'textarea_small',
			'default' => '',
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'       => 'allow_css_conditionals',
			'name'     => esc_html__('Advanced: Conditionally Keep Selectors', 'debloat'),
			'desc'     => 'Add advanced conditions.',
			'type'       => 'checkbox',
			'default'   => 0,
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'      => 'allow_conditionals_data',
			'name'    => '',
			'desc'    => 'Keep selector if a certain condition is true. For example, condition type class with match <code>.mfp-lightbox</code> can be used to search for <code>.mfp-</code> to keep all the CSS selectors that have .mfp- in selector.',
			'type'    => 'group',
			'default' => [],
			'attributes' => ['data-conditional-id' => 'remove_css'],
			'options'    => [
				'group_title' => 'Condition {#}',
				'add_button'  => esc_html__('Add Condition', 'debloat'),
				'remove_button' => esc_html__('Remove', 'debloat'),
				'closed' => true,
			]
		];


		$options[] = [
			'id'      => 'remove_css_on',
			'name'    => esc_html__('Remove CSS On', 'debloat'),
			'desc'    => esc_html__('Pages where unused CSS should be removed.', 'debloat'),
			'type'    => 'multicheck',
			'options' => self::get_common('enable_on'),
			'default'    => ['all'],
			'select_all_button' => false,
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'      => 'delay_css_load',
			'name'    => esc_html__('Delay load Original CSS', 'debloat'),
			'desc'    => esc_html__('Delay-loading all of the original CSS might be needed in situations where there are too many JS-based CSS classes that are added later such as sliders, that you cannot track down and add to exclusions right now. Or on pages that may have Auto-load Next Post.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => ['data-conditional-id' => 'remove_css'],
		];

		$options[] = [
			'id'      => 'delay_css_on',
			'name'    => esc_html__('Delay load Original On', 'debloat'),
			'desc'    => esc_html__('Pages where original CSS should be delayed load.', 'debloat'),
			'type'    => 'multicheck',
			'options' => self::get_common('enable_on'),
			'default'    => ['all'],
			'select_all_button' => false,
			'attributes' => ['data-conditional-id' => 'delay_css_load'],
		];

		return $options;
	}

	public static function get_js()
	{
		$options = [];

		/**
		 * Optimize JS
		 */
		$options[] = [
			'name'    => esc_html__('Optimize JS', 'debloat'),
			// 'description' => 'foo',
			'type'    => 'title',
			'id'      => '_defer_js',
		];

		$options[] = [
			'id'      => 'defer_js',
			'name'    => esc_html__('Defer Javascript', 'debloat'),
			'desc'    => esc_html__('Delay JS execution till HTML is loaded to fix Render-Blocking JS issues.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
		];

		$options[] = [
			'id'      => 'defer_js_excludes',
			'name'    => esc_html__('Exclude Scripts', 'debloat'),
			'desc'    => esc_html__('Enter one per line to exclude certain JS files from being deferred.', 'debloat'),
			'type'    => 'textarea_small',
			'default' => '',
			'attributes' => ['data-conditional-id' => 'defer_js'],
		];

		$options[] = [
			'id'      => 'defer_js_inline',
			'name'    => esc_html__('Defer Inline JS', 'debloat'),
			'desc'    => sprintf(
				'%s<p><strong>%s</strong> %s</p>',
				esc_html__('Defer all inline JS.', 'debloat'),
				esc_html__('Note:', 'debloat'),
				esc_html__('Normally not needed. All correct dependent inline scripts are deferred by default. Enable if inline JS not enqueued using WordPress enqueue functions.', 'debloat')
			),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => ['data-conditional-id' => 'defer_js'],
		];

		$options[] = [
			'id'      => 'minify_js',
			'name'    => esc_html__('Minify Javascript', 'debloat'),
			'desc'    => esc_html__('Minify all the deferred or delayed JS files.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
		];

		$options[] = [
			'id'      => 'integrations_js',
			'name'    => esc_html__('Enable Plugin Integrations', 'debloat'),
			'desc'    => esc_html__('Special pre-made rules for javascript, specific to plugins, are applied if enabled.', 'debloat'),
			'type'    => 'multicheck_inline',
			'options' => [
				'elementor' => 'Elementor',
				'wpbakery'  => 'WPBakery Page Builder',
			],
			'default'    => ['elementor', 'wpbakery'],
			'select_all_button' => false,
		];

		/**
		 * Delay JS
		 */
		$options[] = [
			'name'    => esc_html__('Delay Load JS', 'debloat'),
			// 'description' => 'foo',
			'type'    => 'title',
			'id'      => '_delay_js',
		];

		$options[] = [
			'id'      => 'delay_js',
			'name'    => esc_html__('Delay Javascript', 'debloat'),
			'desc'    => esc_html__('Delay execution of the targeted JS files until user interaction.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
		];

		$options[] = [
			'id'      => 'delay_js_max',
			'name'    => esc_html__('Maximum Delay (in seconds)', 'debloat'),
			'desc'    => esc_html__('Max seconds to wait for interaction until delayed JS is loaded anyways.', 'debloat'),
			'type'    => 'text_small',
			'default' => '',
			'attributes' => [
				'type' => 'number',
				'min'  => 0,
				'data-conditional-id' => 'delay_js'
			],
		];

		$options[] = [
			'id'      => 'delay_js_all',
			'name'    => esc_html__('Delay All Scripts', 'debloat'),
			'desc'    => esc_html__('CAREFUL. Delays all JS files. Its better to target scripts manually below. If there are scripts that setup sliders/carousels, animations, or other similar things, these won\'t be setup until the first user interaction.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => ['data-conditional-id' => 'delay_js'],
		];

		$options[] = [
			'id'      => 'delay_js_includes',
			'name'    => esc_html__('Target Scripts', 'debloat'),
			'desc'    =>
				esc_html__('Will delay from these scripts. You may use an ID, part of the URL, or any code for inline scripts. One per line. Examples:', 'debloat')
				. ' <code>id:my-js-id</code>
				<br /><code>my-theme/js-file.js</code>
				<br /><code>wp-content/themes/my-theme/*</code>: All theme JS files.
				<br /><code>plugins/plugin-slug/*</code>: All JS files for plugin-slug.
				',
			'type'    => 'textarea_small',
			'default' => implode("\n", [
				'twitter.com/widgets.js',
				'gtm.js',
				'id:google_gtagjs'
			]),
			'attributes' => [
				'data-conditional-id' => [
					['key' => 'delay_js'],
					['key' => 'delay_js_all', 'value' => 'off'],
				]
			],
		];

		$options[] = [
			'id'      => 'delay_js_excludes',
			'name'    => esc_html__('Exclude Scripts', 'debloat'),
			'desc'    =>
				esc_html__('Enter one per line to exclude certain scripts from this optimizations. Examples:', 'debloat')
				. '<code>id:my-js-id</code>
				<br /><code>my-theme/js-file.js</code>
				<br /><code>wp-content/themes/my-theme/*</code>: All theme JS files.
				<br /><code>someStringInJs</code>: Exclude by some text in inline JS tag.
				',
			'type'    => 'textarea_small',
			'default' => '',
			'attributes' => ['data-conditional-id' => 'delay_js'],
		];

		$options[] = [
			'id'      => 'delay_js_adsense',
			'name'    => esc_html__('Delay Google Ads', 'debloat'),
			'desc'    => esc_html__('Delay Google Adsense until first interaction. Note: This may not be ideal if you have ads that are in header.', 'debloat'),
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => ['data-conditional-id' => 'delay_js'],
		];
		
		return $options;
	}

	public static function get_general()
	{
		$options = [];

		$options[] = [
			'name'    => esc_html__('Disable for Admins', 'debloat'),
			'desc'    => esc_html__('Disable processing for logged in admin users or any user with capability "manage_options". (Useful if using a pagebuilder that conflicts)', 'debloat'),
			'id'      => 'disable_for_admins',
			'type'    => 'checkbox',
			'default' => 0,
		];

		return $options;
	}

	public static function get_all()
	{
		return array_merge(
			self::get_css(),
			self::get_js()
		);
	}
}