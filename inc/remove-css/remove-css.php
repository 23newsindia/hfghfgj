<?php

namespace Sphere\Debloat;
use Sphere\Debloat\RemoveCss\Sanitizer;
use Sphere\Debloat\OptimizeCss\Stylesheet;

/**
 * Process stylesheets to debloat and optimize CSS.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class RemoveCss
{
	/**
	 * @var \DOMDocument
	 */
	public $dom;
	public $html;

	/**
	 * @var array
	 */
	public $used_markup = [];

	/**
	 * @var array
	 */
	protected $stylesheets;

	protected $include_sheets = [];
	protected $exclude_sheets = [];

	/**
	 * Undocumented function
	 *
	 * @param Stylesheet[] $stylesheets
	 * @param \DOMDocument $dom
	 * @param string $raw_html
	 */
	public function __construct($stylesheets, \DOMDocument $dom, string $raw_html)
	{
		$this->stylesheets = $stylesheets;
		$this->dom         = $dom;
		$this->html        = $raw_html;
	}

	public function process()
	{		
		// Collect all the classes, ids, tags used in DOM.
		$this->find_used_selectors();

		// Figure out valid sheets.
		$this->setup_valid_sheets();

		/**
		 * Process and replace stylesheets with CSS cleaned.
		 */
		do_action('debloat/remove_css_begin', $this);
		$allow_selectors = $this->get_allowed_selectors();

		foreach ($this->stylesheets as $sheet) {
			if (!$this->should_process_stylesheet($sheet)) {
				// Util\debug_log('Skipping: ' . print_r($sheet, true));
				continue;
			}

			// Perhaps not a local file or unreadable.
			$file_data = $this->process_file_by_url($sheet->url);
			if (!$file_data) {
				continue;
			}

			$sheet->content = $file_data['content'];
			$sheet->file    = $file_data['file'];

			// Parsed sheet will be cached instead of being parsed by Sabberworm again.
			$this->setup_sheet_cache($sheet);

			/**
			 * Fire up sanitizer to process and remove unused CSS.
			 */
			$sanitizer = new Sanitizer($sheet, $this->used_markup, $allow_selectors);
			$sanitized_css = $sanitizer->sanitize();

			// Store sizes for debug info.
			$sheet->original_size = strlen($sheet->content);
			$sheet->new_size =  $sanitized_css ? strlen($sanitized_css) : $sheet->original_size;

			if ($sanitized_css) {

				// Pre-render as we'll have to add a delayed css tag as well, if enabled.
				$sheet->content   = $sanitized_css;
				$sheet->render_id = 'debloat-' . $sheet->id;
				$sheet->set_render('inline');
				$replacement = $sheet->render();

				// Add tags for delayed CSS files.
				if (Plugin::delay_load()->should_delay_css()) {

					$sheet->delay_type = Plugin::options()->delay_css_type;
					$sheet->set_render('delay');
					$sheet->has_delay = true;

					// Add the delay load CSS tag in addition to inlined sanitized CSS above.
					$replacement .= $sheet->render();
				}

				$sheet->set_render('remove_css', $replacement);

				// Save in parsed css cache if not already saved.
				$this->save_sheet_cache($sheet);
			}

			// Free up memory.
			$sheet->content = '';
			$sheet->parsed_data = '';
		}

		// $this->stylesheets = array_map(function($sheet) {
		// 	if (isset($sheet->original_size)) {
		// 		$sheet->saved = $sheet->original_size - $sheet->new_size;
		// 	}
		// 	return $sheet;
		// }, $this->stylesheets);

		$total = array_reduce($this->stylesheets, function($acc, $item) {
			if (!empty($item->original_size)) {
				$acc += ($item->original_size - $item->new_size);
			}
			return $acc;
		}, 0);

		$this->html .= "\n<!-- Debloat Remove CSS Saved: {$total} bytes. -->";
		
		return $this->html;
	}

	/**
	 * Add parsed data cache to stylesheet object. Will be used by save_sheet_cache later.
	 *
	 * @param Stylesheet $sheet
	 * @return void
	 */
	public function setup_sheet_cache(Stylesheet $sheet)
	{
		if (!isset($sheet->file)) {
			return;
		}

		$cache = get_transient($this->get_transient_id($sheet));
		if ($cache && $cache['mtime'] < Plugin::file_system()->mtime($sheet->file)) {
			return;
		}

		if ($cache && !empty($cache['data'])) {
			$sheet->parsed_data = $cache['data'];
			$sheet->has_cache = true;
			return;
		}
	}

	protected function get_transient_id($sheet)
	{
		return substr('debloat_sheet_cache_' . $sheet->id, 0, 190);
	}

	/**
	 * Cache the parsed data.
	 * 
	 * Note: This doesn't cache whole CSS as that would vary based on found selectors.
	 *
	 * @param Stylesheet $sheet
	 * @return void
	 */
	public function save_sheet_cache(Stylesheet $sheet)
	{
		if ($sheet->has_cache) {
			return;
		}
		
		$cache_data = [
			'data'  => $sheet->parsed_data,
			'mtime' => Plugin::file_system()->mtime($sheet->file)
		];

		// With expiry; won't be auto-loaded.
		set_transient($this->get_transient_id($sheet), $cache_data, MONTH_IN_SECONDS);
	}

	/**
	 * Setup includes and excludes based on options.
	 *
	 * @return void
	 */
	public function setup_valid_sheets()
	{
		$default_excludes = [
			'wp-includes/css/dashicons.css',
			'admin-bar.css',
			'wp-mediaelement'
		];
		
		$excludes = array_merge(
			$default_excludes, 
			Util\option_to_array(Plugin::options()->remove_css_excludes)
		);

		$this->exclude_sheets = apply_filters('debloat/remove_css_excludes', $excludes, $this);

		if (Plugin::options()->remove_css_all) {
			return;
		}

		if (Plugin::options()->remove_css_theme) {
			$this->include_sheets[] = content_url('themes') . '/*';
		}

		if (Plugin::options()->remove_css_plugins) {
			$this->include_sheets[] = content_url('plugins') . '/*';
		}

		$this->include_sheets = array_merge(
			$this->include_sheets,
			Util\option_to_array(Plugin::options()->remove_css_includes)
		);

		$this->include_sheets = apply_filters('debloat/remove_css_includes', $this->include_sheets, $this);
	}

	/**
	 * Determine if stylesheet should be processed, based on exclusion and inclusion
	 * rules and settings.
	 *
	 * @param Stylesheet $sheet
	 * @return boolean
	 */
	public function should_process_stylesheet(Stylesheet $sheet)
	{
		// Handle manual excludes first.
		if ($this->exclude_sheets) {
			foreach ($this->exclude_sheets as $exclude) {
				if (Util\asset_match($exclude, $sheet)) {
					return false;
				}
			}
		}

		// All stylesheets are valid.
		if (Plugin::options()->remove_css_all) {
			return true;
		}

		foreach ($this->include_sheets as $include) {
			if (Util\asset_match($include, $sheet)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all the allowed selectors in correct data format to be used by sanitizer.
	 *
	 * @return array
	 */
	public function get_allowed_selectors()
	{
		// Allowed selectors of type 'any': simple match in selector string.
		$allowed_any = array_map(
			function($value) {
				if (!$value) {
					return '';
				}

				return [
					'type'   => 'any',
					'search' => [$value]
				];
			},
			Util\option_to_array((string) Plugin::options()->allow_css_selectors)
		);

		// Conditional selectors.
		$allowed_conditionals = [];
		$conditionals = Plugin::options()->allow_css_conditionals
			? (array) Plugin::options()->allow_conditionals_data
			: [];

		if ($conditionals) {
			$allowed_conditionals = array_map(
				function($value) {
					if (!isset($value['match'])) {
						return '';
					}

					$value['class'] = preg_replace('/^\./', '', trim($value['match']));

					if ($value['type'] !== 'prefix' && isset($value['selectors'])) {
						$value['search'] = Util\option_to_array($value['selectors']);
					}

					return $value;
				},
				$conditionals
			);
		}

		$allowed = apply_filters(
			'debloat/allow_css_selectors', 
			array_filter(array_merge($allowed_any, $allowed_conditionals)),
			$this
		);

		return $allowed;
	}

	/**
	 * Find all the classes, ids, and tags used in the document.
	 *
	 * @return void
	 */
	protected function find_used_selectors()
	{
		$this->used_markup = [
			'tags'    => [],
			'classes' => [],
			'ids'     => [],
		];

		/**
		 * @var DOMElement $node
		 */
		$classes = [];
		foreach ($this->dom->getElementsByTagName('*') as $node) {
			$this->used_markup['tags'][ $node->tagName ] = 1;

			// Collect tag classes.
			if ($node->hasAttribute('class')) {
				$class = $node->getAttribute('class');
				$ele_classes = preg_split('/\s+/', $class);
				array_push($classes, ...$ele_classes);
			}

			if ($node->hasAttribute('id')) {
				$this->used_markup['ids'][ $node->getAttribute('id') ] = 1;
			}
		}

		// Add the classes.
		$classes = array_filter(array_unique($classes));
		if ($classes) {
			$this->used_markup['classes'] = array_fill_keys($classes, 1);
		}
	}

	/**
	 * Process a stylesheet via URL
	 * 
	 * @uses Plugin::file_system()
	 * @uses Plugin::file_system()->url_to_local()
	 * @return boolean|array With 'content' and 'file'.
	 */
	public function process_file_by_url($url)
	{
		// Try to get local path for this stylesheet
		$file = Plugin::file_system()->url_to_local($url);
		if (!$file) {
			return false;
		}

		// We can only support .css files yet
		if (substr($file, -4) !== '.css') {
			return false;
		}

		$content = Plugin::file_system()->get_contents($file);
		if (!$content) {
			return false;
		}

		return [
			'content' => $content,
			'file'    => $file
		];
	}
}