<?php
/**
 * Plugin Name: Beaver Builder Cache Helper
 * Description: Ensures Beaver Builder "cache" files (CSS/JS) - the ones in the "uploads" directory, exist before access, preventing 404 errors. Automatically clears page cache when BB cache files are regenerated. Works with CDN setups and caching plugins like WP Rocket, W3 Total Cache, LiteSpeed Cache, and more.
 * Version: 1.1.0
 * Author: Adrian Voicu - Avenirer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load cache clearer class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache-clearer.php';

/**
 * BB Plugin Cache Helper
 *
 * This plugin ensures that Beaver Builder cache files (CSS/JS) exist
 * before they are enqueued or accessed, preventing 404 errors.
 * 
 * It also clears page cache when BB cache files are regenerated to
 * ensure users get updated files instead of stale cached pages.
 * 
 * This is particularly useful when using:
 * - CDN setups (CloudFront, etc.) where cache files might not be immediately available
 * - Caching plugins (WP Rocket, W3 Total Cache, etc.) that may serve
 *   stale pages referencing old/deleted cache files
 */
class BB_Plugin_Cache_Helper {

	/**
	 * Track which assets we've already checked/created to avoid infinite loops
	 *
	 * @var array
	 */
	private static $checked_assets = array();

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		// Always add admin test page (even if BB isn't active yet)
		add_action( 'admin_menu', array( $this, 'add_test_page' ) );

		// Only run Beaver Builder hooks if BB is active
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		// Hook into wp_enqueue_scripts early to ensure files exist before enqueuing
		add_action( 'wp_enqueue_scripts', array( $this, 'ensure_cache_files_exist' ), 5 );

		// Hook into AJAX asset rendering (these are actions, not filters)
		add_action( 'fl_builder_after_render_css', array( $this, 'verify_css_file_created_action' ), 10 );
		add_action( 'fl_builder_after_render_js', array( $this, 'verify_js_file_created_action' ), 10 );

		// Hook into AJAX layout response to ensure files exist before URLs are returned
		add_filter( 'fl_builder_ajax_layout_response', array( $this, 'ensure_ajax_assets_exist' ), 10, 1 );

		// Ensure cache directory exists when getting cache dir
		add_filter( 'fl_builder_get_cache_dir', array( $this, 'ensure_cache_directory_exists' ), 5, 1 );

		// Clear page cache when BB cache files are regenerated
		add_action( 'fl_builder_after_render_css', array( $this, 'clear_page_cache' ), 20 );
		add_action( 'fl_builder_after_render_js', array( $this, 'clear_page_cache' ), 20 );
		
		// Clear page cache when BB cache is cleared via admin
		add_action( 'fl_builder_cache_cleared', array( $this, 'clear_page_cache' ), 10 );
	}

	/**
	 * Add admin test page for development
	 *
	 * @return void
	 */
	public function add_test_page() {
		add_submenu_page(
			'tools.php',
			'BB Cache Helper Test',
			'BB Cache Helper Test',
			'manage_options',
			'bb-cache-helper-test',
			array( $this, 'render_test_page' )
		);
	}

	/**
	 * Render test page
	 *
	 * @return void
	 */
	public function render_test_page() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			echo '<div class="wrap"><h1>BB Cache Helper Test</h1><p>Beaver Builder is not active.</p></div>';
			return;
		}

		$cache_dir = FLBuilderModel::get_cache_dir();
		$cache_files = array();
		
		if ( $cache_dir ) {
			$css_files = glob( $cache_dir['path'] . '*.css' );
			$js_files = glob( $cache_dir['path'] . '*.js' );
			$cache_files = array_merge( $css_files ?: array(), $js_files ?: array() );
		}

		// Handle file deletion
		if ( isset( $_POST['delete_file'] ) && check_admin_referer( 'bb_cache_test_delete' ) ) {
			$file_to_delete = sanitize_text_field( $_POST['delete_file'] );
			$full_path = $cache_dir['path'] . basename( $file_to_delete );
			
			if ( file_exists( $full_path ) && strpos( $full_path, $cache_dir['path'] ) === 0 ) {
				if ( unlink( $full_path ) ) {
					echo '<div class="notice notice-success"><p>File deleted: ' . esc_html( basename( $file_to_delete ) ) . '</p></div>';
					// Refresh file list
					$css_files = glob( $cache_dir['path'] . '*.css' );
					$js_files = glob( $cache_dir['path'] . '*.js' );
					$cache_files = array_merge( $css_files ?: array(), $js_files ?: array() );
				} else {
					echo '<div class="notice notice-error"><p>Failed to delete file.</p></div>';
				}
			}
		}

		?>
		<div class="wrap">
			<h1>Beaver Builder Cache Helper - Test</h1>
			
			<div class="card" style="max-width: 800px;">
				<h2>Cache Directory Info</h2>
				<?php if ( $cache_dir ) : ?>
					<p><strong>Path:</strong> <code><?php echo esc_html( $cache_dir['path'] ); ?></code></p>
					<p><strong>URL:</strong> <code><?php echo esc_html( $cache_dir['url'] ); ?></code></p>
				<?php else : ?>
					<p>Could not get cache directory.</p>
				<?php endif; ?>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>Cache Files (<?php echo count( $cache_files ); ?>)</h2>
				<?php if ( empty( $cache_files ) ) : ?>
					<p>No cache files found. Create/edit a page with Beaver Builder and view it to generate cache files.</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Filename</th>
								<th>Size</th>
								<th>Modified</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cache_files as $file ) : 
								$basename = basename( $file );
								$size = filesize( $file );
								$modified = filemtime( $file );
							?>
								<tr>
									<td><code><?php echo esc_html( $basename ); ?></code></td>
									<td><?php echo esc_html( size_format( $size ) ); ?></td>
									<td><?php echo esc_html( date( 'Y-m-d H:i:s', $modified ) ); ?></td>
									<td>
										<form method="post" style="display: inline;">
											<?php wp_nonce_field( 'bb_cache_test_delete' ); ?>
											<input type="hidden" name="delete_file" value="<?php echo esc_attr( $basename ); ?>">
											<button type="submit" class="button button-small" onclick="return confirm('Delete this file? The plugin should recreate it when you visit the page.');">Delete</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>Testing Instructions</h2>
				<ol>
					<li><strong>Find a page with Beaver Builder content</strong>
						<ul>
							<li>Go to Pages/Posts and find one built with Beaver Builder</li>
							<li>Note the cache files listed above (they should match the post ID)</li>
						</ul>
					</li>
					<li><strong>Delete a cache file</strong>
						<ul>
							<li>Click "Delete" on a CSS or JS file above</li>
							<li>Or manually delete from: <code><?php echo esc_html( $cache_dir['path'] ?? 'N/A' ); ?></code></li>
						</ul>
					</li>
					<li><strong>Test recreation</strong>
						<ul>
							<li>Visit the page in the frontend (open in new tab)</li>
							<li>The plugin should automatically recreate the deleted file</li>
							<li>Refresh this page to verify the file was recreated</li>
						</ul>
					</li>
					<li><strong>Check browser console</strong>
						<ul>
							<li>Open browser DevTools (F12) → Network tab</li>
							<li>Look for the CSS/JS file requests</li>
							<li>Should return 200 (not 404) if plugin worked</li>
						</ul>
					</li>
				</ol>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
				<h2>Quick Test via Terminal</h2>
				<p>You can also test via command line:</p>
				<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;"># List cache files
ls -lh <?php echo esc_html( $cache_dir['path'] ?? 'wp-content/uploads/bb-plugin/cache/' ); ?>

# Delete a specific file (replace with actual filename)
rm <?php echo esc_html( $cache_dir['path'] ?? 'wp-content/uploads/bb-plugin/cache/' ); ?>123-layout.css

# Visit the page, then check if file was recreated
ls -lh <?php echo esc_html( $cache_dir['path'] ?? 'wp-content/uploads/bb-plugin/cache/' ); ?></pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Ensure cache files exist before they are enqueued
	 *
	 * @return void
	 */
	public function ensure_cache_files_exist() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return;
		}

		$post_id = FLBuilderModel::get_post_id();

		// Only check if we have a valid post ID
		if ( ! $post_id ) {
			return;
		}

		// Ensure cache directory exists
		$cache_dir = FLBuilderModel::get_cache_dir();
		if ( ! $cache_dir ) {
			return;
		}

		// Get asset info
		$asset_info = FLBuilderModel::get_asset_info();
		$enqueuemethod = FLBuilderModel::get_asset_enqueue_method();

		// Skip if using inline method
		if ( 'inline' === $enqueuemethod ) {
			return;
		}

		// Check and create CSS file if needed
		$this->ensure_asset_file_exists( $asset_info['css'], 'css', true );

		// Check and create JS file if needed
		$this->ensure_asset_file_exists( $asset_info['js'], 'js', true );
	}

	/**
	 * Ensure an asset file exists, creating it if necessary
	 *
	 * @param string $file_path The full path to the file
	 * @param string $type The asset type ('css' or 'js')
	 * @param bool $include_global Whether to include global assets
	 * @return void
	 */
	private function ensure_asset_file_exists( $file_path, $type = 'css', $include_global = true ) {
		// Skip if we've already checked this file
		if ( isset( self::$checked_assets[ $file_path ] ) ) {
			return;
		}

		// Mark as checked to prevent infinite loops
		self::$checked_assets[ $file_path ] = true;

		// Check if file exists and has content
		if ( fl_builder_filesystem()->file_exists( $file_path ) && fl_builder_filesystem()->filesize( $file_path ) > 0 ) {
			return;
		}

		// File doesn't exist or is empty, try to create it
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		// Ensure cache directory exists
		$cache_dir = FLBuilderModel::get_cache_dir();
		if ( ! $cache_dir || ! fl_builder_filesystem()->file_exists( $cache_dir['path'] ) ) {
			FLBuilderModel::get_cache_dir();
		}

		// Render the asset
		$render_method = 'render_' . $type;
		if ( method_exists( 'FLBuilder', $render_method ) ) {
			call_user_func_array( array( 'FLBuilder', $render_method ), array( $include_global ) );
		}

		// Verify file was created, retry once if needed
		if ( ! fl_builder_filesystem()->file_exists( $file_path ) || 0 === fl_builder_filesystem()->filesize( $file_path ) ) {
			// Retry once
			if ( method_exists( 'FLBuilder', $render_method ) ) {
				call_user_func_array( array( 'FLBuilder', $render_method ), array( $include_global ) );
			}
		}
	}

	/**
	 * Verify CSS file was created after rendering (action hook)
	 *
	 * @return void
	 */
	public function verify_css_file_created_action() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return;
		}

		$asset_info = FLBuilderModel::get_asset_info();
		$enqueuemethod = FLBuilderModel::get_asset_enqueue_method();

		// Only check if using file method
		if ( 'file' !== $enqueuemethod ) {
			return;
		}

		$css_path = $asset_info['css'];

		// Verify file exists and has content
		if ( ! fl_builder_filesystem()->file_exists( $css_path ) || 0 === fl_builder_filesystem()->filesize( $css_path ) ) {
			// File doesn't exist, try to render again
			$this->ensure_asset_file_exists( $css_path, 'css', true );
		}
	}

	/**
	 * Verify JS file was created after rendering (action hook)
	 *
	 * @return void
	 */
	public function verify_js_file_created_action() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return;
		}

		$asset_info = FLBuilderModel::get_asset_info();
		$enqueuemethod = FLBuilderModel::get_asset_enqueue_method();

		// Only check if using file method
		if ( 'file' !== $enqueuemethod ) {
			return;
		}

		$js_path = $asset_info['js'];

		// Verify file exists and has content
		if ( ! fl_builder_filesystem()->file_exists( $js_path ) || 0 === fl_builder_filesystem()->filesize( $js_path ) ) {
			// File doesn't exist, try to render again
			$this->ensure_asset_file_exists( $js_path, 'js', true );
		}
	}

	/**
	 * Ensure AJAX assets exist before returning URLs
	 *
	 * @param array $response The AJAX response array
	 * @return array
	 */
	public function ensure_ajax_assets_exist( $response ) {
		if ( ! isset( $response['assets'] ) || ! is_array( $response['assets'] ) ) {
			return $response;
		}

		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return $response;
		}

		$asset_info = FLBuilderModel::get_asset_info();
		$enqueuemethod = FLBuilderModel::get_asset_enqueue_method();

		// Only check if using file method
		if ( 'file' !== $enqueuemethod ) {
			return $response;
		}

		// Ensure CSS file exists
		if ( isset( $response['assets']['css'] ) && ! empty( $response['assets']['css'] ) ) {
			$css_path = $asset_info['css'];
			if ( ! fl_builder_filesystem()->file_exists( $css_path ) || 0 === fl_builder_filesystem()->filesize( $css_path ) ) {
				$this->ensure_asset_file_exists( $css_path, 'css', true );
				// Update the URL if file now exists
				if ( fl_builder_filesystem()->file_exists( $css_path ) && fl_builder_filesystem()->filesize( $css_path ) > 0 ) {
					$asset_ver = FLBuilderModel::get_asset_version();
					$response['assets']['css'] = $asset_info['css_url'] . '?ver=' . $asset_ver;
				} else {
					// Fallback to inline CSS
					$response['assets']['css'] = FLBuilder::render_css();
				}
			}
		}

		// Ensure JS file exists
		if ( isset( $response['assets']['js'] ) && ! empty( $response['assets']['js'] ) ) {
			$js_path = $asset_info['js'];
			if ( ! fl_builder_filesystem()->file_exists( $js_path ) || 0 === fl_builder_filesystem()->filesize( $js_path ) ) {
				$this->ensure_asset_file_exists( $js_path, 'js', true );
				// Update the URL if file now exists
				if ( fl_builder_filesystem()->file_exists( $js_path ) && fl_builder_filesystem()->filesize( $js_path ) > 0 ) {
					$asset_ver = FLBuilderModel::get_asset_version();
					$response['assets']['js'] = $asset_info['js_url'] . '?ver=' . $asset_ver;
				} else {
					// Fallback to inline JS
					$response['assets']['js'] = FLBuilder::render_js();
				}
			}
		}

		return $response;
	}

	/**
	 * Ensure cache directory exists when getting cache dir
	 *
	 * @param array $dir_info Cache directory info array
	 * @return array
	 */
	public function ensure_cache_directory_exists( $dir_info ) {
		if ( ! $dir_info || ! isset( $dir_info['path'] ) ) {
			return $dir_info;
		}

		// Ensure directory exists
		if ( ! fl_builder_filesystem()->file_exists( $dir_info['path'] ) ) {
			fl_builder_filesystem()->mkdir( $dir_info['path'] );
			
			// Add index.html for security
			if ( fl_builder_filesystem()->file_exists( $dir_info['path'] ) ) {
				$index_file = $dir_info['path'] . 'index.html';
				if ( ! fl_builder_filesystem()->file_exists( $index_file ) ) {
					fl_builder_filesystem()->file_put_contents( $index_file, '' );
				}
			}
		}

		return $dir_info;
	}

	/**
	 * Clear page cache when Beaver Builder cache files are regenerated
	 *
	 * @return void
	 */
	public function clear_page_cache() {
		BB_Plugin_Cache_Clearer::clear_all_cache();
	}
}

// Initialize the plugin
new BB_Plugin_Cache_Helper();

