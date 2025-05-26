<?php
/**
 * Common functions for WP Domain Mapping plugin
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ensure URL has a protocol
 *
 * @param string $domain Domain name
 * @return string Domain with protocol
 */
function dm_ensure_protocol( $domain ) {
    if ( preg_match( '#^https?://#', $domain ) ) {
        return $domain;
    }
    return 'http://' . $domain;
}

/**
 * Clean domain name (remove protocol and trailing slash)
 * UPDATED: Improved domain cleaning function, preserves www prefix
 *
 * @param string $domain Domain name
 * @return string Cleaned domain
 */
function dm_clean_domain( $domain ) {
    // Remove protocol
    $domain = preg_replace( '#^https?://#', '', $domain );

    // Remove trailing slash
    $domain = rtrim( $domain, '/' );

    // Remove path if exists
    if ( strpos( $domain, '/' ) !== false ) {
        $domain = substr( $domain, 0, strpos( $domain, '/' ) );
    }

    // Convert to lowercase for consistency
    $domain = strtolower( $domain );

    // Convert IDN to ASCII (Punycode)
    if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^a-z0-9\-\.]/i', $domain ) ) {
        if (defined('INTL_IDNA_VARIANT_UTS46')) {
            // PHP 7.2+
            $ascii_domain = idn_to_ascii( $domain, 0, INTL_IDNA_VARIANT_UTS46 );
        } else {
            // PHP < 7.2
            $ascii_domain = idn_to_ascii( $domain );
        }

        // Only use converted result if conversion was successful
        if ($ascii_domain !== false) {
            $domain = $ascii_domain;
        }
    }

    return $domain;
}

/**
 * Validate a domain name
 * UPDATED: More flexible domain validation, supports www prefix and more valid formats
 *
 * @param string $domain The domain
 * @return bool True if valid
 */
function dm_validate_domain( $domain ) {
    // Remove possible protocol and path
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = trim($domain);

    // Check if empty
    if (empty($domain)) {
        return false;
    }

    // Check length limit (RFC 1035)
    if (strlen($domain) > 253) {
        return false;
    }

    // Check if contains only valid characters (letters, numbers, dots, hyphens)
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
        return false;
    }

    // Check if starts or ends with dot
    if (substr($domain, 0, 1) === '.' || substr($domain, -1) === '.') {
        return false;
    }

    // Check for consecutive dots
    if (strpos($domain, '..') !== false) {
        return false;
    }

    // Split domain into parts
    $parts = explode('.', $domain);

    // Need at least two parts (domain.tld)
    if (count($parts) < 2) {
        return false;
    }

    // Check each part
    foreach ($parts as $part) {
        // Each part cannot be empty
        if (empty($part)) {
            return false;
        }

        // Each part cannot exceed 63 characters (RFC 1035)
        if (strlen($part) > 63) {
            return false;
        }

        // Each part cannot start or end with hyphen
        if (substr($part, 0, 1) === '-' || substr($part, -1) === '-') {
            return false;
        }

        // Each part can only contain letters, numbers and hyphens
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $part)) {
            return false;
        }
    }

    // Check TLD (last part) validity
    $tld = end($parts);

    // TLD needs at least 2 characters and can only contain letters
    if (strlen($tld) < 2 || !preg_match('/^[a-zA-Z]+$/', $tld)) {
        return false;
    }

    return true;
}

/**
 * Check if two domains are the same (ignoring www prefix)
 *
 * @param string $domain1 First domain
 * @param string $domain2 Second domain
 * @return bool True if domains are essentially the same
 */
function dm_domains_are_equivalent( $domain1, $domain2 ) {
    // Remove www. prefix for comparison
    $clean1 = preg_replace( '/^www\./i', '', $domain1 );
    $clean2 = preg_replace( '/^www\./i', '', $domain2 );

    return strcasecmp( $clean1, $clean2 ) === 0;
}

/**
 * Display IDN warning message
 *
 * @return string Warning message
 */
function dm_idn_warning() {
    return sprintf(
        /* translators: %s: URL to punycode converter */
        wp_kses(
            __( 'International Domain Names should be in <a href="%s" target="_blank">punycode</a> format.', 'wp-domain-mapping' ),
            array( 'a' => array( 'href' => array(), 'target' => array() ) )
        ),
        'https://www.punycoder.com/'
    );
}

/**
 * Check if user is a site admin
 *
 * @return bool True if user is a site admin
 */
function dm_is_site_admin() {
    return current_user_can( 'manage_network' );
}

/**
 * Get domain mapping table names
 *
 * @return array Array of table names
 */
function dm_get_table_names() {
    global $wpdb;

    return array(
        'domains' => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS,
        'logins'  => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGINS,
        'logs'    => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGS,
    );
}

/**
 * Log domain mapping action
 *
 * @param string $action Action type
 * @param string $domain Domain name
 * @param int $blog_id Blog ID
 * @param int $user_id User ID (optional)
 */
function dm_log_action( $action, $domain, $blog_id, $user_id = null ) {
    global $wpdb;

    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $tables = dm_get_table_names();

    $wpdb->insert(
        $tables['logs'],
        array(
            'user_id' => $user_id,
            'action' => $action,
            'domain' => $domain,
            'blog_id' => $blog_id
        ),
        array( '%d', '%s', '%s', '%d' )
    );
}

/**
 * Get domain by name
 *
 * @param string $domain Domain name
 * @return object|null Domain object or null
 */
function dm_get_domain_by_name( $domain ) {
    global $wpdb;

    $tables = dm_get_table_names();

    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$tables['domains']} WHERE domain = %s",
        $domain
    ));
}

/**
 * Check if domain exists for another blog
 * UPDATED: More precise checking for domain conflicts
 *
 * @param string $domain Domain name
 * @param int $exclude_blog_id Blog ID to exclude from check (optional)
 * @return object|null Domain object if exists for another blog, null otherwise
 */
function dm_domain_exists_for_another_blog( $domain, $exclude_blog_id = 0 ) {
    global $wpdb;

    $tables = dm_get_table_names();

    $query = "SELECT * FROM {$tables['domains']} WHERE domain = %s";
    $params = array( $domain );

    if ( $exclude_blog_id > 0 ) {
        $query .= " AND blog_id != %d";
        $params[] = $exclude_blog_id;
    }

    return $wpdb->get_row( $wpdb->prepare( $query, $params ) );
}

/**
 * Get domains by blog ID
 *
 * @param int $blog_id Blog ID
 * @return array Array of domain objects
 */
function dm_get_domains_by_blog_id( $blog_id ) {
    global $wpdb;

    $tables = dm_get_table_names();

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$tables['domains']} WHERE blog_id = %d ORDER BY active DESC, domain ASC",
        $blog_id
    ));
}

/**
 * Add a new domain mapping
 *
 * @param int $blog_id Blog ID
 * @param string $domain Domain name
 * @param int $active Whether domain is primary (1) or not (0)
 * @return bool|int False on failure, insert ID on success
 */
function dm_add_domain( $blog_id, $domain, $active = 0 ) {
    global $wpdb;

    $tables = dm_get_table_names();
    $domain = dm_clean_domain( $domain );

    // Validate domain
    if ( ! dm_validate_domain( $domain ) ) {
        return false;
    }

    // Check if domain already exists
    if ( dm_get_domain_by_name( $domain ) ) {
        return false;
    }

    // If setting as primary, reset other domains
    if ( $active ) {
        $wpdb->update(
            $tables['domains'],
            array( 'active' => 0 ),
            array( 'blog_id' => $blog_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    // Insert new domain
    $result = $wpdb->insert(
        $tables['domains'],
        array(
            'blog_id' => $blog_id,
            'domain' => $domain,
            'active' => $active
        ),
        array( '%d', '%s', '%d' )
    );

    if ( $result ) {
        dm_log_action( 'add', $domain, $blog_id );
        dm_clear_domain_cache( $blog_id );
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Update domain mapping
 * UPDATED: Support changing domain name and blog_id
 *
 * @param string $domain Domain name (current)
 * @param int $blog_id Blog ID
 * @param int $active Whether domain is primary (1) or not (0)
 * @param string $new_domain New domain name (optional)
 * @return bool True on success, false on failure
 */
function dm_update_domain( $domain, $blog_id, $active, $new_domain = null ) {
    global $wpdb;

    $tables = dm_get_table_names();

    // If changing domain name
    if ( $new_domain && $new_domain !== $domain ) {
        $new_domain = dm_clean_domain( $new_domain );

        // Validate new domain
        if ( ! dm_validate_domain( $new_domain ) ) {
            return false;
        }

        // Check if new domain exists for another blog
        $existing = dm_domain_exists_for_another_blog( $new_domain, $blog_id );
        if ( $existing ) {
            return false;
        }
    }

    // If setting as primary, reset other domains
    if ( $active ) {
        $wpdb->update(
            $tables['domains'],
            array( 'active' => 0 ),
            array( 'blog_id' => $blog_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    // Prepare update data
    $data = array(
        'active' => $active,
        'blog_id' => $blog_id
    );
    $data_format = array( '%d', '%d' );

    if ( $new_domain && $new_domain !== $domain ) {
        $data['domain'] = $new_domain;
        $data_format[] = '%s';
    }

    // Update domain
    $result = $wpdb->update(
        $tables['domains'],
        $data,
        array( 'domain' => $domain ),
        $data_format,
        array( '%s' )
    );

    if ( $result !== false ) {
        dm_log_action( 'edit', $new_domain ?: $domain, $blog_id );
        dm_clear_domain_cache( $blog_id );
        return true;
    }

    return false;
}

/**
 * Delete domain mapping
 *
 * @param string $domain Domain name
 * @return bool True on success, false on failure
 */
function dm_delete_domain( $domain ) {
    global $wpdb;

    $tables = dm_get_table_names();

    // Get domain info for logging
    $domain_info = dm_get_domain_by_name( $domain );

    if ( ! $domain_info ) {
        return false;
    }

    // Delete domain
    $result = $wpdb->delete(
        $tables['domains'],
        array( 'domain' => $domain ),
        array( '%s' )
    );

    if ( $result ) {
        dm_log_action( 'delete', $domain, $domain_info->blog_id );
        dm_clear_domain_cache( $domain_info->blog_id );
        return true;
    }

    return false;
}

/**
 * Save health check result
 *
 * @param string $domain Domain name
 * @param array $result Health check result
 */
function dm_save_health_result( $domain, $result ) {
    $health_results = get_site_option( 'dm_domain_health_results', array() );
    $domain_key = md5( $domain );

    $health_results[$domain_key] = $result;
    update_site_option( 'dm_domain_health_results', $health_results );
}

/**
 * Get health check result
 *
 * @param string $domain Domain name
 * @return array|null Health check result or null
 */
function dm_get_health_result( $domain ) {
    $health_results = get_site_option( 'dm_domain_health_results', array() );
    $domain_key = md5( $domain );

    return isset( $health_results[$domain_key] ) ? $health_results[$domain_key] : null;
}

/**
 * Check if domain has all health checks passing
 *
 * @param string $domain Domain name
 * @return bool True if all health checks pass
 */
function dm_is_domain_healthy( $domain ) {
    $health_result = dm_get_health_result( $domain );

    if ( ! $health_result ) {
        return false;
    }

    // Check all three criteria: DNS, SSL, and Accessibility
    $dns_ok = isset( $health_result['dns_status'] ) && $health_result['dns_status'] === 'success';
    $ssl_ok = isset( $health_result['ssl_valid'] ) && $health_result['ssl_valid'] === true;
    $accessible_ok = isset( $health_result['accessible'] ) && $health_result['accessible'] === true;

    return $dns_ok && $ssl_ok && $accessible_ok;
}

/**
 * Format action name for display
 *
 * @param string $action Action name
 * @return string Formatted action name
 */
function dm_format_action_name( $action ) {
    switch ( $action ) {
        case 'add':
            return __( 'Added', 'wp-domain-mapping' );
        case 'edit':
            return __( 'Updated', 'wp-domain-mapping' );
        case 'delete':
            return __( 'Deleted', 'wp-domain-mapping' );
        case 'import':
            return __( 'Imported', 'wp-domain-mapping' );
        default:
            return ucfirst( $action );
    }
}

/**
 * Enhanced caching functions for better performance
 */

// Cache functions - IMPROVED
function dm_get_domains_by_blog_id_cached($blog_id) {
    $cache_key = 'dm_domains_' . $blog_id;
    $cache_group = 'domain_mapping';

    $domains = wp_cache_get($cache_key, $cache_group);

    if (false === $domains) {
        $domains = dm_get_domains_by_blog_id($blog_id);
        // Cache for 1 hour, or until manually cleared
        wp_cache_set($cache_key, $domains, $cache_group, HOUR_IN_SECONDS);
    }

    return $domains;
}

// Clear cache function - IMPROVED
function dm_clear_domain_cache($blog_id = null) {
    $cache_group = 'domain_mapping';

    if ($blog_id) {
        // Clear specific blog cache
        wp_cache_delete('dm_domains_' . $blog_id, $cache_group);
        wp_cache_delete('dm_domain_exists_' . $blog_id, $cache_group);
    } else {
        // Clear all domain mapping caches
        wp_cache_flush_group($cache_group);
    }

    // Also clear object cache if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group($cache_group);
    }
}

/**
 * Cached domain existence check
 */
function dm_domain_exists_cached($domain) {
    $cache_key = 'dm_domain_exists_' . md5($domain);
    $cache_group = 'domain_mapping';

    $exists = wp_cache_get($cache_key, $cache_group);

    if (false === $exists) {
        $exists = dm_get_domain_by_name($domain);
        // Cache for 30 minutes
        wp_cache_set($cache_key, $exists, $cache_group, 30 * MINUTE_IN_SECONDS);
    }

    return $exists;
}

/**
 * Batch clear cache for multiple blogs
 */
function dm_clear_multiple_domain_cache($blog_ids) {
    if (!is_array($blog_ids)) {
        $blog_ids = array($blog_ids);
    }

    foreach ($blog_ids as $blog_id) {
        dm_clear_domain_cache($blog_id);
    }
}

/**
 * Health check results caching
 */
function dm_get_health_result_cached($domain) {
    $cache_key = 'dm_health_' . md5($domain);
    $cache_group = 'domain_mapping_health';

    $result = wp_cache_get($cache_key, $cache_group);

    if (false === $result) {
        $result = dm_get_health_result($domain);
        if ($result) {
            // Cache for 1 hour
            wp_cache_set($cache_key, $result, $cache_group, HOUR_IN_SECONDS);
        }
    }

    return $result;
}

/**
 * Clear health check cache
 */
function dm_clear_health_cache($domain = null) {
    $cache_group = 'domain_mapping_health';

    if ($domain) {
        wp_cache_delete('dm_health_' . md5($domain), $cache_group);
    } else {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($cache_group);
        }
    }
}
/**
 * Enhanced error handling and logging functions
 */

/**
 * Log domain mapping errors
 */
function dm_log_error($message, $context = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $log_message = '[WP Domain Mapping] ' . $message;

    if (!empty($context)) {
        $log_message .= ' Context: ' . json_encode($context);
    }

    error_log($log_message);
}

/**
 * Log domain mapping info
 */
function dm_log_info($message, $context = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $log_message = '[WP Domain Mapping INFO] ' . $message;

    if (!empty($context)) {
        $log_message .= ' Context: ' . json_encode($context);
    }

    error_log($log_message);
}

/**
 * Safe database query execution
 */
function dm_safe_query($query, $params = array()) {
    global $wpdb;

    try {
        if (!empty($params)) {
            $prepared = $wpdb->prepare($query, $params);
        } else {
            $prepared = $query;
        }

        $result = $wpdb->query($prepared);

        if ($result === false) {
            dm_log_error('Database query failed', array(
                'query' => $query,
                'params' => $params,
                'error' => $wpdb->last_error
            ));
        }

        return $result;
    } catch (Exception $e) {
        dm_log_error('Database exception', array(
            'query' => $query,
            'params' => $params,
            'exception' => $e->getMessage()
        ));
        return false;
    }
}

/**
 * Enhanced domain validation with error details
 */
function dm_validate_domain_with_errors($domain) {
    $errors = array();

    // Remove possible protocol and path
    $original_domain = $domain;
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = trim($domain);

    // Check if empty
    if (empty($domain)) {
        $errors[] = __('Domain cannot be empty', 'wp-domain-mapping');
        return array('valid' => false, 'errors' => $errors, 'domain' => $domain);
    }

    // Check length limit (RFC 1035)
    if (strlen($domain) > 253) {
        $errors[] = __('Domain name too long (max 253 characters)', 'wp-domain-mapping');
    }

    // Check if contains only valid characters
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
        $errors[] = __('Domain contains invalid characters', 'wp-domain-mapping');
    }

    // Check if starts or ends with dot
    if (substr($domain, 0, 1) === '.' || substr($domain, -1) === '.') {
        $errors[] = __('Domain cannot start or end with a dot', 'wp-domain-mapping');
    }

    // Check for consecutive dots
    if (strpos($domain, '..') !== false) {
        $errors[] = __('Domain cannot contain consecutive dots', 'wp-domain-mapping');
    }

    // Split domain into parts
    $parts = explode('.', $domain);

    // Need at least two parts
    if (count($parts) < 2) {
        $errors[] = __('Domain must have at least two parts (e.g., domain.com)', 'wp-domain-mapping');
    }

    // Check each part
    foreach ($parts as $i => $part) {
        if (empty($part)) {
            $errors[] = sprintf(__('Domain part %d is empty', 'wp-domain-mapping'), $i + 1);
            continue;
        }

        if (strlen($part) > 63) {
            $errors[] = sprintf(__('Domain part "%s" too long (max 63 characters)', 'wp-domain-mapping'), $part);
        }

        if (substr($part, 0, 1) === '-' || substr($part, -1) === '-') {
            $errors[] = sprintf(__('Domain part "%s" cannot start or end with hyphen', 'wp-domain-mapping'), $part);
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $part)) {
            $errors[] = sprintf(__('Domain part "%s" contains invalid characters', 'wp-domain-mapping'), $part);
        }
    }

    // Check TLD
    if (count($parts) >= 2) {
        $tld = end($parts);
        if (strlen($tld) < 2 || !preg_match('/^[a-zA-Z]+$/', $tld)) {
            $errors[] = __('Invalid top-level domain (TLD)', 'wp-domain-mapping');
        }
    }

    $is_valid = empty($errors);

    // Log validation attempts if debugging
    if (!$is_valid) {
        dm_log_info('Domain validation failed', array(
            'original' => $original_domain,
            'cleaned' => $domain,
            'errors' => $errors
        ));
    }

    return array(
        'valid' => $is_valid,
        'errors' => $errors,
        'domain' => $domain,
        'original' => $original_domain
    );
}

/**
 * Enhanced health check with error handling
 */
function dm_check_domain_health_safe($domain) {
    try {
        $tools = WP_Domain_Mapping_Tools::get_instance();
        $result = $tools->check_domain_health($domain);

        dm_log_info('Health check completed', array(
            'domain' => $domain,
            'result' => $result
        ));

        return $result;
    } catch (Exception $e) {
        dm_log_error('Health check failed', array(
            'domain' => $domain,
            'exception' => $e->getMessage()
        ));

        return array(
            'domain' => $domain,
            'last_check' => current_time('mysql'),
            'error' => $e->getMessage(),
            'dns_status' => 'error',
            'ssl_valid' => false,
            'accessible' => false
        );
    }
}
