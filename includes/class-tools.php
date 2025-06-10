<?php
/**
 * Tools functionality for WP Domain Mapping plugin
 * Integrates health check, import/export, and site ID display features
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP Domain Mapping Tools Class
 */
class WP_Domain_Mapping_Tools {

    /**
     * Class instance
     *
     * @var WP_Domain_Mapping_Tools
     */
    private static $instance = null;

    /**
     * Database table names
     *
     * @var array
     */
    private $tables = array();

    /**
     * Get class instance
     *
     * @return WP_Domain_Mapping_Tools
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->tables = dm_get_table_names();
        $this->init();
    }

    /**
     * Initialize tools functionality
     */
    private function init() {
        // Setup hooks
        $this->setup_hooks();

        // Initialize cron for health checks
        $this->initialize_cron();
    }

    /**
     * Setup hooks
     */
    private function setup_hooks() {
        // Add Site ID to toolbar (only in multisite)
        if ( is_multisite() ) {
            add_action( 'admin_bar_menu', array( $this, 'add_site_id_to_toolbar' ), 100 );

            // Add Site ID column to sites list
            add_filter( 'manage_sites-network_columns', array( $this, 'add_site_id_column' ), 20 );
            add_action( 'manage_sites_custom_column', array( $this, 'display_site_id_column' ), 10, 2 );
            add_action( 'admin_print_styles', array( $this, 'site_id_column_style' ) );

            // Add SSL and Reachable columns
            add_filter( 'manage_sites-network_columns', array( $this, 'add_ssl_reachable_columns' ), 25 );
            add_filter( 'wpmu_blogs_columns', array( $this, 'add_ssl_reachable_columns' ), 25 );
            add_action( 'manage_blogs_custom_column', array( $this, 'display_ssl_reachable_column' ), 15, 3 );
            add_action( 'manage_sites_custom_column', array( $this, 'display_ssl_reachable_column' ), 15, 3 );
        }

        // AJAX handlers
        add_action( 'wp_ajax_dm_check_domain_health', array( $this, 'ajax_check_domain_health' ) );
        add_action( 'wp_ajax_dm_check_domain_health_batch', array( $this, 'ajax_check_domain_health_batch' ) );
        add_action( 'wp_ajax_dm_import_csv', array( $this, 'ajax_import_csv' ) );
        add_action( 'wp_ajax_dm_stop_health_check', array( $this, 'ajax_stop_health_check' ) );

        // Scheduled tasks
        add_action( 'dm_domain_health_check', array( $this, 'scheduled_health_check' ) );
        add_action( 'dm_domain_health_check_batch', array( $this, 'process_health_check_batch' ) );
        add_action( 'dm_cleanup_stuck_health_check', array( $this, 'cleanup_stuck_health_check' ) );

        // Admin init actions
        add_action( 'admin_init', array( $this, 'handle_export' ) );
        add_action( 'admin_init', array( $this, 'handle_health_manual_check' ) );
        add_action( 'admin_init', array( $this, 'handle_health_settings_save' ) );

        // Add debug hooks
        add_action( 'admin_notices', array( $this, 'show_cron_debug_info' ) );

        // Add manual trigger cron hook (only for debugging)
        if ( isset( $_GET['dm_test_cron'] ) && current_user_can( 'manage_network' ) ) {
            add_action( 'admin_init', array( $this, 'test_cron_execution' ) );
        }

        // Add cleanup hook - check for stuck health checks every hour
        if ( ! wp_next_scheduled( 'dm_cleanup_stuck_health_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'dm_cleanup_stuck_health_check' );
        }
    }

    /**
     * Initialize cron for health checks
     */
    private function initialize_cron() {
        // Register activation/deactivation hooks
        register_activation_hook( WP_DOMAIN_MAPPING_BASENAME, array( $this, 'schedule_health_check' ) );
        register_deactivation_hook( WP_DOMAIN_MAPPING_BASENAME, array( $this, 'unschedule_health_check' ) );
    }

    // ========================================
    // Site ID Display Functions
    // ========================================

    /**
     * Add site ID to WordPress toolbar
     *
     * @param WP_Admin_Bar $admin_bar WordPress toolbar object
     */
    public function add_site_id_to_toolbar( $admin_bar ) {
        // Only show to admins or network admins
        if ( ! current_user_can( 'manage_options' ) && ! is_super_admin() ) {
            return;
        }

        $blog_id = get_current_blog_id();

        $admin_bar->add_menu( array(
            'id'    => 'wp-site-ID',
            'title' => sprintf( __( 'Site ID: %d', 'wp-domain-mapping' ), $blog_id ),
            'href'  => is_super_admin() ? esc_url( network_admin_url( 'site-info.php?id=' . $blog_id ) ) : '#',
            'meta'  => array(
                'title' => is_super_admin() ? __( 'Edit this site', 'wp-domain-mapping' ) : __( 'Current site ID', 'wp-domain-mapping' ),
                'class' => 'dm-site-id-menu'
            ),
        ) );
    }

    /**
     * Add site ID column to sites list
     *
     * @param array $columns Current columns
     * @return array Modified columns
     */
    public function add_site_id_column( $columns ) {
        // Add site ID column after first column
        $columns = array_slice( $columns, 0, 1, true ) +
                  array( 'dm_site_id' => __( 'Site ID', 'wp-domain-mapping' ) ) +
                  array_slice( $columns, 1, count( $columns ) - 1, true );

        return $columns;
    }

    /**
     * Display site ID column content
     *
     * @param string $column Column name
     * @param int $blog_id Site ID
     */
    public function display_site_id_column( $column, $blog_id ) {
        if ( 'dm_site_id' == $column ) {
            echo '<span class="dm-site-id-badge">' . esc_html( $blog_id ) . '</span>';
        }
    }

    /**
     * Add SSL and Reachable columns
     */
    public function add_ssl_reachable_columns( $columns ) {
        $new_columns = array();
        $ssl_reachable_added = false;

        foreach ( $columns as $key => $value ) {
            // First add the current column
            $new_columns[$key] = $value;

            // Insert SSL and Reachable columns after the Site ID column
            if ( $key === 'dm_site_id' && ! $ssl_reachable_added ) {
                $new_columns['ssl_status'] = __( 'SSL', 'wp-domain-mapping' );
                $new_columns['reachable_status'] = __( 'Reachable', 'wp-domain-mapping' );
                $ssl_reachable_added = true;
            }
        }

        // If Site ID column wasn't found but columns need to be added
        if ( ! $ssl_reachable_added ) {
            // Insert after checkbox (cb) column, before everything else
            $final_columns = array();
            foreach ( $new_columns as $key => $value ) {
                $final_columns[$key] = $value;
                if ( $key === 'cb' && ! $ssl_reachable_added ) {
                    $final_columns['ssl_status'] = __( 'SSL', 'wp-domain-mapping' );
                    $final_columns['reachable_status'] = __( 'Reachable', 'wp-domain-mapping' );
                    $ssl_reachable_added = true;
                }
            }
            return $final_columns;
        }

        return $new_columns;
    }

    /**
     * Display SSL and Reachable column content
     */
    public function display_ssl_reachable_column( $column, $blog_id ) {
        global $wpdb;

        if ( $column == 'ssl_status' ) {
            // Check if site uses HTTPS
            $site_url = get_blog_option( $blog_id, 'siteurl', '' );
            if ( strpos( $site_url, 'https://' ) === 0 ) {
                echo '<span class="dashicons dashicons-lock" style="color: #46b450;" title="' . esc_attr__( 'SSL Enabled', 'wp-domain-mapping' ) . '"></span>';
            } else {
                echo '<span class="dashicons dashicons-unlock" style="color: #999;" title="' . esc_attr__( 'No SSL', 'wp-domain-mapping' ) . '"></span>';
            }
        }

        if ( $column == 'reachable_status' ) {
            // Check if this is the main site
            if ( is_main_site( $blog_id ) ) {
                // Main site is always reachable if we can access the admin
                echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'Main site is accessible', 'wp-domain-mapping' ) . '"></span>';
                return;
            }

            // Get primary domain for this blog
            $domain = $wpdb->get_var( $wpdb->prepare(
                "SELECT domain FROM {$this->tables['domains']} WHERE blog_id = %d AND active = 1 LIMIT 1",
                $blog_id
            ));

            if ( ! $domain ) {
                // No mapped domain, check original domain
                $blog_details = get_blog_details( $blog_id );
                if ( $blog_details ) {
                    $domain = $blog_details->domain;
                }
            }

            if ( $domain ) {
                $health_result = dm_get_health_result( $domain );

                if ( $health_result && isset( $health_result['accessible'] ) ) {
                    if ( $health_result['accessible'] ) {
                        echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'Site is accessible', 'wp-domain-mapping' ) . '"></span>';
                    } else {
                        echo '<span class="dashicons dashicons-warning" style="color: #dc3232;" title="' . esc_attr__( 'Site is not accessible', 'wp-domain-mapping' ) . '"></span>';
                    }
                } else {
                    echo '<span class="dashicons dashicons-minus" style="color: #999;" title="' . esc_attr__( 'Not checked', 'wp-domain-mapping' ) . '"></span>';
                }
            } else {
                echo '<span class="dashicons dashicons-minus" style="color: #999;" title="' . esc_attr__( 'No domain', 'wp-domain-mapping' ) . '"></span>';
            }
        }
    }

    /**
     * Add custom styles for site ID column
     */
    public function site_id_column_style() {
        if ( 'sites-network' == get_current_screen()->id ) {
            ?>
            <style type="text/css">
                th#dm_site_id { width: 3.5em; }
                th#ssl_status { width: 3em; }
                th#reachable_status { width: 5em; }
                .dm-site-id-badge {
                    display: inline-block;
                    background: #2271b1;
                    color: #fff;
                    font-weight: 500;
                    padding: 0 5px;
                    border-radius: 3px;
                    font-size: 12px;
                }
            </style>
            <?php
        }
    }

    // ========================================
    // Health Check Functions - IMPROVED
    // ========================================

    /**
     * Schedule health check - improved version
     */
    public function schedule_health_check() {
        // First clear existing schedules
        $this->unschedule_health_check();

        // Check if setting is enabled
        if ( ! get_site_option( 'dm_health_check_enabled', true ) ) {
            return;
        }

        // Set next execution time (tomorrow at 2:00 AM)
        $next_run = strtotime( 'tomorrow 2:00 AM' );

        // Schedule event
        $scheduled = wp_schedule_event( $next_run, 'daily', 'dm_domain_health_check' );

        // Log scheduling status
        if ( $scheduled !== false ) {
            error_log( 'WP Domain Mapping: Health check scheduled for ' . date( 'Y-m-d H:i:s', $next_run ) );
        } else {
            error_log( 'WP Domain Mapping: Failed to schedule health check' );
        }

        // Also set an option to track scheduling status
        update_site_option( 'dm_health_check_scheduled', array(
            'scheduled_at' => current_time( 'timestamp' ),
            'next_run' => $next_run,
            'status' => $scheduled !== false ? 'scheduled' : 'failed'
        ) );
    }

    /**
     * Unschedule health check - improved version
     */
    public function unschedule_health_check() {
        // Clear main health check
        wp_clear_scheduled_hook( 'dm_domain_health_check' );

        // Clear batch processing health check
        wp_clear_scheduled_hook( 'dm_domain_health_check_batch' );

        // Clear cleanup hook
        wp_clear_scheduled_hook( 'dm_cleanup_stuck_health_check' );

        // Clear scheduling status option
        delete_site_option( 'dm_health_check_scheduled' );

        error_log( 'WP Domain Mapping: Health check unscheduled' );
    }

    /**
     * Handle manual health check
     */
    public function handle_health_manual_check() {
        if ( isset( $_POST['dm_manual_health_check'] ) && $_POST['dm_manual_health_check'] ) {
            // Verify nonce
            if ( ! isset( $_POST['dm_manual_health_check_nonce'] ) || ! wp_verify_nonce( $_POST['dm_manual_health_check_nonce'], 'dm_manual_health_check' ) ) {
                wp_die( __( 'Security check failed.', 'wp-domain-mapping' ) );
            }

            // Check permissions
            if ( ! current_user_can( 'manage_network' ) ) {
                wp_die( __( 'You do not have sufficient permissions to perform this action.', 'wp-domain-mapping' ) );
            }

            // Clear any existing progress data
            delete_site_option( 'dm_health_check_queue' );
            delete_site_option( 'dm_health_check_progress' );

            // Start batch processing
            $started = $this->start_health_check_batch();

            if ( $started ) {
                wp_redirect( add_query_arg( array(
                    'page' => 'domain-mapping',
                    'tab' => 'health',
                    'checking' => 1
                ), network_admin_url( 'settings.php' ) ) );
            } else {
                wp_redirect( add_query_arg( array(
                    'page' => 'domain-mapping',
                    'tab' => 'health',
                    'error' => 'no_domains'
                ), network_admin_url( 'settings.php' ) ) );
            }
            exit;
        }
    }

    /**
     * Handle health settings save
     */
    public function handle_health_settings_save() {
        if ( isset( $_POST['dm_health_settings'] ) && $_POST['dm_health_settings'] ) {
            // Verify nonce
            if ( ! isset( $_POST['dm_health_settings_nonce'] ) || ! wp_verify_nonce( $_POST['dm_health_settings_nonce'], 'dm_health_settings' ) ) {
                wp_die( __( 'Security check failed.', 'wp-domain-mapping' ) );
            }

            // Check permissions
            if ( ! current_user_can( 'manage_network' ) ) {
                wp_die( __( 'You do not have sufficient permissions to perform this action.', 'wp-domain-mapping' ) );
            }

            // Save settings
            $health_check_enabled = isset( $_POST['health_check_enabled'] ) ? (bool) $_POST['health_check_enabled'] : false;
            $health_notifications_enabled = isset( $_POST['health_notifications_enabled'] ) ? (bool) $_POST['health_notifications_enabled'] : false;
            $notification_email = isset( $_POST['notification_email'] ) ? sanitize_email( $_POST['notification_email'] ) : '';
            $ssl_expiry_threshold = isset( $_POST['ssl_expiry_threshold'] ) ? intval( $_POST['ssl_expiry_threshold'] ) : 14;
            $batch_size = isset( $_POST['health_check_batch_size'] ) ? intval( $_POST['health_check_batch_size'] ) : 5;

            update_site_option( 'dm_health_check_enabled', $health_check_enabled );
            update_site_option( 'dm_health_notifications_enabled', $health_notifications_enabled );
            update_site_option( 'dm_notification_email', $notification_email );
            update_site_option( 'dm_ssl_expiry_threshold', $ssl_expiry_threshold );
            update_site_option( 'dm_health_check_batch_size', max( 3, min( 20, $batch_size ) ) );

            // If auto check is enabled, ensure cron is set
            if ( $health_check_enabled ) {
                $this->schedule_health_check();
            } else {
                $this->unschedule_health_check();
            }

            // Redirect back to health page
            wp_redirect( add_query_arg( array( 'page' => 'domain-mapping', 'tab' => 'health', 'settings-updated' => 1 ), network_admin_url( 'settings.php' ) ) );
            exit;
        }
    }

    /**
     * AJAX handler for domain health check
     */
    public function ajax_check_domain_health() {
        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'wp-domain-mapping' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dm_check_domain_health' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'wp-domain-mapping' ) );
        }

        // Get domain
        $domain = isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '';
        if ( empty( $domain ) ) {
            wp_send_json_error( __( 'No domain specified.', 'wp-domain-mapping' ) );
        }

        // Check if this domain belongs to the main site
        $main_site = get_site( get_main_site_id() );
        if ( $main_site && $domain === $main_site->domain ) {
            // Return success for main site without checking
            $result = array(
                'domain' => $domain,
                'last_check' => current_time( 'mysql' ),
                'dns_status' => 'success',
                'dns_message' => __( 'Main site is always accessible', 'wp-domain-mapping' ),
                'resolved_ip' => '',
                'ssl_valid' => strpos( home_url(), 'https://' ) === 0,
                'ssl_expiry' => '',
                'accessible' => true,
                'response_code' => 200
            );

            dm_save_health_result( $domain, $result );
            wp_send_json_success( $result );
            return;
        }

        // Run health check
        $result = $this->check_domain_health( $domain );

        // Save result
        dm_save_health_result( $domain, $result );

        // Return result
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for batch domain health check
     */
    public function ajax_check_domain_health_batch() {
        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'wp-domain-mapping' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dm_check_domain_health_batch' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'wp-domain-mapping' ) );
        }

        // Check if there's a health check in progress
        $progress = get_site_option( 'dm_health_check_progress', false );
        $queue = get_site_option( 'dm_health_check_queue', array() );

        if ( $progress === false && empty( $queue ) ) {
            wp_send_json_success( array(
                'complete' => true,
                'message' => __( 'No health check in progress.', 'wp-domain-mapping' )
            ) );
            return;
        }

        // Process next batch
        $result = $this->process_health_check_batch( true );

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler to stop health check
     */
    public function ajax_stop_health_check() {
        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'wp-domain-mapping' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dm_stop_health_check' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'wp-domain-mapping' ) );
        }

        // Clear health check progress data
        delete_site_option( 'dm_health_check_queue' );
        delete_site_option( 'dm_health_check_progress' );

        // Clear related cron tasks
        wp_clear_scheduled_hook( 'dm_domain_health_check_batch' );

        error_log( 'WP Domain Mapping: Health check stopped by user' );

        wp_send_json_success( __( 'Health check stopped successfully.', 'wp-domain-mapping' ) );
    }

    /**
     * Scheduled health check - improved version
     */
    public function scheduled_health_check() {
        // Log execution start
        error_log( 'WP Domain Mapping: Starting scheduled health check at ' . current_time( 'mysql' ) );

        // Check if health checks are enabled
        if ( ! get_site_option( 'dm_health_check_enabled', true ) ) {
            error_log( 'WP Domain Mapping: Health check is disabled, skipping' );
            return;
        }

        // Update last execution time
        update_site_option( 'dm_last_health_check', current_time( 'timestamp' ) );

        // Start batch processing
        $this->start_health_check_batch();

        error_log( 'WP Domain Mapping: Scheduled health check batch started' );
    }

    /**
     * Start batch health check processing
     */
    private function start_health_check_batch() {
        global $wpdb;

        // Clear any existing progress data
        delete_site_option( 'dm_health_check_queue' );
        delete_site_option( 'dm_health_check_progress' );

        // Get all domains to check
        $domains = array();

        // Get mapped domains
        $mapped_domains = $wpdb->get_col( "SELECT domain FROM {$this->tables['domains']}" );
        if ( $mapped_domains ) {
            $domains = array_merge( $domains, $mapped_domains );
        }

        // Add original domains for sites without mapped domains (excluding main site)
        $sites = get_sites( array(
            'number' => 0,
            'site__not_in' => array( get_main_site_id() ) // Exclude main site
        ) );

        foreach ( $sites as $site ) {
            // Check if this site has a mapped domain
            $has_mapped = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['domains']} WHERE blog_id = %d",
                $site->blog_id
            ));

            // If no mapped domain, add original domain
            if ( ! $has_mapped ) {
                $domains[] = $site->domain;
            }
        }

        // Remove duplicates
        $domains = array_unique( $domains );

        if ( empty( $domains ) ) {
            error_log( 'WP Domain Mapping: No domains found for health check' );
            return false;
        }

        // Store domains in queue
        update_site_option( 'dm_health_check_queue', $domains );
        update_site_option( 'dm_health_check_progress', array(
            'total' => count( $domains ),
            'processed' => 0,
            'started' => current_time( 'mysql' ),
            'issues' => array()
        ) );

        // Schedule first batch
        wp_schedule_single_event( time() + 1, 'dm_domain_health_check_batch' );

        error_log( 'WP Domain Mapping: Health check batch started with ' . count( $domains ) . ' domains' );
        return true;
    }

    /**
     * Process health check batch
     *
     * @param bool $return_data Whether to return data for AJAX
     * @return array|void
     */
    public function process_health_check_batch( $return_data = false ) {
        $queue = get_site_option( 'dm_health_check_queue', array() );
        $progress = get_site_option( 'dm_health_check_progress', array(
            'total' => 0,
            'processed' => 0,
            'started' => current_time( 'mysql' ),
            'issues' => array()
        ) );

        // If queue is empty, complete the process
        if ( empty( $queue ) ) {
            // Send notification if there are issues and notifications are enabled
            if ( ! empty( $progress['issues'] ) && get_site_option( 'dm_health_notifications_enabled', true ) ) {
                $this->send_health_notification( $progress['issues'] );
            }

            // Clean up
            delete_site_option( 'dm_health_check_queue' );
            delete_site_option( 'dm_health_check_progress' );

            // Update last check time
            update_site_option( 'dm_last_health_check', current_time( 'timestamp' ) );

            if ( $return_data ) {
                return array(
                    'complete' => true,
                    'total' => isset($progress['total']) ? $progress['total'] : 0,
                    'processed' => isset($progress['processed']) ? $progress['processed'] : 0,
                    'message' => __( 'Health check completed successfully.', 'wp-domain-mapping' )
                );
            }
            return;
        }

        // Get batch size
        $batch_size = get_site_option( 'dm_health_check_batch_size', 5 ); // Reduced default batch size

        // Process batch
        $batch = array_splice( $queue, 0, $batch_size );
        $processed_in_batch = 0;

        // Get main site domain to skip it
        $main_site = get_site( get_main_site_id() );
        $main_domain = $main_site ? $main_site->domain : '';

        foreach ( $batch as $domain ) {
            // Skip main site domain
            if ( $domain === $main_domain ) {
                $progress['processed']++;
                $processed_in_batch++;
                continue;
            }

            try {
                // Execute health check with timeout control
                $start_time = microtime(true);
                $result = $this->check_domain_health_with_timeout( $domain, 10 ); // 10 second timeout
                $end_time = microtime(true);

                // Record check duration
                $result['check_duration'] = round($end_time - $start_time, 2);

                // Save result
                dm_save_health_result( $domain, $result );

                // Check for issues
                if ( $this->has_health_issues( $result ) ) {
                    $progress['issues'][$domain] = $result;
                }

            } catch ( Exception $e ) {
                // Log error but continue processing
                error_log( 'WP Domain Mapping: Health check error for ' . $domain . ': ' . $e->getMessage() );

                // Save error result
                $error_result = array(
                    'domain' => $domain,
                    'last_check' => current_time( 'mysql' ),
                    'error' => $e->getMessage(),
                    'dns_status' => 'error',
                    'ssl_valid' => false,
                    'accessible' => false
                );
                dm_save_health_result( $domain, $error_result );
            }

            $progress['processed']++;
            $processed_in_batch++;

            // Prevent memory leaks
            if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
            }
        }

        // Update queue and progress
        update_site_option( 'dm_health_check_queue', $queue );
        update_site_option( 'dm_health_check_progress', $progress );

        // If there are still unprocessed domains, schedule next batch
        if ( ! empty( $queue ) && ! $return_data ) {
            wp_schedule_single_event( time() + 3, 'dm_domain_health_check_batch' ); // 3 second interval
        }

        if ( $return_data ) {
            $remaining = count( $queue );
            $percentage = $progress['total'] > 0 ? round( ( $progress['processed'] / $progress['total'] ) * 100 ) : 100;

            return array(
                'complete' => empty( $queue ),
                'total' => $progress['total'],
                'processed' => $progress['processed'],
                'remaining' => $remaining,
                'percentage' => $percentage,
                'processed_in_batch' => $processed_in_batch,
                'message' => sprintf(
                    __( 'Processed %d of %d domains (%d%% complete)', 'wp-domain-mapping' ),
                    $progress['processed'],
                    $progress['total'],
                    $percentage
                )
            );
        }
    }

    /**
     * Domain health check with timeout control
     */
    private function check_domain_health_with_timeout( $domain, $timeout = 10 ) {
        // Set maximum execution time
        $original_time_limit = ini_get('max_execution_time');
        if ( function_exists('set_time_limit') ) {
            set_time_limit( $timeout + 5 );
        }

        try {
            $result = $this->check_domain_health( $domain );
            return $result;
        } finally {
            // Restore original time limit
            if ( function_exists('set_time_limit') && $original_time_limit ) {
                set_time_limit( $original_time_limit );
            }
        }
    }

    /**
     * Check domain health
     *
     * @param string $domain Domain to check
     * @return array Health check result
     */
    public function check_domain_health( $domain ) {
        $result = array(
            'domain' => $domain,
            'last_check' => current_time( 'mysql' ),
            'dns_status' => 'error',
            'dns_message' => __( 'DNS check not performed', 'wp-domain-mapping' ),
            'resolved_ip' => '',
            'ssl_valid' => false,
            'ssl_expiry' => '',
            'accessible' => false,
            'response_code' => 0
        );

        // Get server IP or CNAME
        $server_ip = get_site_option( 'dm_ipaddress', '' );
        $server_cname = get_site_option( 'dm_cname', '' );

        // Check DNS setting
        $domain_ip = gethostbyname( $domain );
        $result['resolved_ip'] = $domain_ip;

        if ( $domain_ip && $domain_ip !== $domain ) {
            if ( $server_ip && strpos( $server_ip, $domain_ip ) !== false ) {
                $result['dns_status'] = 'success';
                $result['dns_message'] = __( 'Domain A record is correctly pointing to server IP.', 'wp-domain-mapping' );
            } elseif ( $server_cname && function_exists( 'dns_get_record' ) ) {
                $dns_records = @dns_get_record( $domain, DNS_CNAME );
                $has_valid_cname = false;

                if ( $dns_records ) {
                    foreach ( $dns_records as $record ) {
                        if ( isset( $record['target'] ) &&
                            (
                                $record['target'] === $server_cname ||
                                strpos( $record['target'], $server_cname ) !== false
                            ) ) {
                            $has_valid_cname = true;
                            break;
                        }
                    }
                }

                if ( $has_valid_cname ) {
                    $result['dns_status'] = 'success';
                    $result['dns_message'] = __( 'Domain CNAME record is correctly configured.', 'wp-domain-mapping' );
                } else {
                    $result['dns_message'] = __( 'Domain is not pointing to the correct server.', 'wp-domain-mapping' );
                }
            } else {
                $result['dns_message'] = __( 'Cannot verify DNS configuration.', 'wp-domain-mapping' );
            }
        } else {
            $result['dns_message'] = __( 'Domain does not resolve to an IP address.', 'wp-domain-mapping' );
        }

        // Check site accessibility and SSL
        $response = $this->test_domain_connection( $domain );

        if ( $response ) {
            $result['accessible'] = $response['accessible'];
            $result['response_code'] = $response['response_code'];
            $result['ssl_valid'] = $response['ssl_valid'];
            $result['ssl_expiry'] = $response['ssl_expiry'];
        }

        return $result;
    }

    /**
     * Test domain connection - improved with better timeout control
     *
     * @param string $domain Domain to test
     * @return array|false Connection test result
     */
    private function test_domain_connection( $domain ) {
        if ( ! function_exists( 'curl_init' ) ) {
            return array(
                'accessible' => false,
                'response_code' => 0,
                'ssl_valid' => false,
                'ssl_expiry' => '',
                'error' => 'cURL not available'
            );
        }

        $result = array(
            'accessible' => false,
            'response_code' => 0,
            'ssl_valid' => false,
            'ssl_expiry' => '',
            'error' => ''
        );

        // Test HTTPS connection
        $ch = curl_init( 'https://' . $domain );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 8, // Reduced timeout
            CURLOPT_CONNECTTIMEOUT => 5, // Connection timeout
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CERTINFO => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'WP Domain Mapping Health Check/1.0'
        ));

        $response = curl_exec( $ch );
        $response_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $ssl_error = curl_error( $ch );

        $result['response_code'] = $response_code;

        if ( $response !== false && $response_code > 0 ) {
            $result['accessible'] = ( $response_code >= 200 && $response_code < 400 );
            $result['ssl_valid'] = empty( $ssl_error );

            // Get SSL certificate info
            if ( $result['ssl_valid'] ) {
                $cert_info = curl_getinfo( $ch, CURLINFO_CERTINFO );
                if ( ! empty( $cert_info ) && isset( $cert_info[0]['Expire date'] ) ) {
                    $result['ssl_expiry'] = $cert_info[0]['Expire date'];
                }
            } else {
                $result['error'] = $ssl_error;
            }
        }

        curl_close( $ch );

        // If HTTPS failed, try HTTP
        if ( ! $result['accessible'] ) {
            $ch = curl_init( 'http://' . $domain );
            curl_setopt_array( $ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'WP Domain Mapping Health Check/1.0'
            ));

            $response = curl_exec( $ch );
            $response_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $http_error = curl_error( $ch );

            if ( $response !== false && $response_code > 0 ) {
                $result['accessible'] = ( $response_code >= 200 && $response_code < 400 );
                $result['response_code'] = $response_code;
            } else {
                $result['error'] = $http_error;
            }

            curl_close( $ch );
        }

        return $result;
    }

    /**
     * Check if result has health issues
     *
     * @param array $result Health check result
     * @return bool Whether there are issues
     */
    private function has_health_issues( $result ) {
        // Check DNS issues
        if ( $result['dns_status'] !== 'success' ) {
            return true;
        }

        // Check SSL issues
        if ( ! $result['ssl_valid'] ) {
            return true;
        }

        // Check SSL expiry
        if ( ! empty( $result['ssl_expiry'] ) ) {
            $expiry_date = strtotime( $result['ssl_expiry'] );
            $threshold = get_site_option( 'dm_ssl_expiry_threshold', 14 );
            $threshold_date = strtotime( '+' . $threshold . ' days' );

            if ( $expiry_date <= $threshold_date ) {
                return true;
            }
        }

        // Check accessibility issues
        if ( ! $result['accessible'] ) {
            return true;
        }

        return false;
    }

    /**
     * Send health notification
     *
     * @param array $issues Issues found
     */
    private function send_health_notification( $issues ) {
        $notification_email = get_site_option( 'dm_notification_email', get_option( 'admin_email' ) );

        if ( empty( $notification_email ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( __( '[%s] Domain Mapping Health Alert', 'wp-domain-mapping' ), $site_name );

        // Build email content
        $message = sprintf( __( 'Domain health issues were detected on %s.', 'wp-domain-mapping' ), $site_name ) . "\n\n";
        $message .= __( 'The following domains have issues:', 'wp-domain-mapping' ) . "\n\n";

        foreach ( $issues as $domain => $result ) {
            $message .= sprintf( __( 'Domain: %s', 'wp-domain-mapping' ), $domain ) . "\n";

            // Add DNS status
            if ( $result['dns_status'] !== 'success' ) {
                $message .= "  - " . sprintf( __( 'DNS Issue: %s', 'wp-domain-mapping' ), $result['dns_message'] ) . "\n";
            }

            // Add SSL status
            if ( ! $result['ssl_valid'] ) {
                $message .= "  - " . __( 'SSL Certificate is invalid or missing.', 'wp-domain-mapping' ) . "\n";
            } elseif ( ! empty( $result['ssl_expiry'] ) ) {
                $expiry_date = strtotime( $result['ssl_expiry'] );
                $threshold = get_site_option( 'dm_ssl_expiry_threshold', 14 );
                $threshold_date = strtotime( '+' . $threshold . ' days' );

                if ( $expiry_date <= $threshold_date ) {
                    $message .= "  - " . sprintf(
                        __( 'SSL Certificate expires on %s (within %d days).', 'wp-domain-mapping' ),
                        date( 'Y-m-d', $expiry_date ),
                        $threshold
                    ) . "\n";
                }
            }

            // Add accessibility status
            if ( ! $result['accessible'] ) {
                $message .= "  - " . __( 'Site is not accessible.', 'wp-domain-mapping' ) . "\n";
                if ( $result['response_code'] > 0 ) {
                    $message .= "    " . sprintf( __( 'HTTP Response Code: %d', 'wp-domain-mapping' ), $result['response_code'] ) . "\n";
                }
            }

            $message .= "\n";
        }

        // Add resolution link
        $message .= sprintf(
            __( 'To view and manage these issues, please visit: %s', 'wp-domain-mapping' ),
            network_admin_url( 'settings.php?page=domain-mapping&tab=health' )
        ) . "\n";

        // Send email
        wp_mail( $notification_email, $subject, $message );
    }

    /**
     * Clean up stuck health checks
     */
    public function cleanup_stuck_health_check() {
        $progress = get_site_option( 'dm_health_check_progress', false );

        if ( $progress !== false ) {
            $started_time = strtotime( $progress['started'] );
            $current_time = current_time( 'timestamp' );

            // If health check running for more than 30 minutes, consider it stuck
            if ( ( $current_time - $started_time ) > 1800 ) {
                delete_site_option( 'dm_health_check_queue' );
                delete_site_option( 'dm_health_check_progress' );

                error_log( 'WP Domain Mapping: Cleaned up stuck health check that started at ' . $progress['started'] );
                return true;
            }
        }

        return false;
    }

    /**
     * Get cron status
     */
    public function get_cron_status() {
        $scheduled_info = get_site_option( 'dm_health_check_scheduled', false );
        $next_scheduled = wp_next_scheduled( 'dm_domain_health_check' );
        $last_check = get_site_option( 'dm_last_health_check', false );

        return array(
            'scheduled_info' => $scheduled_info,
            'next_scheduled' => $next_scheduled,
            'next_scheduled_formatted' => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : 'Not scheduled',
            'last_check' => $last_check,
            'last_check_formatted' => $last_check ? date( 'Y-m-d H:i:s', $last_check ) : 'Never',
            'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'cron_running' => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON
        );
    }

    /**
     * Show cron debug info (only show on network admin pages)
     */
    public function show_cron_debug_info() {
        if ( ! is_network_admin() || ! current_user_can( 'manage_network' ) ) {
            return;
        }

        $current_screen = get_current_screen();
        if ( ! $current_screen || $current_screen->id !== 'settings_page_domain-mapping-network' ) {
            return;
        }

        $status = $this->get_cron_status();

        if ( $status['wp_cron_disabled'] ) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>WP Domain Mapping:</strong> WordPress Cron is disabled (DISABLE_WP_CRON = true). ';
            echo 'Automatic health checks will not work. Please set up a system cron job.';
            echo '</p></div>';
        }

        // Show next execution time (only in debug mode)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Health Check Status:</strong><br>';
            echo 'Next scheduled: ' . esc_html( $status['next_scheduled_formatted'] ) . '<br>';
            echo 'Last check: ' . esc_html( $status['last_check_formatted'] ) . '<br>';
            echo '<a href="' . add_query_arg( 'dm_test_cron', '1' ) . '">Test Cron Execution</a>';
            echo '</p></div>';
        }
    }

    /**
     * Test cron execution (only for debugging)
     */
    public function test_cron_execution() {
        if ( ! isset( $_GET['dm_test_cron'] ) || ! current_user_can( 'manage_network' ) ) {
            return;
        }

        // Manually trigger health check
        $this->scheduled_health_check();

        wp_redirect( remove_query_arg( 'dm_test_cron' ) );
        exit;
    }

    // ========================================
    // Import/Export Functions
    // ========================================

    /**
     * Handle CSV export
     */
    public function handle_export() {
        if ( ! isset( $_POST['domain_mapping_export'] ) || ! $_POST['domain_mapping_export'] ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_die( __( 'You do not have sufficient permissions to export data.', 'wp-domain-mapping' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['domain_mapping_export_nonce'] ) || ! wp_verify_nonce( $_POST['domain_mapping_export_nonce'], 'domain_mapping_export' ) ) {
            wp_die( __( 'Invalid security token. Please try again.', 'wp-domain-mapping' ) );
        }

        // Get options
        $include_header = isset( $_POST['include_header'] ) ? (bool) $_POST['include_header'] : false;
        $blog_id_filter = isset( $_POST['blog_id_filter'] ) && ! empty( $_POST['blog_id_filter'] ) ? intval( $_POST['blog_id_filter'] ) : 0;

        // Get domain mapping data
        global $wpdb;
        $sql = "SELECT blog_id, domain, active FROM {$this->tables['domains']}";

        if ( $blog_id_filter > 0 ) {
            $sql .= $wpdb->prepare( " WHERE blog_id = %d", $blog_id_filter );
        }

        $domains = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $domains ) ) {
            // No data
            wp_redirect( add_query_arg( array( 'page' => 'domain-mapping', 'tab' => 'import-export', 'export' => 'empty' ), network_admin_url( 'settings.php' ) ) );
            exit;
        }

        // Set up CSV output
        $filename = 'domain-mappings-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );

        // Add header row
        if ( $include_header ) {
            fputcsv( $output, array( 'blog_id', 'domain', 'active' ) );
        }

        // Add data rows
        foreach ( $domains as $domain ) {
            fputcsv( $output, $domain );
        }

        fclose( $output );
        exit;
    }

    /**
     * AJAX handler for CSV import - FIXED
     */
    public function ajax_import_csv() {
        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to import data.', 'wp-domain-mapping' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['domain_mapping_import_nonce'] ) || ! wp_verify_nonce( $_POST['domain_mapping_import_nonce'], 'domain_mapping_import' ) ) {
            wp_send_json_error( __( 'Invalid security token. Please try again.', 'wp-domain-mapping' ) );
        }

        // Check if file was uploaded
        if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'No file uploaded or upload error.', 'wp-domain-mapping' ) );
        }

        // Check file size before processing
        if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(__('File size exceeds 5MB limit.', 'wp-domain-mapping'));
            return;
        }

        // Improved file type validation
        $uploaded_filename = $_FILES['csv_file']['name'];
        $file_extension = strtolower(pathinfo($uploaded_filename, PATHINFO_EXTENSION));

        // Check file extension
        if (!in_array($file_extension, array('csv', 'txt'))) {
            wp_send_json_error(__('Only CSV files are allowed. File extension detected: ' . $file_extension, 'wp-domain-mapping'));
            return;
        }

        // Additional MIME type check (more reliable)
        $allowed_mime_types = array(
            'text/csv',
            'text/plain',
            'application/csv',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel'
        );

        $file_mime_type = '';

        // Method 1: Use finfo if available (most reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_mime_type = finfo_file($finfo, $_FILES['csv_file']['tmp_name']);
            finfo_close($finfo);
        }
        // Method 2: Fallback to mime_content_type if available
        elseif (function_exists('mime_content_type')) {
            $file_mime_type = mime_content_type($_FILES['csv_file']['tmp_name']);
        }
        // Method 3: Use WordPress built-in function
        else {
            $file_type_check = wp_check_filetype_and_ext($_FILES['csv_file']['tmp_name'], $uploaded_filename);
            $file_mime_type = $file_type_check['type'];
        }

        // Validate MIME type (but be more lenient since CSV files can have various MIME types)
        if (!empty($file_mime_type) && !in_array($file_mime_type, $allowed_mime_types)) {
            // Additional check: try to detect if file content looks like CSV
            $file_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($file_handle) {
                $first_line = fgets($file_handle);
                fclose($file_handle);

                // Basic CSV content check - look for common CSV patterns
                $csv_patterns = array(
                    '/,/',           // Contains commas
                    '/;/',           // Contains semicolons (common in European CSV)
                    '/\t/',          // Contains tabs (TSV)
                    '/\r\n|\r|\n/'   // Contains line breaks
                );

                $looks_like_csv = false;
                foreach ($csv_patterns as $pattern) {
                    if (preg_match($pattern, $first_line)) {
                        $looks_like_csv = true;
                        break;
                    }
                }

                if (!$looks_like_csv) {
                    wp_send_json_error(__('File does not appear to be a valid CSV format. MIME type detected: ' . $file_mime_type, 'wp-domain-mapping'));
                    return;
                }
            }
        }

        // Get options
        $has_header = isset( $_POST['has_header'] ) ? (bool) $_POST['has_header'] : false;
        $update_existing = isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false;
        $validate_sites = isset( $_POST['validate_sites'] ) ? (bool) $_POST['validate_sites'] : true;

        // Open file
        $file = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
        if ( ! $file ) {
            wp_send_json_error( __( 'Could not open the uploaded file.', 'wp-domain-mapping' ) );
        }

        // Check for UTF-8 BOM and remove it
        $first_bytes = fread($file, 3);
        if ($first_bytes !== "\xEF\xBB\xBF") {
            // If no BOM found, rewind to beginning
            rewind($file);
        }
        // If BOM found, it's already skipped

        // Initialize counters and log
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $log = array();

        // Track processed domains to prevent duplicates
        $processed_domains = array();

        // Skip header row
        if ( $has_header ) {
            $header_row = fgetcsv( $file );
            if ($header_row === false) {
                fclose($file);
                wp_send_json_error(__('Could not read CSV file or file is empty.', 'wp-domain-mapping'));
                return;
            }
        }

        // Process each row
        $row_num = $has_header ? 2 : 1; // Account for header row
        $processed_rows = 0;

        while ( ( $data = fgetcsv( $file ) ) !== false ) {
            $processed_rows++;

            // Skip empty rows
            if (empty($data) || (count($data) == 1 && empty($data[0]))) {
                $row_num++;
                continue;
            }

            // Check data format
            if ( count( $data ) < 3 ) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf( __( 'Row %d: Invalid format. Expected at least 3 columns, got %d.', 'wp-domain-mapping' ), $row_num, count($data) )
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Parse data with trimming
            $blog_id = intval( trim($data[0]) );
            $domain = dm_clean_domain( trim( $data[1] ) );
            $active = intval( trim($data[2]) );

            // Check if we've already processed this domain
            if ( isset( $processed_domains[$domain] ) ) {
                $log[] = array(
                    'status' => 'warning',
                    'message' => sprintf( __( 'Row %d: Domain %s already processed in this import. Skipped duplicate.', 'wp-domain-mapping' ), $row_num, $domain )
                );
                $skipped++;
                $row_num++;
                continue;
            }

            // Mark domain as processed
            $processed_domains[$domain] = true;

            // Validate blog_id
            if ( $blog_id <= 0 ) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf( __( 'Row %d: Invalid blog ID: %d', 'wp-domain-mapping' ), $row_num, $blog_id )
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Validate site exists
            if ( $validate_sites && ! get_blog_details( $blog_id ) ) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf( __( 'Row %d: Site ID %d does not exist.', 'wp-domain-mapping' ), $row_num, $blog_id )
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Validate domain format
            if ( ! dm_validate_domain( $domain ) ) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf( __( 'Row %d: Invalid domain format: %s', 'wp-domain-mapping' ), $row_num, $domain )
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Check if domain already exists
            $existing = dm_get_domain_by_name( $domain );

            if ( $existing ) {
                if ( $existing->blog_id != $blog_id ) {
                    $log[] = array(
                        'status' => 'error',
                        'message' => sprintf( __( 'Row %d: Domain %s is already mapped to blog ID %d.', 'wp-domain-mapping' ),
                            $row_num, $domain, $existing->blog_id )
                    );
                    $errors++;
                } elseif ( ! $update_existing ) {
                    $log[] = array(
                        'status' => 'warning',
                        'message' => sprintf( __( 'Row %d: Domain %s already exists for blog ID %d. Skipped.', 'wp-domain-mapping' ),
                            $row_num, $domain, $blog_id )
                    );
                    $skipped++;
                } else {
                    // Update existing domain
                    $success = dm_update_domain( $domain, $blog_id, $active );

                    if ( $success ) {
                        $log[] = array(
                            'status' => 'success',
                            'message' => sprintf( __( 'Row %d: Updated domain %s for blog ID %d.', 'wp-domain-mapping' ),
                                $row_num, $domain, $blog_id )
                        );
                        $imported++;
                    } else {
                        $log[] = array(
                            'status' => 'error',
                            'message' => sprintf( __( 'Row %d: Failed to update domain %s for blog ID %d.', 'wp-domain-mapping' ),
                                $row_num, $domain, $blog_id )
                        );
                        $errors++;
                    }
                }
            } else {
                // Add new domain
                $success = dm_add_domain( $blog_id, $domain, $active );

                if ( $success ) {
                    $log[] = array(
                        'status' => 'success',
                        'message' => sprintf( __( 'Row %d: Added domain %s for blog ID %d.', 'wp-domain-mapping' ),
                            $row_num, $domain, $blog_id )
                    );
                    $imported++;
                } else {
                    $log[] = array(
                        'status' => 'error',
                        'message' => sprintf( __( 'Row %d: Failed to add domain %s for blog ID %d.', 'wp-domain-mapping' ),
                            $row_num, $domain, $blog_id )
                    );
                    $errors++;
                }
            }

            $row_num++;

            // Prevent timeout on large files
            if ($processed_rows % 100 == 0) {
                // Flush output and extend time limit
                if (function_exists('set_time_limit')) {
                    set_time_limit(30);
                }
            }
        }

        fclose( $file );

        // Build response
        $message = sprintf(
            __( 'Import completed: %d imported, %d skipped, %d errors.', 'wp-domain-mapping' ),
            $imported, $skipped, $errors
        );

        wp_send_json_success( array(
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $log,
            'processed_rows' => $processed_rows
        ) );
    }
}
