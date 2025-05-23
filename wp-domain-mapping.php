<?php
/**
 * Plugin Name: WP Domain Mapping
 * Plugin URI: https://wenpai.org/plugins/wp-domain-mapping/
 * Description: Map any site on a WordPress website to another domain with enhanced management features.
 * Version: 2.0.2
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
define( 'WP_DOMAIN_MAPPING_VERSION', '2.0.2' );
define( 'WP_DOMAIN_MAPPING_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_DOMAIN_MAPPING_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_DOMAIN_MAPPING_BASENAME', plugin_basename( __FILE__ ) );

// Define table name constants if not already defined
if ( ! defined( 'WP_DOMAIN_MAPPING_TABLE_DOMAINS' ) ) {
    define( 'WP_DOMAIN_MAPPING_TABLE_DOMAINS', 'domain_mapping' );
    define( 'WP_DOMAIN_MAPPING_TABLE_LOGINS', 'domain_mapping_logins' );
    define( 'WP_DOMAIN_MAPPING_TABLE_LOGS', 'domain_mapping_logs' );
}

// Include required files
require_once WP_DOMAIN_MAPPING_DIR_PATH . 'includes/functions.php';
require_once WP_DOMAIN_MAPPING_DIR_PATH . 'includes/class-core.php';
require_once WP_DOMAIN_MAPPING_DIR_PATH . 'includes/class-admin.php';
require_once WP_DOMAIN_MAPPING_DIR_PATH . 'includes/class-tools.php';

/**
 * Main Domain Mapping Class
 */
class WP_Domain_Mapping {

    /**
     * Plugin instance
     *
     * @var WP_Domain_Mapping
     */
    private static $instance = null;

    /**
     * Core instance
     *
     * @var WP_Domain_Mapping_Core
     */
    public $core;

    /**
     * Admin instance
     *
     * @var WP_Domain_Mapping_Admin
     */
    public $admin;

    /**
     * Tools instance
     *
     * @var WP_Domain_Mapping_Tools
     */
    public $tools;

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

        // Initialize components
        $this->core = WP_Domain_Mapping_Core::get_instance();
        $this->admin = WP_Domain_Mapping_Admin::get_instance();
        $this->tools = WP_Domain_Mapping_Tools::get_instance();

        // Check plugin requirements on activation
        register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
        register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
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
     * Actions to perform on plugin activation
     */
    public function plugin_activation() {
        // Create database tables
        $this->core->create_tables();

        // Initialize remote login hash
        $this->core->get_hash();

        // Schedule health check
        $this->tools->schedule_health_check();
    }

    /**
     * Actions to perform on plugin deactivation
     */
    public function plugin_deactivation() {
        // Unschedule health check
        $this->tools->unschedule_health_check();
    }
}

// Initialize the plugin
function wp_domain_mapping_init() {
    return WP_Domain_Mapping::get_instance();
}
wp_domain_mapping_init();
