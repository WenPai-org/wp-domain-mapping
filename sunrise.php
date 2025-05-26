<?php
/**
 * Sunrise.php for WP Domain Mapping
 *
 * This file must be copied to wp-content/sunrise.php
 * Also, you must add "define('SUNRISE', 'on');" to wp-config.php
 * Make sure the SUNRISE definition appears before the last require_once in wp-config.php
 */

// Mark as loaded
define('SUNRISE_LOADED', true);

// Check if we're in WP multi-site mode
if (!defined('MULTISITE') || !MULTISITE) {
    return;
}

// Enable domain mapping
define('DOMAIN_MAPPING', 1);

// Don't process if we're in admin and on the original domain
if (is_admin() && isset($_SERVER['HTTP_HOST'])) {
    // Allow admin access from any domain - we'll handle this in the plugin itself
}

global $wpdb, $current_blog, $current_site;

// Check if tables exist before proceeding
if (!$wpdb) {
    return;
}

// Get domains table name
$domain_mapping_table = $wpdb->base_prefix . 'domain_mapping';

// Check if the domain mapping table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$domain_mapping_table}'") == $domain_mapping_table;
if (!$table_exists) {
    return;
}

// Check for the current domain in the domain mapping table
$domain = sanitize_text_field($_SERVER['HTTP_HOST']);
$blog_id = $wpdb->get_var($wpdb->prepare(
    "SELECT blog_id FROM {$domain_mapping_table} WHERE domain = %s LIMIT 1",
    $domain
));

// If we found a mapped domain, override current_blog
if (!empty($blog_id)) {
    // Get the mapped blog details
    $mapped_blog = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d LIMIT 1",
        $blog_id
    ));

    if ($mapped_blog) {
        // Override current_blog
        $current_blog = $mapped_blog;

        // Set cookie domain for the mapped domain (only if not defined in wp-config.php)
        if (!defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', $domain);
        }

        // Define the mapped domain constant
        define('MAPPED_DOMAIN', true);

        // Allow other plugins to know this is a mapped domain
        $GLOBALS['dm_domain'] = array(
            'original' => $current_blog->domain,
            'mapped' => $domain,
            'blog_id' => $blog_id
        );

        // Fix request URI for path sites
        if ($current_blog->path != '/' && ($current_blog->path != '/wp/' || strpos($_SERVER['REQUEST_URI'], '/wp/') === false)) {
            $current_blog->path = '/';
        }

        // Store original values for reference
        if (!defined('DM_ORIGINAL_DOMAIN')) {
            define('DM_ORIGINAL_DOMAIN', $mapped_blog->domain);
        }
        if (!defined('DM_ORIGINAL_PATH')) {
            define('DM_ORIGINAL_PATH', $mapped_blog->path);
        }
    }
}

// Function to check if current request is for admin area
function dm_is_admin_request() {
    if (!isset($_SERVER['REQUEST_URI'])) {
        return false;
    }

    $request_uri = $_SERVER['REQUEST_URI'];
    return (
        strpos($request_uri, '/wp-admin/') !== false ||
        strpos($request_uri, '/wp-login.php') !== false ||
        (defined('WP_ADMIN') && WP_ADMIN)
    );
}
