<?php
/**
 * Sunrise.php for WordPress Domain Mapping
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

// Check if we're on the main site already
if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN == $_SERVER['HTTP_HOST']) {
    return;
}

global $wpdb, $current_blog, $current_site;

// Get domains table name
$domain_mapping_table = $wpdb->base_prefix . 'domain_mapping';

// Check for the current domain in the domain mapping table
$domain = sanitize_text_field($_SERVER['HTTP_HOST']);
$blog_id = $wpdb->get_var($wpdb->prepare(
    "SELECT blog_id FROM {$domain_mapping_table} WHERE domain = %s LIMIT 1",
    $domain
));

// If we found a mapped domain, override current_blog
if (!empty($blog_id)) {
    // Get the mapped blog details
    $mapped_blog = $wpdb->get_row("SELECT * FROM {$wpdb->blogs} WHERE blog_id = '{$blog_id}' LIMIT 1");

    if ($mapped_blog) {
        // Override current_blog
        $current_blog = $mapped_blog;

        // Also set the cookie domain to the current domain
        define('COOKIE_DOMAIN', $_SERVER['HTTP_HOST']);

        // Define the mapped domain constant
        define('MAPPED_DOMAIN', true);

        // Allow other plugins to know this is a mapped domain
        $GLOBALS['dm_domain'] = array(
            'original' => $current_blog->domain,
            'mapped' => $_SERVER['HTTP_HOST']
        );

        // Fix request URI for path sites
        if ($current_blog->path != '/' && ($current_blog->path != '/wp/' || strpos($_SERVER['REQUEST_URI'], '/wp/') === false)) {
            $current_blog->path = '/';
        }
    }
}
