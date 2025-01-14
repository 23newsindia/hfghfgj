<?php

namespace Sphere\Debloat;

use Sphere\Debloat\Admin\Cache;
use Sphere\Debloat\Admin\OptionsData;

/**
 * Admin initialization.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class Admin
{
	/**
	 * @var Sphere\Debloat\Admin\Cache
	 */
	protected $cache;

	/**
	 * Setup hooks
	 */
	public function init()
	{
		$this->cache = new Cache;
		$this->cache->init();

		add_action('cmb2_admin_init',  [$this, 'setup_options']);

		// Enqueue at a lower priority to be after CMB2.
		add_action('admin_enqueue_scripts', [$this, 'register_assets'], 99);

		// Page to delete cache.
		add_action('admin_menu', function() {
			add_submenu_page(
				'', 
				'Delete Cache', 
				'Delete Cache', 
				'manage_options', 
				'debloat-delete-cache', 
				[$this, 'delete_cache']
			);
		});

		// Empty cache on save.
		add_action('cmb2_save_options-page_fields', [$this, '_delete_cache']);

		/**
		 * Fix: CMB2 doesn't save unchecked making default => true impossible.
		 */
		add_filter('cmb2_sanitize_checkbox', function($override, $value) {
			return is_null($value) ? '0' : $value;
		}, 20, 2);

		// Custom CMB2 field for manual callback
		add_action('cmb2_render_manual', function($field) {

			// Add attributes to an empty span for cmb2-conditional
			if (!empty($field->args['attributes'])) {
				printf('<meta name="%s" %s />', 
					$field->args('id'),
					\CMB2_Utils::concat_attrs($field->args('attributes'))
				);
			}

			if (!empty($field->args['render_html']) && is_callable($field->args['render_html'])) {
				call_user_func($field->args['render_html'], $field);
			}

			if (!empty($field->args['desc'])) {
				echo '<p class="cmb2-metabox-description">' . esc_html($field->args['desc']) . '</p>';
			}
		});
	}

	/**
	 * Register admin assets
	 */
	public function register_assets()
	{
		// Specific assets for option pages only
		if (!empty($_GET['page']) && strpos($_GET['page'], 'debloat_options') !== false) {

			wp_enqueue_script(
				'debloat-cmb2-conditionals', 
				Plugin::get_instance()->dir_url . 'js/admin/cmb2-conditionals.js', 
				['jquery'],
				Plugin::VERSION
			);

			wp_enqueue_script(
				'debloat-options', 
				Plugin::get_instance()->dir_url . 'js/admin/options.js', 
				['jquery', 'debloat-cmb2-conditionals'],
				Plugin::VERSION
			);

			wp_enqueue_style(
				'debloat-admin-cmb2',
				Plugin::get_instance()->dir_url . 'css/admin/cmb2.css',
				['cmb2-styles'],
				Plugin::VERSION
			);
		}
	}

	/**
	 * Delete Cache page.
	 */
	public function delete_cache()
	{
		check_admin_referer('debloat_delete_cache');
		$this->_delete_cache();

		echo '
			<h2>Clearing Cache</h2>
			<p>Caches cleared. You may also have to clear your cache plugins.</p>
			<a href="' . esc_url(admin_url('admin.php?page=debloat_options')) . '">Back to Options</a>';
	}

	/**
	 * Callback: Delete the cache.
	 *
	 * @access private
	 */
	public function _delete_cache()
	{
		$this->cache->empty();

		/**
		 * Hook after deleting cache.
		 */
		do_action('debloat/after_delete_cache');
	}

	/**
	 * Setup admin options with CMB2
	 */
	public function setup_options()
	{
		// Configure admin options
		$options = new_cmb2_box([
			'id'           => 'debloat_options',
			'title'        => esc_html__('Debloat Plugin Settings', 'debloat'),
			'object_types' => ['options-page'],
			'option_key'   => 'debloat_options',
			'parent_slug'  => 'options-general.php',
			'menu_title'   => esc_html__('Debloat: Optimize', 'debloat'),
			'tab_group'    => 'debloat_options',
			'tab_title'    => esc_html__('Optimize CSS', 'debloat'),
			'classes'      => 'sphere-cmb2-wrap',
			'display_cb'   => [$this, 'render_options_page'],
		]);

		$this->add_options(
			OptionsData::get_css(),
			$options
		);

		// Configure admin options
		$js_options = new_cmb2_box([
			'id'           => 'debloat_options_js',
			'title'        => esc_html__('Optimize JS', 'debloat'),
			'object_types' => ['options-page'],
			'option_key'   => 'debloat_options_js',
			'parent_slug'  => 'debloat_options',
			'menu_title'   => esc_html__('Optimize JS', 'debloat'),
			'tab_group'    => 'debloat_options',
			'tab_title'    => esc_html__('Optimize JS', 'debloat'),
			'classes'      => 'sphere-cmb2-wrap',
			'display_cb'   => [$this, 'render_options_page'],
		]);

		$this->add_options(
			OptionsData::get_js(),
			$js_options
		);

		// Configure admin options
		$general_options = new_cmb2_box([
			'id'           => 'debloat_options_general',
			'title'        => esc_html__('General Settings', 'debloat'),
			'object_types' => ['options-page'],
			'option_key'   => 'debloat_options_general',
			'parent_slug'  => 'debloat_options',
			'menu_title'   => esc_html__('General Settings', 'debloat'),
			'tab_group'    => 'debloat_options',
			'tab_title'    => esc_html__('General Settings', 'debloat'),
			'display_cb'   => [$this, 'render_options_page'],
			'classes'      => 'sphere-cmb2-wrap'
		]);

		$this->add_options(
			OptionsData::get_general(),
			$general_options
		);

		do_action('debloat/admin/after_options', $options);

	}
	
	/**
	 * Add options to CMB2 array.
	 *
	 * @param array $options
	 * @param \CMB2 $object
	 * @return void
	 */
	protected function add_options($options, $object)
	{
		return array_map(
			function($option) use ($object) {
				if (isset($option['attributes']['data-conditional-id'])) {
					$condition = &$option['attributes']['data-conditional-id'];
					if (is_array($condition)) {
						$condition = json_encode($condition);
					}
				}

				$field_id = $object->add_field($option);
				
                if ($option['type'] === 'group') {
                    $this->add_option_group($option, $field_id, $object);
                }

			}, 
			$options
		);
	}

	protected function add_option_group($option, $group_id, $object)
	{
		if ($option['id'] === 'allow_conditionals_data') {
			$object->add_group_field($group_id, [
				'id'      => 'type',
				'name'    => esc_html__('Condition Type', 'debloat'),
				'type'    => 'radio',
				'default' => 'class',
				'options' => [
					'class'  => esc_html__('Class - If a class (in "condition match") exists in HTML, keep classes matching "selector match".', 'debloat'),
					'prefix' => esc_html__('Prefix - Condition matches the first class and keeps all the used child classes. Example: .s-dark will keep .s-dark .site-header.', 'debloat'),
				],
			]);

			$object->add_group_field($group_id, [
				'id'      => 'match',
				'name'    => esc_html__('Condition Match', 'debloat'),
				'desc'    => esc_html__('Required. Usually a single class, example:', 'debloat') . '<code>.my-class</code>',
				'type'    => 'text',
				'default' => '',
			]);

			$object->add_group_field($group_id, [
				'id'      => 'search',
				'name'    => esc_html__('Selectors Match', 'debloat'),
				'desc'    => esc_html__('Enter one per line. See example matchings in "Always Keep Selectors" above.', 'debloat'),
				'type'    => 'textarea_small',
				'default' => '',
				'attributes' => [
					'data-conditional-id' => json_encode([$group_id, 'type']),
					'data-conditional-value' => 'class'
				]
			]);
		}
	}

	public function render_options_page($hookup)
	{
		?>
		<div class="cmb2-options-page debloat-options option-<?php echo esc_attr( sanitize_html_class( $hookup->option_key ) ); ?>">
			<div class="wrap">
				<?php if ( $hookup->cmb->prop( 'title' ) ) : ?>
					<h2><?php echo wp_kses_post( $hookup->cmb->prop( 'title' ) ); ?></h2>
				<?php endif; ?>
			</div>
			
			<div class="wrap"><?php $hookup->options_page_tab_nav_output(); ?></div>

			<div class="debloat-inner-wrap">
				<form class="cmb-form debloat-options-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="<?php echo esc_attr($hookup->cmb->cmb_id); ?>" enctype="multipart/form-data" encoding="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo esc_attr( $hookup->option_key ); ?>">

					<div class="sphere-cmb2-wrap debloat-intro-info">
						<div class="cmb2-wrap cmb2-metabox">
						<div class="cmb-row">
							<h3>Important: Debloat Plugin</h3>
							<p>
								This plugin is for advanced users. The features "Remove Unused CSS" and "Delay JS" are especially for advanced users only.
							</p>
							<ol>
								<li>Use a cache plugin like W3 Total Cache, WP Super Cache, etc. <strong>Required</strong> for Remove Unused CSS feature.</li>
								<li>Do <strong>NOT</strong> enable minification, CSS, or JS optimization via another plugin.</li>
								<li>If your theme doesn't have it built-in, use a Lazyload plugin for images.</li>
							</ol>
						</div>
						</div>
					</div>

					<?php $hookup->options_page_metabox(); ?>
					<?php submit_button( esc_attr( $hookup->cmb->prop( 'save_button' ) ), 'primary', 'submit-cmb' ); ?>
				</form>
				<div class="debloat-sidebar">
					<?php $this->cache_info(); ?>
				</div>
			</div>

		</div>
		<?php
	}

	public function cache_info()
	{
		$js_cache   = Plugin::file_cache()->get_stats('js');
		$css_cache  = Plugin::file_cache()->get_stats('css');

		// Number of css sheets in cache.
		$css_sheets = count($this->cache->get_transients());
		?>

		<div class="sphere-cmb2-wrap debloat-cache-info">
		<div class="cmb2-wrap cmb2-metabox">
			<div class="cmb-row cmb-type-title">
				<div class="cmb-td">
					<h3 class="cmb2-metabox-title">
						<?php esc_html_e('Cache Stats', 'debloat'); ?>
					</h3>
				</div>
			</div>

			<div class="cmb-row">
				<?php if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG): ?>
					<p><strong>Minification Disabled</strong>: SCRIPT_DEBUG is enabled (likely in wp-config.php).</p>
				<?php endif; ?>
				
				<div class="cache-stats">
					<p><?php printf(esc_html__('Minified CSS files: %d', 'debloat'), $css_cache); ?></p>
					<p><?php printf(esc_html__('Minified JS files: %d', 'debloat'), $js_cache); ?></p>
					<p><?php printf(esc_html__('Processed CSS Sheets: %d', 'debloat'), $css_sheets); ?></p>
				</div>

				<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=debloat-delete-cache'), 'debloat_delete_cache'); ?>" 
					class="button button-secondary" style="margin-top: 10px;">
					<?php echo esc_html('Empty All Cache', 'debloat'); ?>
				</a>
				</p>
			</div>
		</div>
		</div>

		<?php
	}
}