<?php

namespace Sphere\Debloat;

use Sphere\Debloat\Base\Asset;
use Sphere\Debloat\OptimizeCss\Stylesheet;
use MatthiasMullie\Minify;

/**
 * Minifer for assets with cache integration.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class Minifier
{
	protected $asset_type = 'js';

	/**
	 * @var Sphere\Debloat\OptimizeCss\Stylesheet|Sphere\Debloat\OptimizeJs\Script
	 */
	protected $asset;

	/**
	 * @param \Sphere\Debloat\Base\Asset $asset
	 */
	public function __construct(Asset $asset)
	{
		if ($asset instanceof Stylesheet) {
			$this->asset_type = 'css';
		}

		$this->asset = $asset;
	}

	/**
	 * Minify the asset, cache it, and replace its URL in the asset object.
	 * 
	 * @uses Plugin::file_cache()
	 * @uses Plugin::file_system()
	 * 
	 * @return string URL of the minified file.
	 */
	public function process()
	{
		// Not for inline assets.
		if (!$this->asset->url) {
			return;
		}

		// Debugging scripts. Do not minify.
		if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
			return;
		}

		$file = Plugin::file_cache()->get_url($this->asset->url);
		if (!$file) {
			$minify = $this->minify();
			if (!$minify) {
				return;
			}
			
			// For CSS assets, convert URLs to fully qualified.
			$this->maybe_convert_urls();
			
			if (!Plugin::file_cache()->set($this->asset->url, $this->asset->content)) {
				return;
			}
			
			$file = Plugin::file_cache()->get_url($this->asset->url);
		}

		$this->asset->minified_url = $file;
		return $file;
	}

	/**
	 * Minify the file using the URL in the asset object.
	 *
	 * @return string|boolean
	 */
	public function minify()
	{
		// We support google fonts remote fetch.
		if (
			Plugin::options()->optimize_gfonts_inline && 
			$this->asset instanceof Stylesheet && 
			$this->asset->is_google_fonts()
		) {
			$this->fetch_remote_content();
		
		} else {
			// We minify and cache local source only for now.
			$source_file = Plugin::file_system()->url_to_local($this->asset->url);
			if (!$source_file) {
				return false;
			}

			$source = Plugin::file_system()->get_contents($source_file);
			$this->asset->content = $source;
		}

		/**
		 * We want a cached file with source data whether existing is minified or not - as 
		 * post-processing is needed for URLs when inlining the CSS. 
		 * 
		 * For JS, caching the already min files also serves the purpose of not testing them
		 * again, unnecessarily.
		 */
		if ($this->is_content_minified()) {
			return $this->asset->content;
		}

		Util\debug_log('Minifying: ' . $this->asset->url);

		// JS minifier.
		if ($this->asset_type === 'js') {

			// Improper handling for older webpack: https://github.com/matthiasmullie/minify/issues/375
			if (strpos($source, 'var __webpack_require__ = function (moduleId)') !== false) {
				return false;
			}

			$minifier = new Minify\JS($this->asset->content);
			$this->asset->content = $minifier->minify();

		} else {

			// CSS minifier. Set content and convert urls.
			$minifier = new Minify\CSS($this->asset->content);
			$this->asset->content = $minifier->minify();
		}
		
		return $this->asset->content;
	}

	/**
	 * Convert URLs for CSS assets.
	 *
	 * @return void
	 */
	public function maybe_convert_urls()
	{
		if ($this->asset_type !== 'css') {
			return;
		}

		// Check if any non-data URLs exist.
		if (!preg_match('/url\((?![\'"\s]*data:)/', $this->asset->content)) {
			return;
		}

		$this->asset->convert_urls();
	}

	/**
	 * Get remote asset content and add to asset content.
	 *
	 * @return void
	 */
	public function fetch_remote_content()
	{
		$request = wp_remote_get($this->asset->url, [
			'timeout' => 5,
			// For google fonts mainly.
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36',
		]);

		if (is_wp_error($request) || empty($request['body'])) {
			return;
		}

		$this->asset->content = $request['body'];
	}

	/**
	 * Check if provided content is already minified.
	 *
	 * @return boolean
	 */
	public function is_content_minified($content = '')
	{
		$content = $content ?: $this->asset->content;

		// Already minified asset.
		if (preg_match('/[\-\.]min\.(js|css)/', $this->asset->url)) {
			return true;
		}

		$content = trim($content);
		if (!$content) {
			return true;
		}

		// Hacky, but will do. 
		$new_lines = substr_count($content, "\n", 0, min(strlen($content), 2000));
		if ($new_lines < 5) {
			return true;
		}
	}
}