<?php
/**
 * Plugin Name: WP Domain Mapping
 * Plugin URI: https://wenpai.org/plugins/wp-domain-mapping/
 * Description: Map any site on a WordPress website to another domain with enhanced management features.
 * Version: 2.0.0
 * Author: WPDomain.com
 * Author URI: https://wpdomain.com/
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-domain-mapping
 * Domain Path: /languages
 * Requires at least: 6.7.2
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WP_DOMAIN_MAPPING_VERSION', '2.0.0' );
define( 'WP_DOMAIN_MAPPING_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_DOMAIN_MAPPING_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_DOMAIN_MAPPING_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Domain Mapping Class
 *
 * Handles the core functionality of the plugin
 */
class WP_Domain_Mapping {

    /**
     * Plugin instance
     *
     * @var WP_Domain_Mapping
     */
    private static $instance = null;

    /**
     * Database table names
     *
     * @var array
     */
    private $tables = array();

    /**
     * Get plugin instance
     *
     * @return WP_Domain_Mapping
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
        global $wpdb;

        // Define table name constants if not already defined
        if ( ! defined( 'WP_DOMAIN_MAPPING_TABLE_DOMAINS' ) ) {
            define( 'WP_DOMAIN_MAPPING_TABLE_DOMAINS', 'domain_mapping' );
            define( 'WP_DOMAIN_MAPPING_TABLE_LOGINS', 'domain_mapping_logins' );
            define( 'WP_DOMAIN_MAPPING_TABLE_LOGS', 'domain_mapping_logs' );
        }

        // Set up table names
        $this->tables = array(
            'domains'  => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS,
            'logins'   => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGINS,
            'logs'     => $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGS,
        );

        // Initialize the plugin
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Initialize plugin update checker
        $this->setup_updater();

        // Setup admin-related hooks
        $this->setup_admin_hooks();

        // Setup general hooks
        $this->setup_general_hooks();

        // Check plugin requirements on activation
        register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-domain-mapping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Setup plugin updater
     */
    private function setup_updater() {
        // Only include the updater if the PUC library exists
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';

            if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5p3\PucFactory' ) ) {
                \YahnisElsts\PluginUpdateChecker\v5p3\PucFactory::buildUpdateChecker(
                    'https://updates.weixiaoduo.com/wp-domain-mapping.json',
                    __FILE__,
                    'wp-domain-mapping'
                );
            }
        }
    }

    /**
     * Setup admin hooks
     */
    private function setup_admin_hooks() {
        // Add menu pages
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
        add_action( 'network_admin_menu', array( $this, 'add_network_admin_pages' ) );

        // Add AJAX handlers
        add_action( 'wp_ajax_dm_handle_actions', array( $this, 'ajax_handle_actions' ) );

        // Add domain link to sites list
        add_filter( 'manage_sites_action_links', array( $this, 'add_domain_link_to_sites' ), 10, 2 );

        // Add custom columns
        add_filter( 'wpmu_blogs_columns', array( $this, 'add_domain_mapping_columns' ) );
        add_action( 'manage_blogs_custom_column', array( $this, 'display_domain_mapping_column' ), 1, 3 );
        add_action( 'manage_sites_custom_column', array( $this, 'display_domain_mapping_column' ), 1, 3 );

        // Handle user domain mapping actions
        if ( isset( $_GET['page'] ) && 'domainmapping' === $_GET['page'] ) {
            add_action( 'admin_init', array( $this, 'handle_user_domain_actions' ) );
        }
    }

    /**
     * Setup general hooks
     */
    private function setup_general_hooks() {
        // Delete domain mappings when a blog is deleted
        add_action( 'delete_blog', array( $this, 'delete_blog_domain_mapping' ), 1, 2 );

        // Handle domain redirection
        add_action( 'template_redirect', array( $this, 'redirect_to_mapped_domain' ) );

        // Handle admin area redirection
        add_action( 'admin_init', array( $this, 'redirect_admin' ) );

        // Setup domain mapping filters if DOMAIN_MAPPING is defined
        if ( defined( 'DOMAIN_MAPPING' ) ) {
            $this->setup_domain_mapping_filters();
        } else {
            add_filter( 'admin_url', array( $this, 'domain_mapping_adminurl' ), 10, 3 );
        }

        // Handle remote login
        if ( isset( $_GET['dm'] ) ) {
            add_action( 'template_redirect', array( $this, 'remote_login_js' ) );
        }
    }

    /**
     * Setup domain mapping filters when DOMAIN_MAPPING is defined
     */
    private function setup_domain_mapping_filters() {
        add_filter( 'plugins_url', array( $this, 'domain_mapping_plugins_uri' ), 1 );
        add_filter( 'theme_root_uri', array( $this, 'domain_mapping_themes_uri' ), 1 );
        add_filter( 'pre_option_siteurl', array( $this, 'domain_mapping_siteurl' ) );
        add_filter( 'pre_option_home', array( $this, 'domain_mapping_siteurl' ) );
        add_filter( 'the_content', array( $this, 'domain_mapping_post_content' ) );
        add_action( 'wp_head', array( $this, 'remote_login_js_loader' ) );
        add_action( 'login_head', array( $this, 'redirect_login_to_orig' ) );
        add_action( 'wp_logout', array( $this, 'remote_logout_loader' ), 9999 );

        add_filter( 'stylesheet_uri', array( $this, 'domain_mapping_post_content' ) );
        add_filter( 'stylesheet_directory', array( $this, 'domain_mapping_post_content' ) );
        add_filter( 'stylesheet_directory_uri', array( $this, 'domain_mapping_post_content' ) );
        add_filter( 'template_directory', array( $this, 'domain_mapping_post_content' ) );
        add_filter( 'template_directory_uri', array( $this, 'domain_mapping_post_content' ) );
        add_filter( 'plugins_url', array( $this, 'domain_mapping_post_content' ) );
    }

    /**
     * Actions to perform on plugin activation
     */
    public function plugin_activation() {
        // Create database tables
        $this->create_tables();

        // Initialize remote login hash
        $this->get_hash();
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_pages() {
        global $current_site, $wpdb, $wp_db_version;

        if ( ! isset( $current_site ) && $wp_db_version >= 15260 ) {
            add_action( 'admin_notices', array( $this, 'domain_mapping_warning' ) );
            return false;
        }

        if ( isset($current_site->path) && $current_site->path != "/" ) {
            wp_die( esc_html__( "The domain mapping plugin only works if the site is installed in /. This is a limitation of how virtual servers work and is very difficult to work around.", 'wp-domain-mapping' ) );
        }

        if ( get_site_option( 'dm_user_settings' ) && $current_site->blog_id != $wpdb->blogid && ! $this->sunrise_warning( false ) ) {
            add_management_page(
                __( 'Domain Mapping', 'wp-domain-mapping' ),
                __( 'Domain Mapping', 'wp-domain-mapping' ),
                'manage_options',
                'domainmapping',
                array( $this, 'render_manage_page' )
            );
        }
    }

    /**
     * Add network admin menu pages
     */
    public function add_network_admin_pages() {
        add_submenu_page(
            'settings.php',
            __( 'Domain Mapping', 'wp-domain-mapping' ),
            __( 'Domain Mapping', 'wp-domain-mapping' ),
            'manage_network',
            'domain-mapping',
            array( $this, 'render_admin_page' )
        );

        add_submenu_page(
            'sites.php',
            __( 'Domains', 'wp-domain-mapping' ),
            __( 'Domains', 'wp-domain-mapping' ),
            'manage_network',
            'domains',
            array( $this, 'render_domains_admin' )
        );
    }

    /**
     * Check if sunrise.php is properly configured
     *
     * @param bool $die Whether to die with an error message
     * @return bool True if there's a problem, false if everything is okay
     */
    public function sunrise_warning( $die = true ) {
        if ( ! file_exists( WP_CONTENT_DIR . '/sunrise.php' ) ) {
            if ( ! $die ) return true;
            if ( $this->is_site_admin() ) {
                wp_die(
                    sprintf(
                        /* translators: %1$s: Content directory, %2$s: WordPress install path */
                        esc_html__( 'Please copy sunrise.php to %1$s/sunrise.php and ensure the SUNRISE definition is in %2$swp-config.php', 'wp-domain-mapping' ),
                        esc_html( WP_CONTENT_DIR ),
                        esc_html( ABSPATH )
                    )
                );
            } else {
                wp_die( esc_html__( "This plugin has not been configured correctly yet.", 'wp-domain-mapping' ) );
            }
        } elseif ( ! defined( 'SUNRISE' ) ) {
            if ( ! $die ) return true;
            if ( $this->is_site_admin() ) {
                wp_die(
                    sprintf(
                        /* translators: %s: WordPress install path */
                        esc_html__( 'Please uncomment the line <em>define( \'SUNRISE\', \'on\' );</em> or add it to your %swp-config.php', 'wp-domain-mapping' ),
                        esc_html( ABSPATH )
                    )
                );
            } else {
                wp_die( esc_html__( "This plugin has not been configured correctly yet.", 'wp-domain-mapping' ) );
            }
        } elseif ( ! defined( 'SUNRISE_LOADED' ) ) {
            if ( ! $die ) return true;
            if ( $this->is_site_admin() ) {
                wp_die(
                    sprintf(
                        /* translators: %s: WordPress install path */
                        esc_html__( 'Please edit your %swp-config.php and move the line <em>define( \'SUNRISE\', \'on\' );</em> above the last require_once() in that file or make sure you updated sunrise.php.', 'wp-domain-mapping' ),
                        esc_html( ABSPATH )
                    )
                );
            } else {
                wp_die( esc_html__( "This plugin has not been configured correctly yet.", 'wp-domain-mapping' ) );
            }
        }
        return false;
    }

    /**
     * Display warning message for network configuration
     */
    public function domain_mapping_warning() {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Domain Mapping Disabled.', 'wp-domain-mapping' ) . '</strong> ' .
             sprintf(
                /* translators: %s: URL to WordPress network creation documentation */
                wp_kses(
                    __( 'You must <a href="%1$s">create a network</a> for it to work.', 'wp-domain-mapping' ),
                    array( 'a' => array( 'href' => array() ) )
                ),
                'http://codex.wordpress.org/Create_A_Network'
             ) . '</p></div>';
    }

    /**
     * Create required database tables
     */
    public function create_tables() {
        global $wpdb;

        // Only network admins can create tables
        if ( ! $this->is_site_admin() ) {
            return;
        }

        // Initialize remote login hash
        $this->get_hash();

        // Use static variable to prevent repeated execution
        static $tables_created = false;
        if ( $tables_created ) {
            return;
        }

        $created = 0;
        $charset_collate = $wpdb->get_charset_collate();

        // Create domain_mapping table
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['domains']}'" ) != $this->tables['domains'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['domains']}` (
                `id` bigint(20) NOT NULL auto_increment,
                `blog_id` bigint(20) NOT NULL,
                `domain` varchar(255) NOT NULL,
                `active` tinyint(4) default '1',
                PRIMARY KEY  (`id`),
                KEY `blog_id` (`blog_id`,`domain`,`active`)
            ) $charset_collate;" );
            $created = 1;
        }

        // Create domain_mapping_logins table
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['logins']}'" ) != $this->tables['logins'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['logins']}` (
                `id` varchar(32) NOT NULL,
                `user_id` bigint(20) NOT NULL,
                `blog_id` bigint(20) NOT NULL,
                `t` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                PRIMARY KEY  (`id`)
            ) $charset_collate;" );
            $created = 1;
        }

        // Create domain_mapping_logs table
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['logs']}'" ) != $this->tables['logs'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['logs']}` (
                `id` bigint(20) NOT NULL auto_increment,
                `user_id` bigint(20) NOT NULL,
                `action` varchar(50) NOT NULL,
                `domain` varchar(255) NOT NULL,
                `blog_id` bigint(20) NOT NULL,
                `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) $charset_collate;" );
            $created = 1;
        }

        // If any table was created, show success message and mark as created
        if ( $created ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success"><p><strong>' .
                      esc_html__( 'Domain mapping database tables created.', 'wp-domain-mapping' ) .
                      '</strong></p></div>';
            });
            $tables_created = true;
        }
    }

    /**
     * AJAX handler for domain actions
     */
    public function ajax_handle_actions() {
        check_ajax_referer( 'domain_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-domain-mapping' ) );
        }

        global $wpdb;

        $action = sanitize_text_field( $_POST['action_type'] );
        $domain = $this->clean_domain( sanitize_text_field( isset( $_POST['domain'] ) ? strtolower( $_POST['domain'] ) : '' ) );
        $blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
        $active = isset( $_POST['active'] ) ? absint( $_POST['active'] ) : 0;
        $orig_domain = isset( $_POST['orig_domain'] ) ? $this->clean_domain( sanitize_text_field( $_POST['orig_domain'] ) ) : '';
        $current_user_id = get_current_user_id();

        switch ( $action ) {
            case 'save':
                if ( $blog_id != 0 && $blog_id != 1 ) {
                    // Validate domain format
                    if ( ! $this->is_valid_domain( $domain ) ) {
                        wp_send_json_error( __( 'Invalid domain format.', 'wp-domain-mapping' ) );
                    }

                    // Check if domain exists for another blog
                    $exists = $wpdb->get_var( $wpdb->prepare(
                        "SELECT domain FROM {$this->tables['domains']} WHERE blog_id != %d AND domain = %s",
                        $blog_id, $domain
                    ));

                    if ( null == $exists ) {
                        $wpdb->query( 'START TRANSACTION' );

                        try {
                            if ( empty( $orig_domain ) ) {
                                // Insert new domain
                                $success = $wpdb->insert(
                                    $this->tables['domains'],
                                    array(
                                        'blog_id' => $blog_id,
                                        'domain' => $domain,
                                        'active' => $active
                                    ),
                                    array( '%d', '%s', '%d' )
                                );

                                if ( $success ) {
                                    // Log the action
                                    $wpdb->insert(
                                        $this->tables['logs'],
                                        array(
                                            'user_id' => $current_user_id,
                                            'action' => 'add',
                                            'domain' => $domain,
                                            'blog_id' => $blog_id
                                        ),
                                        array( '%d', '%s', '%s', '%d' )
                                    );

                                    $wpdb->query( 'COMMIT' );
                                    wp_send_json_success( __( 'Domain added successfully.', 'wp-domain-mapping' ) );
                                } else {
                                    $wpdb->query( 'ROLLBACK' );
                                    wp_send_json_error( __( 'Failed to add domain.', 'wp-domain-mapping' ) );
                                }
                            } else {
                                // Update existing domain
                                $success = $wpdb->update(
                                    $this->tables['domains'],
                                    array(
                                        'blog_id' => $blog_id,
                                        'domain' => $domain,
                                        'active' => $active
                                    ),
                                    array( 'domain' => $orig_domain ),
                                    array( '%d', '%s', '%d' ),
                                    array( '%s' )
                                );

                                if ( $success !== false ) {
                                    // Log the action
                                    $wpdb->insert(
                                        $this->tables['logs'],
                                        array(
                                            'user_id' => $current_user_id,
                                            'action' => 'edit',
                                            'domain' => $domain,
                                            'blog_id' => $blog_id
                                        ),
                                        array( '%d', '%s', '%s', '%d' )
                                    );

                                    $wpdb->query( 'COMMIT' );
                                    wp_send_json_success( __( 'Domain updated successfully.', 'wp-domain-mapping' ) );
                                } else {
                                    $wpdb->query( 'ROLLBACK' );
                                    wp_send_json_error( __( 'No changes were made or update failed.', 'wp-domain-mapping' ) );
                                }
                            }
                        } catch ( Exception $e ) {
                            $wpdb->query( 'ROLLBACK' );
                            wp_send_json_error( __( 'An error occurred while saving domain.', 'wp-domain-mapping' ) );
                        }
                    } else {
                        wp_send_json_error( __( 'Domain already exists for another site.', 'wp-domain-mapping' ) );
                    }
                } else {
                    wp_send_json_error( __( 'Invalid site ID.', 'wp-domain-mapping' ) );
                }
                break;

            case 'delete':
                $domains = isset( $_POST['domains'] ) ? array_map( 'sanitize_text_field', (array) $_POST['domains'] ) : array( $domain );

                $wpdb->query( 'START TRANSACTION' );
                $deleted = 0;

                try {
                    foreach ( $domains as $del_domain ) {
                        if ( empty( $del_domain ) ) continue;

                        // Get blog_id before deletion for logging
                        $affected_blog_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT blog_id FROM {$this->tables['domains']} WHERE domain = %s",
                            $del_domain
                        ));

                        if ( $affected_blog_id ) {
                            // Delete the domain
                            $result = $wpdb->delete(
                                $this->tables['domains'],
                                array( 'domain' => $del_domain ),
                                array( '%s' )
                            );

                            if ( $result ) {
                                $deleted++;

                                // Log the action
                                $wpdb->insert(
                                    $this->tables['logs'],
                                    array(
                                        'user_id' => $current_user_id,
                                        'action' => 'delete',
                                        'domain' => $del_domain,
                                        'blog_id' => $affected_blog_id
                                    ),
                                    array( '%d', '%s', '%s', '%d' )
                                );
                            }
                        }
                    }

                    if ( $deleted > 0 ) {
                        $wpdb->query( 'COMMIT' );
                        $message = sprintf(
                            _n(
                                'Domain deleted successfully.',
                                '%d domains deleted successfully.',
                                $deleted,
                                'wp-domain-mapping'
                            ),
                            $deleted
                        );
                        wp_send_json_success( $message );
                    } else {
                        $wpdb->query( 'ROLLBACK' );
                        wp_send_json_error( __( 'No domains were deleted.', 'wp-domain-mapping' ) );
                    }
                } catch ( Exception $e ) {
                    $wpdb->query( 'ROLLBACK' );
                    wp_send_json_error( __( 'An error occurred while deleting domains.', 'wp-domain-mapping' ) );
                }
                break;

            default:
                wp_send_json_error( __( 'Invalid action.', 'wp-domain-mapping' ) );
        }
    }

    /**
     * Handle user domain mapping actions
     */
    public function handle_user_domain_actions() {
        global $wpdb, $parent_file;

        $url = add_query_arg( array( 'page' => 'domainmapping' ), admin_url( $parent_file ) );

        if ( ! empty( $_POST['action'] ) ) {
            $domain = isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '';

            if ( empty( $domain ) ) {
                wp_die( esc_html__( "You must enter a domain", 'wp-domain-mapping' ) );
            }

            check_admin_referer( 'domain_mapping' );

            do_action( 'dm_handle_actions_init', $domain );

            switch ( $_POST['action'] ) {
                case "add":
                    do_action( 'dm_handle_actions_add', $domain );

                    // Validate domain format
                    if ( ! $this->is_valid_domain( $domain ) ) {
                        wp_die( esc_html__( "Invalid domain format", 'wp-domain-mapping' ) );
                    }

                    // Check if domain already exists
                    $domain_exists = $wpdb->get_row( $wpdb->prepare(
                        "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s OR (SELECT blog_id FROM {$this->tables['domains']} WHERE domain = %s)",
                        $domain, $domain
                    ));

                    if ( null == $domain_exists ) {
                        // If primary, reset other domains to not primary
                        if ( isset( $_POST['primary'] ) && $_POST['primary'] ) {
                            $wpdb->update(
                                $this->tables['domains'],
                                array( 'active' => 0 ),
                                array( 'blog_id' => $wpdb->blogid ),
                                array( '%d' ),
                                array( '%d' )
                            );
                        }

                        // Insert new domain
                        $wpdb->insert(
                            $this->tables['domains'],
                            array(
                                'blog_id' => $wpdb->blogid,
                                'domain' => $domain,
                                'active' => isset( $_POST['primary'] ) ? 1 : 0
                            ),
                            array( '%d', '%s', '%d' )
                        );

                        wp_redirect( add_query_arg( array( 'updated' => 'add' ), $url ) );
                        exit;
                    } else {
                        wp_redirect( add_query_arg( array( 'updated' => 'exists' ), $url ) );
                        exit;
                    }
                    break;

                case "primary":
                    do_action( 'dm_handle_actions_primary', $domain );

                    // Reset all domains to not primary
                    $wpdb->update(
                        $this->tables['domains'],
                        array( 'active' => 0 ),
                        array( 'blog_id' => $wpdb->blogid ),
                        array( '%d' ),
                        array( '%d' )
                    );

                    // Check if domain is not the original domain
                    $orig_url = parse_url( $this->get_original_url( 'siteurl' ) );

                    if ( $domain != $orig_url['host'] ) {
                        // Set the selected domain as primary
                        $wpdb->update(
                            $this->tables['domains'],
                            array( 'active' => 1 ),
                            array(
                                'domain' => $domain,
                                'blog_id' => $wpdb->blogid
                            ),
                            array( '%d' ),
                            array( '%s', '%d' )
                        );
                    }

                    wp_redirect( add_query_arg( array( 'updated' => 'primary' ), $url ) );
                    exit;
                    break;
            }
        } elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' ) {
            $domain = sanitize_text_field( $_GET['domain'] );

            if ( empty( $domain ) ) {
                wp_die( esc_html__( "You must enter a domain", 'wp-domain-mapping' ) );
            }

            check_admin_referer( "delete" . $_GET['domain'] );

            do_action( 'dm_handle_actions_del', $domain );

            // Delete the domain
            $wpdb->delete(
                $this->tables['domains'],
                array( 'domain' => $domain ),
                array( '%s' )
            );

            wp_redirect( add_query_arg( array( 'updated' => 'del' ), $url ) );
            exit;
        }
    }

    /**
     * Render domains admin page
     */
    public function render_domains_admin() {
        global $wpdb, $current_site;

        if ( ! $this->is_site_admin() ) {
            return false;
        }

        $this->sunrise_warning();
        $this->create_tables();

        if ( isset($current_site->path) && $current_site->path != "/" ) {
            wp_die( sprintf(
                esc_html__( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in '%s'.", "wp-domain-mapping" ),
                esc_html( $current_site->path )
            ));
        }

        $total_domains = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['domains']}" );
        $primary_domains = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['domains']} WHERE active = 1" );

        $edit_row = false;
        if ( isset( $_GET['edit_domain'] ) ) {
            $edit_domain = sanitize_text_field( $_GET['edit_domain'] );
            $edit_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->tables['domains']} WHERE domain = %s",
                $edit_domain
            ));
        }

        // Load admin UI
        require_once( WP_DOMAIN_MAPPING_DIR_PATH . 'admin/domains-page.php' );
    }

    /**
     * Render domain logs
     */
    public function render_domain_logs() {
        global $wpdb;

        $logs = $wpdb->get_results( "SELECT * FROM {$this->tables['logs']} ORDER BY timestamp DESC LIMIT 50" );

        if ( ! $logs ) {
            echo '<p>' . esc_html__( 'No logs available.', 'wp-domain-mapping' ) . '</p>';
            return;
        }

        // Load logs UI
        require_once( WP_DOMAIN_MAPPING_DIR_PATH . 'admin/logs-table.php' );
    }

    /**
     * Render admin configuration page
     */
    public function render_admin_page() {
        global $wpdb, $current_site;

        if ( ! $this->is_site_admin() ) {
            return false;
        }

        $this->sunrise_warning();
        $this->create_tables();

        if ( isset($current_site->path) && $current_site->path != "/" ) {
            wp_die( sprintf(
                esc_html__( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in '%s'.", "wp-domain-mapping" ),
                esc_html( $current_site->path )
            ));
        }

        // Initialize options if needed
        if ( get_site_option( 'dm_remote_login', 'NA' ) == 'NA' ) {
            add_site_option( 'dm_remote_login', 1 );
        }

        if ( get_site_option( 'dm_redirect_admin', 'NA' ) == 'NA' ) {
            add_site_option( 'dm_redirect_admin', 1 );
        }

        if ( get_site_option( 'dm_user_settings', 'NA' ) == 'NA' ) {
            add_site_option( 'dm_user_settings', 1 );
        }

        // Handle form submission
        if ( ! empty( $_POST['action'] ) && $_POST['action'] == 'update' ) {
            check_admin_referer( 'domain_mapping' );

            // Validate and save IP addresses
            $ipok = true;
            $ipaddresses = explode( ',', sanitize_text_field( $_POST['ipaddress'] ) );

            foreach ( $ipaddresses as $address ) {
                if ( ( $ip = trim( $address ) ) && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    $ipok = false;
                    break;
                }
            }

            if ( $ipok ) {
                update_site_option( 'dm_ipaddress', sanitize_text_field( $_POST['ipaddress'] ) );
            }

            // Save remote login option
            if ( intval( $_POST['always_redirect_admin'] ) == 0 ) {
                $_POST['dm_remote_login'] = 0;
            }

            update_site_option( 'dm_remote_login', intval( $_POST['dm_remote_login'] ) );

            // Validate and save CNAME
            if ( ! preg_match( '/(--|\.\.)/', $_POST['cname'] ) && preg_match( '|^([a-zA-Z0-9-\.])+$|', $_POST['cname'] ) ) {
                update_site_option( 'dm_cname', sanitize_text_field( $_POST['cname'] ) );
            } else {
                update_site_option( 'dm_cname', '' );
            }

            // Save other options
            update_site_option( 'dm_301_redirect', isset( $_POST['permanent_redirect'] ) ? intval( $_POST['permanent_redirect'] ) : 0 );
            update_site_option( 'dm_redirect_admin', isset( $_POST['always_redirect_admin'] ) ? intval( $_POST['always_redirect_admin'] ) : 0 );
            update_site_option( 'dm_user_settings', isset( $_POST['dm_user_settings'] ) ? intval( $_POST['dm_user_settings'] ) : 0 );
            update_site_option( 'dm_no_primary_domain', isset( $_POST['dm_no_primary_domain'] ) ? intval( $_POST['dm_no_primary_domain'] ) : 0 );

            // Add settings saved notice
            add_settings_error(
                'dm_settings',
                'settings_updated',
                __( 'Settings saved.', 'wp-domain-mapping' ),
                'updated'
            );
        }

        // Load admin UI
        require_once( WP_DOMAIN_MAPPING_DIR_PATH . 'admin/settings-page.php' );
    }

    /**
     * Render user management page
     */
    public function render_manage_page() {
        global $wpdb, $parent_file;

        if ( isset( $_GET['updated'] ) ) {
            do_action( 'dm_echo_updated_msg' );
        }

        $this->sunrise_warning();

        if ( ! get_site_option( 'dm_ipaddress' ) && ! get_site_option( 'dm_cname' ) ) {
            if ( $this->is_site_admin() ) {
                echo wp_kses(
                    __( "Please set the IP address or CNAME of your server in the <a href='wpmu-admin.php?page=domain-mapping'>site admin page</a>.", 'wp-domain-mapping' ),
                    array( 'a' => array( 'href' => array() ) )
                );
            } else {
                esc_html_e( "This plugin has not been configured correctly yet.", 'wp-domain-mapping' );
            }
            return false;
        }

        $protocol = is_ssl() ? 'https://' : 'http://';
        $domains = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['domains']} WHERE blog_id = %d",
            $wpdb->blogid
        ), ARRAY_A );

        // Load user UI
        require_once( WP_DOMAIN_MAPPING_DIR_PATH . 'admin/user-page.php' );
    }

    /**
     * Add "Domains" link to sites list
     *
     * @param array $actions The row actions
     * @param int $blog_id The blog ID
     * @return array Modified actions
     */
    public function add_domain_link_to_sites( $actions, $blog_id ) {
        $domains_url = add_query_arg(
            array( 'page' => 'domains', 'blog_id' => $blog_id ),
            network_admin_url( 'sites.php' )
        );

        $actions['domains'] = '<a href="' . esc_url( $domains_url ) . '">' .
                              esc_html__( 'Domains', 'wp-domain-mapping' ) . '</a>';

        return $actions;
    }

    /**
     * Add domain mapping columns to sites list
     *
     * @param array $columns The columns
     * @return array Modified columns
     */
    public function add_domain_mapping_columns( $columns ) {
        $columns['map'] = __( 'Mapping' );
        return $columns;
    }

    /**
     * Display domain mapping column content
     *
     * @param string $column The column name
     * @param int $blog_id The blog ID
     */
    public function display_domain_mapping_column( $column, $blog_id ) {
        global $wpdb;
        static $maps = false;

        if ( $column == 'map' ) {
            if ( $maps === false ) {
                $work = $wpdb->get_results( "SELECT blog_id, domain FROM {$this->tables['domains']} ORDER BY blog_id" );
                $maps = array();

                if ( $work ) {
                    foreach ( $work as $blog ) {
                        $maps[$blog->blog_id][] = $blog->domain;
                    }
                }
            }

            if ( ! empty( $maps[$blog_id] ) && is_array( $maps[$blog_id] ) ) {
                foreach ( $maps[$blog_id] as $blog ) {
                    echo esc_html( $blog ) . '<br />';
                }
            }
        }
    }

    /**
     * Delete blog domain mappings when a blog is deleted
     *
     * @param int $blog_id The blog ID
     * @param bool $drop Whether to drop tables
     */
    public function delete_blog_domain_mapping( $blog_id, $drop ) {
        global $wpdb;

        if ( $blog_id && $drop ) {
            $domains = $wpdb->get_col( $wpdb->prepare(
                "SELECT domain FROM {$this->tables['domains']} WHERE blog_id = %d",
                $blog_id
            ));

            do_action( 'dm_delete_blog_domain_mappings', $domains );

            $wpdb->delete(
                $this->tables['domains'],
                array( 'blog_id' => $blog_id ),
                array( '%d' )
            );
        }
    }

    /**
     * Redirect to mapped domain
     */
    public function redirect_to_mapped_domain() {
        global $current_blog, $wpdb;

        if ( is_main_site() || ( isset( $_GET['preview'] ) && $_GET['preview'] == 'true' ) ||
            ( isset( $_POST['customize'] ) && isset( $_POST['theme'] ) && $_POST['customize'] == 'on' ) ) {
            return;
        }

        $protocol = is_ssl() ? 'https://' : 'http://';
        $url = $this->domain_mapping_siteurl( false );

        if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_blog->path ) ) {
            $redirect = get_site_option( 'dm_301_redirect' ) ? '301' : '302';

            if ( ( defined( 'VHOST' ) && constant("VHOST") != 'yes' ) ||
                 ( defined( 'SUBDOMAIN_INSTALL' ) && constant( 'SUBDOMAIN_INSTALL' ) == false ) ) {
                $_SERVER['REQUEST_URI'] = str_replace( $current_blog->path, '/', $_SERVER['REQUEST_URI'] );
            }

            wp_redirect( $url . $_SERVER['REQUEST_URI'], $redirect );
            exit;
        }
    }

    /**
     * Redirect admin area if needed
     */
    public function redirect_admin() {
        if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin/admin-ajax.php' ) !== false ) {
            return;
        }

        if ( get_site_option( 'dm_redirect_admin' ) ) {
            $url = $this->get_original_url( 'siteurl' );

            if ( false === strpos( $url, $_SERVER['HTTP_HOST'] ) ) {
                wp_redirect( untrailingslashit( $url ) . $_SERVER['REQUEST_URI'] );
                exit;
            }
        } else {
            global $current_blog;
            $url = $this->domain_mapping_siteurl( false );
            $request_uri = str_replace( $current_blog->path, '/', $_SERVER['REQUEST_URI'] );

            if ( false === strpos( $url, $_SERVER['HTTP_HOST'] ) ) {
                wp_redirect( str_replace( '//wp-admin', '/wp-admin', trailingslashit( $url ) . $request_uri ) );
                exit;
            }
        }
    }

    /**
     * Redirect login to original domain
     */
    public function redirect_login_to_orig() {
        if ( ! get_site_option( 'dm_remote_login' ) ||
             ( isset( $_GET['action'] ) && $_GET['action'] == 'logout' ) ||
             isset( $_GET['loggedout'] ) ) {
            return;
        }

        $url = $this->get_original_url( 'siteurl' );

        if ( $url != site_url() ) {
            $url .= "/wp-login.php";
            echo "<script type='text/javascript'>\nwindow.location = '" . esc_url( $url ) . "'</script>";
        }
    }

    /**
     * Handle logout across domains
     */
    public function remote_logout_loader() {
        global $current_site, $current_blog, $wpdb;

        $protocol = is_ssl() ? 'https://' : 'http://';
        $hash = $this->get_hash();
        $key = md5( time() );

        $wpdb->insert(
            $this->tables['logins'],
            array(
                'id' => $key,
                'user_id' => 0,
                'blog_id' => $current_blog->blog_id,
                't' => current_time( 'mysql' )
            ),
            array( '%s', '%d', '%d', '%s' )
        );

        if ( get_site_option( 'dm_redirect_admin' ) ) {
            wp_redirect( $protocol . $current_site->domain . $current_site->path .
                "?dm={$hash}&action=logout&blogid={$current_blog->blog_id}&k={$key}&t=" . mt_rand() );
            exit;
        }
    }

    /**
     * Handle remote login JS
     */
    public function remote_login_js() {
        global $current_blog, $current_user, $wpdb;

        if ( 0 == get_site_option( 'dm_remote_login' ) ) {
            return;
        }

        $hash = $this->get_hash();
        $protocol = is_ssl() ? 'https://' : 'http://';

        if ( $_GET['dm'] == $hash ) {
            if ( $_GET['action'] == 'load' ) {
                if ( ! is_user_logged_in() ) {
                    exit;
                }

                $key = md5( time() . mt_rand() );

                $wpdb->insert(
                    $this->tables['logins'],
                    array(
                        'id' => $key,
                        'user_id' => $current_user->ID,
                        'blog_id' => $_GET['blogid'],
                        't' => current_time( 'mysql' )
                    ),
                    array( '%s', '%d', '%d', '%s' )
                );

                $url = add_query_arg(
                    array(
                        'action' => 'login',
                        'dm' => $hash,
                        'k' => $key,
                        't' => mt_rand()
                    ),
                    $_GET['back']
                );

                echo "window.location = '" . esc_url( $url ) . "'";
                exit;

            } elseif ( $_GET['action'] == 'login' ) {
                $details = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$this->tables['logins']} WHERE id = %s AND blog_id = %d",
                    $_GET['k'], $wpdb->blogid
                ));

                if ( $details ) {
                    if ( $details->blog_id == $wpdb->blogid ) {
                        $wpdb->delete(
                            $this->tables['logins'],
                            array( 'id' => $_GET['k'] ),
                            array( '%s' )
                        );

                        $wpdb->query( $wpdb->prepare(
                            "DELETE FROM {$this->tables['logins']} WHERE t < %s",
                            date( 'Y-m-d H:i:s', time() - 120 )
                        ));

                        wp_set_auth_cookie( $details->user_id );

                        wp_redirect( remove_query_arg(
                            array( 'dm', 'action', 'k', 't' ),
                            $protocol . $current_blog->domain . $_SERVER['REQUEST_URI']
                        ));
                        exit;

                    } else {
                        wp_die( esc_html__( "Incorrect or out of date login key", 'wp-domain-mapping' ) );
                    }
                } else {
                    wp_die( esc_html__( "Unknown login key", 'wp-domain-mapping' ) );
                }

            } elseif ( $_GET['action'] == 'logout' ) {
                $details = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$this->tables['logins']} WHERE id = %s AND blog_id = %d",
                    $_GET['k'], $_GET['blogid']
                ));

                if ( $details ) {
                    $wpdb->delete(
                        $this->tables['logins'],
                        array( 'id' => $_GET['k'] ),
                        array( '%s' )
                    );

                    $blog = get_blog_details( $_GET['blogid'] );
                    wp_clear_auth_cookie();
                    wp_redirect( trailingslashit( $blog->siteurl ) . "wp-login.php?loggedout=true" );
                    exit;
                } else {
                    wp_die( esc_html__( "Unknown logout key", 'wp-domain-mapping' ) );
                }
            }
        }
    }

    /**
     * Add JS loader for remote login
     */
    public function remote_login_js_loader() {
        global $current_site, $current_blog;

        if ( 0 == get_site_option( 'dm_remote_login' ) || is_user_logged_in() ) {
            return;
        }

        $protocol = is_ssl() ? 'https://' : 'http://';
        $hash = $this->get_hash();

        echo "<script src='" . esc_url( $protocol . $current_site->domain . $current_site->path .
             "?dm={$hash}&action=load&blogid={$current_blog->blog_id}&siteid={$current_blog->site_id}&t=" .
             mt_rand() . "&back=" . urlencode( $protocol . $current_blog->domain . $_SERVER['REQUEST_URI'] ) ) .
             "' type='text/javascript'></script>";
    }

    /**
     * Map domain for site URL
     *
     * @param string $setting The setting value
     * @return string The mapped URL
     */
    public function domain_mapping_siteurl( $setting ) {
        global $wpdb, $current_blog;
        static $return_url = array();

        // Check if already cached
        if ( isset( $return_url[$wpdb->blogid] ) ) {
            if ( $return_url[$wpdb->blogid] !== false ) {
                return $return_url[$wpdb->blogid];
            }
            return $setting;
        }

        $s = $wpdb->suppress_errors();

        // Handle primary domain checking based on settings
        if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) {
            if ( isset( $_SERVER['HTTP_HOST'] ) ) {
                $domain = $wpdb->get_var( $wpdb->prepare(
                    "SELECT domain FROM {$this->tables['domains']} WHERE blog_id = %d AND domain = %s LIMIT 1",
                    $wpdb->blogid, $_SERVER['HTTP_HOST']
                ));

                if ( null == $domain ) {
                    $return_url[$wpdb->blogid] = untrailingslashit( $this->get_original_url( "siteurl" ) );
                    return $return_url[$wpdb->blogid];
                }
            }
        } else {
            $domain = $wpdb->get_var( $wpdb->prepare(
                "SELECT domain FROM {$this->tables['domains']} WHERE blog_id = %d AND active = 1 LIMIT 1",
                $wpdb->blogid
            ));

            if ( null == $domain ) {
                $return_url[$wpdb->blogid] = untrailingslashit( $this->get_original_url( "siteurl" ) );
                return $return_url[$wpdb->blogid];
            }
        }

        $wpdb->suppress_errors( $s );

        $protocol = is_ssl() ? 'https://' : 'http://';

        if ( $domain ) {
            $return_url[$wpdb->blogid] = untrailingslashit( $protocol . $domain );
            $setting = $return_url[$wpdb->blogid];
        } else {
            $return_url[$wpdb->blogid] = false;
        }

        return $setting;
    }

    /**
     * Get the original URL for a site
     *
     * @param string $url_type The URL type (siteurl or home)
     * @param int $blog_id The blog ID
     * @return string The original URL
     */
    public function get_original_url( $url_type, $blog_id = 0 ) {
        global $wpdb;
        $id = $blog_id ?: $wpdb->blogid;
        static $orig_urls = array();

        if ( ! isset( $orig_urls[$id] ) ) {
            // Remove filter to avoid infinite loop
            if ( defined( 'DOMAIN_MAPPING' ) ) {
                remove_filter( 'pre_option_' . $url_type, array( $this, 'domain_mapping_siteurl' ) );
            }

            $orig_url = $blog_id == 0 ? get_option( $url_type ) : get_blog_option( $blog_id, $url_type );
            $orig_url = is_ssl() ? str_replace( "http://", "https://", $orig_url ) : str_replace( "https://", "http://", $orig_url );
            $orig_urls[$id] = $orig_url;

            // Restore filter
            if ( defined( 'DOMAIN_MAPPING' ) ) {
                add_filter( 'pre_option_' . $url_type, array( $this, 'domain_mapping_siteurl' ) );
            }
        }

        return $orig_urls[$id];
    }

    /**
     * Map domain for admin URL
     *
     * @param string $url The URL
     * @param string $path The path
     * @param int $blog_id The blog ID
     * @return string The mapped URL
     */
    public function domain_mapping_adminurl( $url, $path, $blog_id = 0 ) {
        $index = strpos( $url, '/wp-admin' );

        if ( $index !== false ) {
            $url = $this->get_original_url( 'siteurl', $blog_id ) . substr( $url, $index );

            if ( ( is_ssl() || force_ssl_admin() ) && 0 === strpos( $url, 'http://' ) ) {
                $url = 'https://' . substr( $url, 7 );
            }
        }

        return $url;
    }

    /**
     * Map domains in post content
     *
     * @param string $post_content The post content
     * @return string The mapped content
     */
    public function domain_mapping_post_content( $post_content ) {
        $orig_url = $this->get_original_url( 'siteurl' );
        $url = $this->domain_mapping_siteurl( 'NA' );

        if ( $url == 'NA' ) {
            return $post_content;
        }

        return str_replace( $orig_url, $url, $post_content );
    }

    /**
     * Map domains in plugin URLs
     *
     * @param string $full_url The full URL
     * @param string $path The path
     * @param string $plugin The plugin
     * @return string The mapped URL
     */
    public function domain_mapping_plugins_uri( $full_url, $path = null, $plugin = null ) {
        return get_option( 'siteurl' ) . substr( $full_url, stripos( $full_url, PLUGINDIR ) - 1 );
    }

    /**
     * Map domains in theme URLs
     *
     * @param string $full_url The full URL
     * @return string The mapped URL
     */
    public function domain_mapping_themes_uri( $full_url ) {
        return str_replace( $this->get_original_url( 'siteurl' ), get_option( 'siteurl' ), $full_url );
    }

    /**
     * Get the domain mapping hash
     *
     * @return string The hash
     */
    public function get_hash() {
        $remote_login_hash = get_site_option( 'dm_hash' );

        if ( null == $remote_login_hash ) {
            $remote_login_hash = md5( time() );
            update_site_option( 'dm_hash', $remote_login_hash );
        }

        return $remote_login_hash;
    }

    /**
     * Check if user is a site admin
     *
     * @return bool True if user is a site admin
     */
    public function is_site_admin() {
        return current_user_can( 'manage_network' );
    }

    /**
     * Clean domain name
     *
     * @param string $domain The domain
     * @return string The cleaned domain
     */
    public function clean_domain( $domain ) {
        // Remove protocol
        $domain = preg_replace( '#^https?://#', '', $domain );

        // Remove trailing slash
        $domain = rtrim( $domain, '/' );

        // Convert to punycode for IDN
        if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^a-z0-9\-\.]/i', $domain ) ) {
            $domain = idn_to_ascii( $domain );
        }

        return $domain;
    }

    /**
     * Validate a domain name
     *
     * @param string $domain The domain
     * @return bool True if valid
     */
    public function is_valid_domain( $domain ) {
        // Basic validation
        return (bool) preg_match( '/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $domain );
    }

    /**
     * Get IDN warning message
     *
     * @return string The warning message
     */
    public function idn_warning() {
        return sprintf(
            /* translators: %s: URL to punycode converter */
            __( 'International Domain Names should be in <a href="%s" target="_blank">punycode</a> format.', 'wp-domain-mapping' ),
            "https://www.punycoder.com/"
        );
    }

    /**
     * Ensure URL has protocol
     *
     * @param string $domain The domain
     * @return string The URL with protocol
     */
    public function ensure_protocol( $domain ) {
        if ( preg_match( '#^https?://#', $domain ) ) {
            return $domain;
        }
        return 'http://' . $domain;
    }

    /**
     * Default updated messages
     */
    public function echo_default_updated_msg() {
        if ( ! isset( $_GET['updated'] ) ) {
            return;
        }

        switch ( $_GET['updated'] ) {
            case "add":
                $msg = __( 'New domain added.', 'wp-domain-mapping' );
                break;
            case "exists":
                $msg = __( 'New domain already exists.', 'wp-domain-mapping' );
                break;
            case "primary":
                $msg = __( 'New primary domain.', 'wp-domain-mapping' );
                break;
            case "del":
                $msg = __( 'Domain deleted.', 'wp-domain-mapping' );
                break;
            default:
                return;
        }

        echo "<div class='notice notice-success'><p>" . esc_html( $msg ) . "</p></div>";
    }
}

// Initialize the plugin
function wp_domain_mapping_init() {
    // Add default updated messages action
    add_action( 'dm_echo_updated_msg', array( WP_Domain_Mapping::get_instance(), 'echo_default_updated_msg' ) );

    // Initialize the plugin
    return WP_Domain_Mapping::get_instance();
}
wp_domain_mapping_init();

// Include admin UI templates
require_once( WP_DOMAIN_MAPPING_DIR_PATH . 'includes/admin-ui.php' );
