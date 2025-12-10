<?php
/**
 * Cache Clearing Helper
 *
 * Handles clearing page cache for various caching plugins
 * when Beaver Builder cache files are regenerated.
 *
 * @package BB_Plugin_Cache_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Clearer Class
 *
 * Provides methods to clear cache for various caching plugins
 */
class BB_Plugin_Cache_Clearer {

	/**
	 * Clear page cache for all supported caching plugins
	 *
	 * Supports common caching plugins to ensure users get updated files
	 * instead of stale cached pages referencing old/deleted cache files.
	 *
	 * @return void
	 */
	public static function clear_all_cache() {
		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		} elseif ( class_exists( 'W3_Plugin_TotalCacheAdmin' ) && function_exists( 'w3_instance' ) ) {
			$w3_plugin = w3_instance( 'W3_Plugin_TotalCacheAdmin' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			if ( $w3_plugin && method_exists( $w3_plugin, 'flush_all' ) ) {
				$w3_plugin->flush_all();
			}
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
			LiteSpeed_Cache_API::purge_all(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
		}

		// WP Fastest Cache
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		// Cache Enabler
		if ( function_exists( 'cache_enabler_clear_complete_cache' ) ) {
			cache_enabler_clear_complete_cache(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		}

		// Comet Cache
		if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
			comet_cache::clear(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
		}

		// Hummingbird
		if ( class_exists( '\WP_Hummingbird\Core\Utils' ) ) {
			$hummingbird = \WP_Hummingbird\Core\Utils::get_module( 'page_cache' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
			if ( $hummingbird && method_exists( $hummingbird, 'clear_cache' ) ) {
				$hummingbird->clear_cache();
			}
		}

		// WP-Optimize
		if ( class_exists( 'WP_Optimize' ) && method_exists( 'WP_Optimize', 'get_cache' ) ) {
			$wp_optimize = WP_Optimize::get_cache(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
			if ( $wp_optimize && method_exists( $wp_optimize, 'purge' ) ) {
				$wp_optimize->purge();
			}
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
		}

		// Breeze
		if ( class_exists( 'Breeze_Admin' ) && method_exists( 'Breeze_Admin', 'breeze_clear_all_cache' ) ) {
			Breeze_Admin::breeze_clear_all_cache(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
		}

		// Swift Performance
		if ( class_exists( 'Swift_Performance_Cache' ) && method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
			Swift_Performance_Cache::clear_all_cache(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
		}

		// Generic WordPress transients (object cache)
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Allow other plugins to hook in
		do_action( 'bb_plugin_cache_helper_clear_page_cache' );
	}
}

