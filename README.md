# Beaver Builder Cache Helper

## Overview

This plugin ensures that Beaver Builder cache files (CSS/JS) exist before they are accessed, preventing 404 errors when files are missing from the cache directory. It also automatically clears page cache when Beaver Builder cache files are regenerated to ensure users get updated files instead of stale cached pages.

## Why This Plugin Was Created

### The Problem

Beaver Builder generates CSS and JavaScript files on-demand and stores them in `wp-content/uploads/bb-plugin/cache/`. These files are referenced in the HTML output and enqueued via WordPress's asset system. However, several scenarios can cause these files to be missing when they're requested:

1. **CDN Integration**: When using CDN services (CloudFront, etc.) for media storage, there can be timing issues where:
   - Files are generated on the server but not yet synced to CDN
   - CDN cache serves stale 404 responses for missing files
   - Files are deleted during cache clearing but page cache still references them

2. **Caching Plugins**: Page caching plugins (WP Rocket, W3 Total Cache, etc.) can:
   - Cache HTML that references cache files before they're created
   - Serve stale cached pages that reference old/deleted cache files
   - Not automatically clear when Beaver Builder regenerates cache files

3. **Race Conditions**: In high-traffic scenarios:
   - Multiple requests can hit a page before cache files are generated
   - Files might be deleted while still being served from page cache

4. **Filesystem Issues**: 
   - Permission problems preventing file creation
   - Disk space issues
   - Network timeouts during file writes

### The Solution

This plugin addresses these issues by:

1. **Proactive File Creation**: Checks and creates cache files before they're enqueued (runs early on `wp_enqueue_scripts`)
2. **Post-Render Verification**: Verifies files were created after rendering and retries if needed
3. **Retry Logic**: Attempts to create files multiple times if initial creation fails
4. **Cache Invalidation**: Automatically clears page cache when Beaver Builder regenerates cache files
5. **Fallback Support**: Falls back to inline CSS/JS if file creation continues to fail

## Features

- ✅ Ensures cache files exist before enqueuing
- ✅ Verifies file creation after rendering
- ✅ Retry logic for failed file creation
- ✅ Automatic page cache clearing when BB cache is regenerated
- ✅ Support for 12+ caching plugins (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed, etc.)
- ✅ AJAX compatibility
- ✅ Fallback to inline assets if file creation fails

## Installation

1. Place the plugin in `wp-content/plugins/bb-plugin-cache-helper/`
2. Activate the plugin via WordPress Admin → Plugins
3. No configuration needed - it works automatically!

## Requirements

- WordPress 5.0+
- Beaver Builder plugin (any version)
- PHP 7.4+

## How It Works

### File Existence Checks

The plugin hooks into several WordPress and Beaver Builder actions:

- `wp_enqueue_scripts` (priority 5): Checks files exist before enqueuing
- `fl_builder_after_render_css`: Verifies CSS file was created
- `fl_builder_after_render_js`: Verifies JS file was created
- `fl_builder_ajax_layout_response`: Ensures files exist in AJAX responses

### Cache Clearing

When Beaver Builder regenerates cache files, the plugin automatically clears page cache for:

- WP Rocket
- W3 Total Cache
- WP Super Cache
- LiteSpeed Cache
- WP Fastest Cache
- Cache Enabler
- Comet Cache
- Hummingbird
- WP-Optimize
- Autoptimize
- Breeze
- Swift Performance
- Generic WordPress object cache

## Testing

The plugin includes a built-in test page to help you verify functionality and troubleshoot issues.

### Accessing the Test Page

1. Go to **Tools → BB Cache Helper Test** in WordPress admin
2. The page displays:
   - Cache directory path and URL
   - List of all Beaver Builder cache files (CSS/JS)
   - File sizes and modification dates
   - Delete buttons for each file

### Testing File Recreation

To test that the plugin correctly recreates missing cache files:

1. **Find a page with Beaver Builder content**
   - Note the cache files listed on the test page
   - Files are named like `{post-id}-layout.css` or `{post-id}-layout.js`

2. **Delete a cache file**
   - Click "Delete" on a CSS or JS file in the test page
   - Or manually delete from: `wp-content/uploads/bb-plugin/cache/`

3. **Visit the page in the frontend**
   - Open the page that uses that cache file in a new tab
   - The plugin should automatically recreate the deleted file

4. **Verify recreation**
   - Return to the test page and refresh
   - The deleted file should now appear again
   - Check browser DevTools (F12) → Network tab to ensure no 404 errors

### Testing via Command Line

You can also test via terminal:

```bash
# List cache files
ls -lh wp-content/uploads/bb-plugin/cache/

# Delete a specific file (replace with actual filename)
rm wp-content/uploads/bb-plugin/cache/123-layout.css

# Visit the page, then check if file was recreated
ls -lh wp-content/uploads/bb-plugin/cache/123-layout.css
```

## Supported Caching Plugins

The plugin automatically detects and clears cache for:

- **WP Rocket** - Most popular premium caching plugin
- **W3 Total Cache** - Comprehensive caching solution
- **WP Super Cache** - Simple, effective caching
- **LiteSpeed Cache** - Server-level caching
- **WP Fastest Cache** - Fast and lightweight
- **Cache Enabler** - Simple page caching
- **Comet Cache** - Advanced caching features
- **Hummingbird** - Performance optimization suite
- **WP-Optimize** - Database and cache optimization
- **Autoptimize** - Asset optimization
- **Breeze** - Cloudways caching solution
- **Swift Performance** - High-performance caching

## Technical Details

### File Structure

```
bb-plugin-cache-helper/
├── bb-plugin-cache-helper.php  # Main plugin file
├── includes/
│   └── class-cache-clearer.php  # Cache clearing logic
├── index.php                    # Security file
└── README.md                    # This file
```

### Hooks Used

- `wp_enqueue_scripts` - Early file existence check
- `fl_builder_after_render_css` - CSS file verification
- `fl_builder_after_render_js` - JS file verification
- `fl_builder_ajax_layout_response` - AJAX asset verification
- `fl_builder_get_cache_dir` - Cache directory verification
- `fl_builder_cache_cleared` - Cache clearing trigger

### Custom Hooks

Other plugins can hook into:

- `bb_plugin_cache_helper_clear_page_cache` - Fired when page cache is cleared

## Troubleshooting

### Files Still Not Being Created

1. Check file permissions on `wp-content/uploads/bb-plugin/cache/`
2. Verify disk space is available
3. Check server error logs for filesystem errors
4. Ensure Beaver Builder is active and working correctly

### Cache Not Clearing

1. Verify your caching plugin is supported (see list above)
2. Check if caching plugin is active
3. Try manually clearing cache from your caching plugin's admin panel

### Performance Concerns

The plugin is designed to be lightweight:
- Only runs when Beaver Builder is active
- Checks are cached to prevent infinite loops
- Minimal overhead on pages without Beaver Builder content

## Changelog

### Version 1.1.0
- Added automatic page cache clearing when BB cache files are regenerated
- Support for 12+ caching plugins
- Separated cache clearing logic into dedicated class
- Added admin test page (Tools → BB Cache Helper Test) for easy testing and troubleshooting

### Version 1.0.0
- Initial release
- File existence verification
- Retry logic for failed file creation
- AJAX compatibility

## Credits

Created by 4mation Technologies for the OnDeck WordPress site.

Inspired by similar solutions for S3 compatibility and cache management.

## License

This plugin is proprietary software developed for internal use.

