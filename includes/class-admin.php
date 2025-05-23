<?php
/**
 * Admin functionality for WP Domain Mapping plugin
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP Domain Mapping Admin Class
 */
class WP_Domain_Mapping_Admin {

    /**
     * Class instance
     *
     * @var WP_Domain_Mapping_Admin
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
     * @return WP_Domain_Mapping_Admin
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
     * Initialize admin functionality
     */
    private function init() {
        // Setup admin-related hooks
        $this->setup_admin_hooks();

        // Load admin pages
        require_once WP_DOMAIN_MAPPING_DIR_PATH . 'admin/pages.php';
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

        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( ! in_array( $hook, array( 'sites_page_domains', 'settings_page_domain-mapping', 'tools_page_domainmapping' ) ) ) {
            return;
        }

        wp_enqueue_style(
            'wp-domain-mapping-admin',
            WP_DOMAIN_MAPPING_DIR_URL . 'assets/css/admin.css',
            array(),
            WP_DOMAIN_MAPPING_VERSION
        );

        wp_enqueue_script(
            'wp-domain-mapping-admin',
            WP_DOMAIN_MAPPING_DIR_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WP_DOMAIN_MAPPING_VERSION,
            true
        );

        wp_localize_script( 'wp-domain-mapping-admin', 'wpDomainMapping', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'domain_mapping' ),
            'messages' => array(
                'domainRequired' => __( 'Domain is required.', 'wp-domain-mapping' ),
                'siteRequired' => __( 'Site ID is required.', 'wp-domain-mapping' ),
                'saving' => __( 'Saving...', 'wp-domain-mapping' ),
                'processing' => __( 'Processing...', 'wp-domain-mapping' ),
                'error' => __( 'An error occurred.', 'wp-domain-mapping' ),
                'noSelection' => __( 'Please select at least one domain.', 'wp-domain-mapping' ),
            )
        ));
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
            if ( dm_is_site_admin() ) {
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
            if ( dm_is_site_admin() ) {
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
            if ( dm_is_site_admin() ) {
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
     * AJAX handler for domain actions
     */
    public function ajax_handle_actions() {
        check_ajax_referer( 'domain_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-domain-mapping' ) );
        }

        global $wpdb;

        $action = sanitize_text_field( $_POST['action_type'] );
        $domain = dm_clean_domain( sanitize_text_field( isset( $_POST['domain'] ) ? strtolower( $_POST['domain'] ) : '' ) );
        $blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
        $active = isset( $_POST['active'] ) ? absint( $_POST['active'] ) : 0;
        $orig_domain = isset( $_POST['orig_domain'] ) ? dm_clean_domain( sanitize_text_field( $_POST['orig_domain'] ) ) : '';
        $current_user_id = get_current_user_id();

        switch ( $action ) {
            case 'save':
                if ( $blog_id != 0 && $blog_id != 1 ) {
                    // Validate domain format
                    if ( ! dm_validate_domain( $domain ) ) {
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
                                    dm_log_action( 'add', $domain, $blog_id, $current_user_id );

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
                                    dm_log_action( 'edit', $domain, $blog_id, $current_user_id );

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
                                dm_log_action( 'delete', $del_domain, $affected_blog_id, $current_user_id );
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
                    if ( ! dm_validate_domain( $domain ) ) {
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
                    $core = WP_Domain_Mapping_Core::get_instance();
                    $orig_url = parse_url( $core->get_original_url( 'siteurl' ) );

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
        if ( ! dm_is_site_admin() ) {
            return false;
        }

        $this->sunrise_warning();

        // Include the admin page template
        dm_render_domains_page();
    }

    /**
     * Render admin configuration page
     */
    public function render_admin_page() {
        if ( ! dm_is_site_admin() ) {
            return false;
        }

        $this->sunrise_warning();

        global $current_site;
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

        // Include the admin page template
        dm_render_admin_page();
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
            if ( dm_is_site_admin() ) {
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
        $domains = dm_get_domains_by_blog_id( $wpdb->blogid );

        // Include the user page template
        dm_render_user_page( $protocol, $domains );
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

// Add default updated messages action
add_action( 'dm_echo_updated_msg', array( WP_Domain_Mapping_Admin::class, 'echo_default_updated_msg' ) );
