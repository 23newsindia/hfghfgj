<?php

namespace Sphere\Debloat\WpCli;
use Sphere\Debloat\Admin\Cache;

/**
 * Commands for WPCLI 
 */
class Commands extends \WP_CLI_Command {

	/**
	 * Flush the Debloat cache.
	 *
	 * [--network]
	 *      Flush CSS Cache for all the sites in the network.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp debloat empty-cache
	 *      - Delete all the cached content.
	 *
	 *  2. wp debloat empty-cache --network
	 *      - Delete all the cached content including all transients for sites in a network.
	 *
	 * @since 2.1.0
	 * @access public
	 * @alias empty-cache
	 */
	public function empty_cache($args, $assoc_args) 
	{
		$network = !empty($assoc_args['network']) && is_multisite();
		$cache   = new Cache;

		if ($network) {
			/** @var \WP_Site[] $blogs */
			$blogs = get_sites();

			foreach ($blogs as $key => $blog) {
				$blog_id = $blog->blog_id;
				switch_to_blog($blog_id);

				$cache->empty();

				\WP_CLI::success('Emptied debloat cache for site - ' . get_option('home'));

				restore_current_blog();
			}

			return;
		}

		$cache->empty();
		\WP_CLI::success('Emptied all debloat cache.');
	}
}
