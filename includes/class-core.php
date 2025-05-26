<?php
/**
 * Core functionality for WP Domain Mapping plugin
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP Domain Mapping Core Class
 */
class WP_Domain_Mapping_Core {

    /**
     * Class instance
     *
     * @var WP_Domain_Mapping_Core
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
     * @return WP_Domain_Mapping_Core
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
     * Initialize core functionality
     */
    private function init() {
        // Setup general hooks
        $this->setup_general_hooks();

        // Setup domain mapping filters if DOMAIN_MAPPING is defined
        if ( defined( 'DOMAIN_MAPPING' ) ) {
            $this->setup_domain_mapping_filters();
        } else {
            add_filter( 'admin_url', array( $this, 'domain_mapping_adminurl' ), 10, 3 );
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
     * Create required database tables - OPTIMIZED
     */
    public function create_tables() {
        global $wpdb;

        // Only network admins can create tables
        if ( ! dm_is_site_admin() ) {
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

        // Create domain_mapping table - IMPROVED INDEXES
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['domains']}'" ) != $this->tables['domains'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['domains']}` (
                `id` bigint(20) NOT NULL auto_increment,
                `blog_id` bigint(20) NOT NULL,
                `domain` varchar(255) NOT NULL,
                `active` tinyint(4) default '1',
                PRIMARY KEY  (`id`),
                UNIQUE KEY `domain` (`domain`),
                KEY `blog_id` (`blog_id`),
                KEY `blog_active` (`blog_id`, `active`),
                KEY `active_domain` (`active`, `domain`)
            ) $charset_collate;" );
            $created = 1;
        }

        // Create domain_mapping_logins table - IMPROVED INDEXES
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['logins']}'" ) != $this->tables['logins'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['logins']}` (
                `id` varchar(32) NOT NULL,
                `user_id` bigint(20) NOT NULL,
                `blog_id` bigint(20) NOT NULL,
                `t` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                PRIMARY KEY  (`id`),
                KEY `user_blog` (`user_id`, `blog_id`),
                KEY `timestamp` (`t`)
            ) $charset_collate;" );
            $created = 1;
        }

        // Create domain_mapping_logs table - IMPROVED INDEXES
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->tables['logs']}'" ) != $this->tables['logs'] ) {
            $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$this->tables['logs']}` (
                `id` bigint(20) NOT NULL auto_increment,
                `user_id` bigint(20) NOT NULL,
                `action` varchar(50) NOT NULL,
                `domain` varchar(255) NOT NULL,
                `blog_id` bigint(20) NOT NULL,
                `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `blog_timestamp` (`blog_id`, `timestamp`),
                KEY `user_action` (`user_id`, `action`),
                KEY `domain_action` (`domain`, `action`),
                KEY `timestamp` (`timestamp`)
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
        $key = wp_generate_password(32, true, true);

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
                     // FIX: Add time validation that was missing
                     if ( strtotime( $details->t ) < ( time() - 300 ) ) {
                         wp_die( __( 'Login key expired', 'wp-domain-mapping' ) );
                     }

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
                     // FIX: Add time validation for logout too
                     if ( strtotime( $details->t ) < ( time() - 300 ) ) {
                         wp_die( __( 'Logout key expired', 'wp-domain-mapping' ) );
                     }

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
}
