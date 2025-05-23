<?php
/**
 * Admin page templates for WP Domain Mapping plugin
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render main admin page with tabs
 */
function dm_render_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?>
            <span style="font-size: 13px; padding-left: 10px;">
                <?php printf( esc_html__( 'Version: %s', 'wp-domain-mapping' ), esc_html( WP_DOMAIN_MAPPING_VERSION ) ); ?>
            </span>
            <a href="https://wpmultisite.com/document/wp-domain-mapping" target="_blank" class="button button-secondary" style="margin-left: 10px;">
                <?php esc_html_e( 'Document', 'wp-domain-mapping' ); ?>
            </a>
            <a href="https://wpmultisite.com/forums/" target="_blank" class="button button-secondary">
                <?php esc_html_e( 'Support', 'wp-domain-mapping' ); ?>
            </a>
        </h1>

        <?php
        // Display settings errors
        settings_errors( 'dm_settings' );

        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
        $tabs = array(
            'settings' => __( 'Settings', 'wp-domain-mapping' ),
            'health' => __( 'Domain Health', 'wp-domain-mapping' ),
            'import-export' => __( 'Import/Export', 'wp-domain-mapping' )
        );

        // Display success messages for tabs
        if ( isset( $_GET['checked'] ) && $_GET['checked'] && $current_tab === 'health' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __( 'Domain health check completed.', 'wp-domain-mapping' ) .
                 '</p></div>';
        }

        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] && $current_tab === 'health' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __( 'Settings saved.', 'wp-domain-mapping' ) .
                 '</p></div>';
        }

        if ( isset( $_GET['imported'] ) && $_GET['imported'] && $current_tab === 'import-export' ) {
            $count = intval( $_GET['imported'] );
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(
                     _n(
                         '%d domain mapping imported successfully.',
                         '%d domain mappings imported successfully.',
                         $count,
                         'wp-domain-mapping'
                     ),
                     $count
                 ) .
                 '</p></div>';
        }

        if ( isset( $_GET['export'] ) && $_GET['export'] == 'success' && $current_tab === 'import-export' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __( 'Domain mappings exported successfully.', 'wp-domain-mapping' ) .
                 '</p></div>';
        }
        ?>

        <!-- Tab Navigation -->
        <div class="domain-mapping-tabs">
            <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                <button type="button" class="domain-mapping-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>"
                        data-tab="<?php echo esc_attr( $tab_key ); ?>"
                        onclick="switchTab('<?php echo esc_js( $tab_key ); ?>')">
                    <?php echo esc_html( $tab_label ); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Tab Content -->
        <div class="domain-mapping-content">
            <!-- Settings Tab -->
            <div class="domain-mapping-section" data-section="settings" <?php echo $current_tab !== 'settings' ? 'style="display:none;"' : ''; ?>>
                <?php dm_render_settings_content(); ?>
            </div>

            <!-- Health Tab -->
            <div class="domain-mapping-section" data-section="health" <?php echo $current_tab !== 'health' ? 'style="display:none;"' : ''; ?>>
                <?php dm_render_health_content(); ?>
            </div>

            <!-- Import/Export Tab -->
            <div class="domain-mapping-section" data-section="import-export" <?php echo $current_tab !== 'import-export' ? 'style="display:none;"' : ''; ?>>
                <?php dm_render_import_export_content(); ?>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        // Update URL without reloading
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);

        // Update active tab
        document.querySelectorAll('.domain-mapping-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

        // Update visible section
        document.querySelectorAll('.domain-mapping-section').forEach(s => s.style.display = 'none');
        document.querySelector(`[data-section="${tab}"]`).style.display = 'block';
    }
    </script>
    <?php
}

/**
 * Render settings tab content
 */
function dm_render_settings_content() {
    // Get current options
    $dm_ipaddress = get_site_option( 'dm_ipaddress', '' );
    $dm_cname = get_site_option( 'dm_cname', '' );
    $dm_remote_login = get_site_option( 'dm_remote_login', 1 );
    $dm_301_redirect = get_site_option( 'dm_301_redirect', 0 );
    $dm_redirect_admin = get_site_option( 'dm_redirect_admin', 1 );
    $dm_user_settings = get_site_option( 'dm_user_settings', 1 );
    $dm_no_primary_domain = get_site_option( 'dm_no_primary_domain', 0 );

    // Get server IP address if not set
    if ( empty( $dm_ipaddress ) ) {
        $server_ip = isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '';
        if ( empty( $server_ip ) && function_exists( 'gethostbyname' ) ) {
            $server_ip = gethostbyname( $_SERVER['SERVER_NAME'] );
        }
        if ( $server_ip && filter_var( $server_ip, FILTER_VALIDATE_IP ) ) {
            $dm_ipaddress = $server_ip;
        }
    }
    ?>
    <div class="card domain-mapping-card">
        <form method="POST">
            <input type="hidden" name="action" value="update" />
            <?php wp_nonce_field( 'domain_mapping' ); ?>

            <h2><?php esc_html_e( 'Server Configuration', 'wp-domain-mapping' ); ?></h2>
            <p><?php esc_html_e( 'Configure the IP address or CNAME for domain mapping.', 'wp-domain-mapping' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ipaddress"><?php esc_html_e( 'Server IP Address:', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input type="text" id="ipaddress" name="ipaddress" value="<?php echo esc_attr( $dm_ipaddress ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Enter the IP address(es) users should point their DNS A records to. Use commas to separate multiple IPs.', 'wp-domain-mapping' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cname"><?php esc_html_e( 'Server CNAME Domain:', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input type="text" id="cname" name="cname" value="<?php echo esc_attr( $dm_cname ); ?>" class="regular-text" placeholder="server.example.com" />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: IDN warning message */
                                esc_html__( 'Use a CNAME instead of an IP (overrides IP settings). %s', 'wp-domain-mapping' ),
                                dm_idn_warning()
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Domain Options', 'wp-domain-mapping' ); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'Domain Options', 'wp-domain-mapping' ); ?></legend>
                            <label for="dm_remote_login">
                                <input type="checkbox" name="dm_remote_login" id="dm_remote_login" value="1" <?php checked( $dm_remote_login, 1 ); ?> />
                                <?php esc_html_e( 'Enable Remote Login', 'wp-domain-mapping' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Allows users to log in from mapped domains and be redirected to the original domain for authentication.', 'wp-domain-mapping' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'Permanent Redirect', 'wp-domain-mapping' ); ?></legend>
                            <label for="permanent_redirect">
                                <input type="checkbox" name="permanent_redirect" id="permanent_redirect" value="1" <?php checked( $dm_301_redirect, 1 ); ?> />
                                <?php esc_html_e( 'Use Permanent Redirect (301)', 'wp-domain-mapping' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Use 301 redirects instead of 302 redirects. This is better for SEO but may cause caching issues.', 'wp-domain-mapping' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'User Settings', 'wp-domain-mapping' ); ?></legend>
                            <label for="dm_user_settings">
                                <input type="checkbox" name="dm_user_settings" id="dm_user_settings" value="1" <?php checked( $dm_user_settings, 1 ); ?> />
                                <?php esc_html_e( 'Enable User Domain Mapping Page', 'wp-domain-mapping' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Allow site administrators to manage their domain mappings from the Tools menu.', 'wp-domain-mapping' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'Redirect Admin', 'wp-domain-mapping' ); ?></legend>
                            <label for="always_redirect_admin">
                                <input type="checkbox" name="always_redirect_admin" id="always_redirect_admin" value="1" <?php checked( $dm_redirect_admin, 1 ); ?> />
                                <?php esc_html_e( 'Redirect Admin Pages to Original Domain', 'wp-domain-mapping' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Force admin pages to use the original WordPress domain instead of the mapped domain.', 'wp-domain-mapping' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'Disable Primary Domain', 'wp-domain-mapping' ); ?></legend>
                            <label for="dm_no_primary_domain">
                                <input type="checkbox" name="dm_no_primary_domain" id="dm_no_primary_domain" value="1" <?php checked( $dm_no_primary_domain, 1 ); ?> />
                                <?php esc_html_e( 'Disable Primary Domain Check', 'wp-domain-mapping' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Do not redirect to the primary domain, but allow access through any mapped domain.', 'wp-domain-mapping' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Configuration', 'wp-domain-mapping' ); ?>" />
                <a href="<?php echo esc_url( admin_url( 'network/sites.php?page=domains' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Manage Domains', 'wp-domain-mapping' ); ?>
                </a>
            </p>
        </form>
    </div>

    <!-- DNS Instructions and Installation Check sections from settings-page.php -->
    <?php dm_render_dns_instructions( $dm_ipaddress, $dm_cname ); ?>
    <?php dm_render_installation_check(); ?>
    <?php
}

/**
 * Render DNS instructions
 */
function dm_render_dns_instructions( $dm_ipaddress, $dm_cname ) {
    ?>
    <div class="card domain-mapping-card">
        <h2><?php esc_html_e( 'DNS Setup Instructions', 'wp-domain-mapping' ); ?></h2>

        <div class="dns-instructions">
            <?php if ( ! empty( $dm_cname ) ) : ?>
                <h3><?php esc_html_e( 'CNAME Method (Recommended)', 'wp-domain-mapping' ); ?></h3>
                <p>
                    <?php
                    printf(
                        /* translators: %s: CNAME value */
                        esc_html__( 'Tell your users to add a DNS "CNAME" record for their domain pointing to: %s', 'wp-domain-mapping' ),
                        '<code>' . esc_html( $dm_cname ) . '</code>'
                    );
                    ?>
                </p>
                <div class="dns-example">
                    <h4><?php esc_html_e( 'Example DNS Record', 'wp-domain-mapping' ); ?></h4>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Type', 'wp-domain-mapping' ); ?></th>
                                <th><?php esc_html_e( 'Name', 'wp-domain-mapping' ); ?></th>
                                <th><?php esc_html_e( 'Value', 'wp-domain-mapping' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>CNAME</code></td>
                                <td><code>@</code> <?php esc_html_e( '(or empty)', 'wp-domain-mapping' ); ?></td>
                                <td><code><?php echo esc_html( $dm_cname ); ?></code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $dm_ipaddress ) ) : ?>
                <h3><?php esc_html_e( 'A Record Method', 'wp-domain-mapping' ); ?></h3>
                <p>
                    <?php
                    printf(
                        /* translators: %s: IP address(es) */
                        esc_html__( 'Tell your users to add a DNS "A" record for their domain pointing to: %s', 'wp-domain-mapping' ),
                        '<code>' . esc_html( $dm_ipaddress ) . '</code>'
                    );
                    ?>
                </p>
                <div class="dns-example">
                    <h4><?php esc_html_e( 'Example DNS Record', 'wp-domain-mapping' ); ?></h4>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Type', 'wp-domain-mapping' ); ?></th>
                                <th><?php esc_html_e( 'Name', 'wp-domain-mapping' ); ?></th>
                                <th><?php esc_html_e( 'Value', 'wp-domain-mapping' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $ips = array_map( 'trim', explode( ',', $dm_ipaddress ) );
                            foreach ( $ips as $index => $ip ) :
                            ?>
                            <tr>
                                <td><code>A</code></td>
                                <td><code>@</code> <?php esc_html_e( '(or empty)', 'wp-domain-mapping' ); ?></td>
                                <td><code><?php echo esc_html( $ip ); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ( empty( $dm_ipaddress ) && empty( $dm_cname ) ) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e( 'Please configure either a Server IP Address or CNAME to provide DNS setup instructions.', 'wp-domain-mapping' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <h3><?php esc_html_e( 'Additional DNS Tips', 'wp-domain-mapping' ); ?></h3>
            <ul class="dns-tips">
                <li><?php esc_html_e( 'Most DNS changes take 24-48 hours to fully propagate worldwide.', 'wp-domain-mapping' ); ?></li>
                <li><?php esc_html_e( 'For "www" subdomain, create a separate CNAME record with "www" as the name pointing to the same value.', 'wp-domain-mapping' ); ?></li>
                <li><?php esc_html_e( 'If you\'re using Cloudflare or similar services, you may need to adjust proxy settings.', 'wp-domain-mapping' ); ?></li>
                <li><?php esc_html_e( 'For SSL to work properly, make sure your web server is configured with the appropriate SSL certificates for mapped domains.', 'wp-domain-mapping' ); ?></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Render installation check
 */
function dm_render_installation_check() {
    ?>
    <div class="card domain-mapping-card">
        <h2><?php esc_html_e( 'Installation Check', 'wp-domain-mapping' ); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Status', 'wp-domain-mapping' ); ?></th>
                    <th><?php esc_html_e( 'Check', 'wp-domain-mapping' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'wp-domain-mapping' ); ?></th>
                </tr>
                <tr>
                    <td>
                        <?php if ( file_exists( WP_CONTENT_DIR . '/sunrise.php' ) ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'sunrise.php file', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( file_exists( WP_CONTENT_DIR . '/sunrise.php' ) ) : ?>
                            <?php esc_html_e( 'Found', 'wp-domain-mapping' ); ?>
                        <?php else : ?>
                            <?php
                            printf(
                                /* translators: %s: WordPress content directory */
                                esc_html__( 'Not found - copy sunrise.php to %s', 'wp-domain-mapping' ),
                                '<code>' . esc_html( WP_CONTENT_DIR ) . '</code>'
                            );
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php if ( defined( 'SUNRISE' ) ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'SUNRISE constant', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( defined( 'SUNRISE' ) ) : ?>
                            <?php
                            printf(
                                /* translators: %s: SUNRISE constant value */
                                esc_html__( 'Defined as: %s', 'wp-domain-mapping' ),
                                '<code>' . esc_html( SUNRISE ) . '</code>'
                            );
                            ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Not defined - add to wp-config.php: ', 'wp-domain-mapping' ); ?>
                            <code>define( 'SUNRISE', 'on' );</code>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php if ( defined( 'SUNRISE_LOADED' ) ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'SUNRISE_LOADED', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( defined( 'SUNRISE_LOADED' ) ) : ?>
                            <?php esc_html_e( 'Loaded successfully', 'wp-domain-mapping' ); ?>
                        <?php else : ?>
                            <?php
                            if ( defined( 'SUNRISE' ) ) {
                                esc_html_e( 'Not loaded - make sure SUNRISE is defined before the require_once() in wp-config.php', 'wp-domain-mapping' );
                            } else {
                                esc_html_e( 'Not loaded - SUNRISE constant not defined', 'wp-domain-mapping' );
                            }
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php if ( ! defined( 'COOKIE_DOMAIN' ) ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'COOKIE_DOMAIN', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( ! defined( 'COOKIE_DOMAIN' ) ) : ?>
                            <?php esc_html_e( 'Not defined (correct)', 'wp-domain-mapping' ); ?>
                        <?php else : ?>
                            <?php
                            printf(
                                /* translators: %s: COOKIE_DOMAIN constant value */
                                esc_html__( 'Defined as: %s - remove this from wp-config.php', 'wp-domain-mapping' ),
                                '<code>' . esc_html( COOKIE_DOMAIN ) . '</code>'
                            );
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php
                        global $wpdb;
                        $tables = dm_get_table_names();
                        $tables_exist = true;
                        foreach ( $tables as $table ) {
                            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
                                $tables_exist = false;
                                break;
                            }
                        }
                        ?>
                        <?php if ( $tables_exist ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'Database tables', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( $tables_exist ) : ?>
                            <?php esc_html_e( 'All tables exist', 'wp-domain-mapping' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Some tables are missing - deactivate and reactivate the plugin', 'wp-domain-mapping' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php if ( is_multisite() ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e( 'Multisite', 'wp-domain-mapping' ); ?></td>
                    <td>
                        <?php if ( is_multisite() ) : ?>
                            <?php esc_html_e( 'Enabled', 'wp-domain-mapping' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Not enabled - this plugin requires WordPress Multisite', 'wp-domain-mapping' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Render health tab content
 */
function dm_render_health_content() {
    // Get all domains
    global $wpdb;
    $tables = dm_get_table_names();
    $domains = $wpdb->get_results("
        SELECT d.*, b.domain as original_domain, b.path
        FROM {$tables['domains']} d
        JOIN {$wpdb->blogs} b ON d.blog_id = b.blog_id
        ORDER BY d.blog_id ASC, d.active DESC
    ");

    // Get health check results
    $health_results = get_site_option( 'dm_domain_health_results', array() );
    ?>

    <div class="card domain-mapping-card">
        <h2><?php _e( 'Domain Health Status', 'wp-domain-mapping' ); ?></h2>

        <p>
            <form method="post" action="">
                <?php wp_nonce_field( 'dm_manual_health_check', 'dm_manual_health_check_nonce' ); ?>
                <input type="hidden" name="dm_manual_health_check" value="1">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Check All Domains Now', 'wp-domain-mapping' ); ?>">
            </form>
        </p>

        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php
                    if ( ! empty( $domains ) ) {
                        printf(
                            _n( '%s domain', '%s domains', count( $domains ), 'wp-domain-mapping' ),
                            number_format_i18n( count( $domains ) )
                        );
                    } else {
                        _e( 'No domains found', 'wp-domain-mapping' );
                    }
                    ?>
                </span>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped domains-health-table">
            <thead>
                <tr>
                    <th class="column-domain"><?php _e( 'Domain', 'wp-domain-mapping' ); ?></th>
                    <th class="column-site"><?php _e( 'Site', 'wp-domain-mapping' ); ?></th>
                    <th class="column-dns"><?php _e( 'DNS Status', 'wp-domain-mapping' ); ?></th>
                    <th class="column-ssl"><?php _e( 'SSL Status', 'wp-domain-mapping' ); ?></th>
                    <th class="column-status"><?php _e( 'Reachable', 'wp-domain-mapping' ); ?></th>
                    <th class="column-last-check"><?php _e( 'Last Check', 'wp-domain-mapping' ); ?></th>
                    <th class="column-actions"><?php _e( 'Actions', 'wp-domain-mapping' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $domains ) ) : ?>
                    <?php foreach ( $domains as $domain ) :
                        $domain_key = md5( $domain->domain );
                        $health_data = isset( $health_results[$domain_key] ) ? $health_results[$domain_key] : null;
                        $site_name = get_blog_option( $domain->blog_id, 'blogname', __( 'Unknown', 'wp-domain-mapping' ) );
                    ?>
                        <tr data-domain="<?php echo esc_attr( $domain->domain ); ?>" data-blog-id="<?php echo esc_attr( $domain->blog_id ); ?>">
                            <td class="column-domain">
                                <?php echo esc_html( $domain->domain ); ?>
                                <?php if ( $domain->active ) : ?>
                                    <span class="dashicons dashicons-star-filled" style="color: #f0b849;" title="<?php esc_attr_e( 'Primary Domain', 'wp-domain-mapping' ); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-site">
                                <a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $domain->blog_id ) ); ?>">
                                    <?php echo esc_html( $site_name ); ?>
                                    <div class="row-actions">
                                        <span class="original-domain"><?php echo esc_html( $domain->original_domain . $domain->path ); ?></span>
                                    </div>
                                </a>
                            </td>
                            <td class="column-dns">
                                <?php if ( $health_data && isset( $health_data['dns_status'] ) ) : ?>
                                    <?php if ( $health_data['dns_status'] === 'success' ) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e( 'DNS correctly configured', 'wp-domain-mapping' ); ?>"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php echo esc_attr( $health_data['dns_message'] ); ?>"></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e( 'Not checked yet', 'wp-domain-mapping' ); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-ssl">
                                <?php if ( $health_data && isset( $health_data['ssl_valid'] ) ) : ?>
                                    <?php if ( $health_data['ssl_valid'] ) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e( 'SSL certificate valid', 'wp-domain-mapping' ); ?>"></span>
                                        <div class="row-actions">
                                            <span><?php echo esc_html( sprintf( __( 'Expires: %s', 'wp-domain-mapping' ), isset( $health_data['ssl_expiry'] ) ? date( 'Y-m-d', strtotime( $health_data['ssl_expiry'] ) ) : '-' ) ); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php esc_attr_e( 'SSL certificate issue', 'wp-domain-mapping' ); ?>"></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e( 'Not checked yet', 'wp-domain-mapping' ); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php if ( $health_data && isset( $health_data['accessible'] ) ) : ?>
                                    <?php if ( $health_data['accessible'] ) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e( 'Site is accessible', 'wp-domain-mapping' ); ?>"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php esc_attr_e( 'Site is not accessible', 'wp-domain-mapping' ); ?>"></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e( 'Not checked yet', 'wp-domain-mapping' ); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-last-check">
                                <?php
                                if ( $health_data && isset( $health_data['last_check'] ) ) {
                                    echo esc_html( human_time_diff( strtotime( $health_data['last_check'] ), current_time( 'timestamp' ) ) ) . ' ' . __( 'ago', 'wp-domain-mapping' );
                                } else {
                                    _e( 'Never', 'wp-domain-mapping' );
                                }
                                ?>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button button-small check-domain-health" data-domain="<?php echo esc_attr( $domain->domain ); ?>">
                                    <?php _e( 'Check Now', 'wp-domain-mapping' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php _e( 'No domains found.', 'wp-domain-mapping' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card domain-mapping-card">
        <h2><?php _e( 'Health Check Settings', 'wp-domain-mapping' ); ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field( 'dm_health_settings', 'dm_health_settings_nonce' ); ?>
            <input type="hidden" name="dm_health_settings" value="1">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e( 'Automatic Health Checks', 'wp-domain-mapping' ); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e( 'Automatic Health Checks', 'wp-domain-mapping' ); ?></span></legend>
                            <label for="health_check_enabled">
                                <input name="health_check_enabled" type="checkbox" id="health_check_enabled" value="1" <?php checked( get_site_option( 'dm_health_check_enabled', true ) ); ?>>
                                <?php _e( 'Enable automatic daily health checks', 'wp-domain-mapping' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Email Notifications', 'wp-domain-mapping' ); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e( 'Email Notifications', 'wp-domain-mapping' ); ?></span></legend>
                            <label for="health_notifications_enabled">
                                <input name="health_notifications_enabled" type="checkbox" id="health_notifications_enabled" value="1" <?php checked( get_site_option( 'dm_health_notifications_enabled', true ) ); ?>>
                                <?php _e( 'Send email notifications when domain health issues are detected', 'wp-domain-mapping' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="notification_email"><?php _e( 'Notification Email', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input name="notification_email" type="email" id="notification_email" class="regular-text" value="<?php echo esc_attr( get_site_option( 'dm_notification_email', get_option( 'admin_email' ) ) ); ?>">
                        <p class="description"><?php _e( 'Email address for domain health notifications.', 'wp-domain-mapping' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ssl_expiry_threshold"><?php _e( 'SSL Expiry Warning', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input name="ssl_expiry_threshold" type="number" id="ssl_expiry_threshold" min="1" max="90" class="small-text" value="<?php echo esc_attr( get_site_option( 'dm_ssl_expiry_threshold', 14 ) ); ?>">
                        <span><?php _e( 'days', 'wp-domain-mapping' ); ?></span>
                        <p class="description"><?php _e( 'Send notifications when SSL certificates are expiring within this many days.', 'wp-domain-mapping' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-domain-mapping' ); ?>">
            </p>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Single domain health check
        $('.check-domain-health').on('click', function() {
            var $button = $(this);
            var domain = $button.data('domain');
            var $row = $button.closest('tr');

            $button.prop('disabled', true).text('<?php esc_html_e( 'Checking...', 'wp-domain-mapping' ); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dm_check_domain_health',
                    domain: domain,
                    nonce: '<?php echo wp_create_nonce( 'dm_check_domain_health' ); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Refresh page to show updated results
                        location.reload();
                    } else {
                        alert(response.data || '<?php esc_html_e( 'An error occurred during the health check.', 'wp-domain-mapping' ); ?>');
                        $button.prop('disabled', false).text('<?php esc_html_e( 'Check Now', 'wp-domain-mapping' ); ?>');
                    }
                },
                error: function() {
                    alert('<?php esc_html_e( 'An error occurred during the health check.', 'wp-domain-mapping' ); ?>');
                    $button.prop('disabled', false).text('<?php esc_html_e( 'Check Now', 'wp-domain-mapping' ); ?>');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render import/export tab content
 */
function dm_render_import_export_content() {
    ?>
    <div class="card domain-mapping-card">
        <h2><?php _e( 'Export Domain Mappings', 'wp-domain-mapping' ); ?></h2>
        <p><?php _e( 'Export all domain mappings to a CSV file.', 'wp-domain-mapping' ); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field( 'domain_mapping_export', 'domain_mapping_export_nonce' ); ?>
            <input type="hidden" name="domain_mapping_export" value="1">

            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" name="include_header" value="1" checked>
                    <?php _e( 'Include column headers', 'wp-domain-mapping' ); ?>
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label for="blog_id_filter"><?php _e( 'Export for specific site ID (optional):', 'wp-domain-mapping' ); ?></label>
                <input type="number" id="blog_id_filter" name="blog_id_filter" min="1" class="regular-text">
                <p class="description"><?php _e( 'Leave empty to export all domains.', 'wp-domain-mapping' ); ?></p>
            </div>

            <p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Export to CSV', 'wp-domain-mapping' ); ?>">
            </p>
        </form>
    </div>

    <div class="card domain-mapping-card">
        <h2><?php _e( 'Import Domain Mappings', 'wp-domain-mapping' ); ?></h2>
        <p><?php _e( 'Import domain mappings from a CSV file.', 'wp-domain-mapping' ); ?></p>

        <form method="post" enctype="multipart/form-data" id="domain-mapping-import-form">
            <?php wp_nonce_field( 'domain_mapping_import', 'domain_mapping_import_nonce' ); ?>
            <input type="hidden" name="domain_mapping_import" value="1">

            <div style="margin-bottom: 15px;">
                <label for="csv_file"><?php _e( 'CSV File:', 'wp-domain-mapping' ); ?></label><br>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                <p class="description">
                    <?php _e( 'The CSV file should have the columns: blog_id, domain, active (1 or 0).', 'wp-domain-mapping' ); ?><br>
                    <?php _e( 'Example: 1,example.com,1', 'wp-domain-mapping' ); ?>
                </p>
            </div>

            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" name="has_header" value="1" checked>
                    <?php _e( 'First row contains column headers', 'wp-domain-mapping' ); ?>
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" name="update_existing" value="1" checked>
                    <?php _e( 'Update existing mappings', 'wp-domain-mapping' ); ?>
                </label>
                <p class="description"><?php _e( 'If unchecked, will skip domains that already exist.', 'wp-domain-mapping' ); ?></p>
            </div>

            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" name="validate_sites" value="1" checked>
                    <?php _e( 'Validate site IDs', 'wp-domain-mapping' ); ?>
                </label>
                <p class="description"><?php _e( 'If checked, will only import domains for existing sites.', 'wp-domain-mapping' ); ?></p>
            </div>

            <p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Import from CSV', 'wp-domain-mapping' ); ?>">
            </p>
        </form>

        <div id="import-progress" style="display: none; margin-top: 20px;">
            <p><?php _e( 'Processing import...', 'wp-domain-mapping' ); ?></p>
            <div class="progress-bar-outer" style="background-color: #f0f0f1; border-radius: 4px; height: 20px; width: 100%; overflow: hidden;">
                <div class="progress-bar-inner" style="background-color: #2271b1; height: 100%; width: 0%;"></div>
            </div>
            <p class="progress-text">0%</p>
        </div>

        <div id="import-results" style="display: none; margin-top: 20px;">
            <h3><?php _e( 'Import Results', 'wp-domain-mapping' ); ?></h3>
            <div class="import-summary"></div>
            <div class="import-details"></div>
        </div>
    </div>

    <div class="card domain-mapping-card">
        <h2><?php _e( 'CSV Format', 'wp-domain-mapping' ); ?></h2>
        <p><?php _e( 'The CSV file should follow this format:', 'wp-domain-mapping' ); ?></p>

        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>blog_id</th>
                    <th>domain</th>
                    <th>active</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>example.com</td>
                    <td>1</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>example.org</td>
                    <td>0</td>
                </tr>
            </tbody>
        </table>

        <ul style="margin-top: 15px;">
            <li><strong>blog_id</strong>: <?php _e( 'The ID of the WordPress site (required)', 'wp-domain-mapping' ); ?></li>
            <li><strong>domain</strong>: <?php _e( 'The domain name without http:// or https:// (required)', 'wp-domain-mapping' ); ?></li>
            <li><strong>active</strong>: <?php _e( 'Set to 1 to make this the primary domain, 0 otherwise (required)', 'wp-domain-mapping' ); ?></li>
        </ul>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#domain-mapping-import-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('action', 'dm_import_csv');

            // Show progress bar
            $('#import-progress').show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                contentType: false,
                processData: false,
                success: function(response) {
                    $('#import-progress').hide();
                    $('#import-results').show();

                    if (response.success) {
                        $('.import-summary').html(
                            '<div class="notice notice-success"><p>' +
                            response.data.message +
                            '</p></div>'
                        );

                        var details = '<table class="widefat">' +
                                      '<thead><tr>' +
                                      '<th>Status</th>' +
                                      '<th>Details</th>' +
                                      '</tr></thead><tbody>';

                        $.each(response.data.details, function(i, item) {
                            var statusClass = 'notice-success';
                            if (item.status === 'error') {
                                statusClass = 'notice-error';
                            } else if (item.status === 'warning') {
                                statusClass = 'notice-warning';
                            }

                            details += '<tr class="' + statusClass + '">' +
                                       '<td>' + item.status + '</td>' +
                                       '<td>' + item.message + '</td>' +
                                       '</tr>';
                        });

                        details += '</tbody></table>';
                        $('.import-details').html(details);
                    } else {
                        $('.import-summary').html(
                            '<div class="notice notice-error"><p>' +
                            response.data +
                            '</p></div>'
                        );
                    }
                },
                error: function() {
                    $('#import-progress').hide();
                    $('#import-results').show();
                    $('.import-summary').html(
                        '<div class="notice notice-error"><p>' +
                        '<?php _e( 'An error occurred during import.', 'wp-domain-mapping' ); ?>' +
                        '</p></div>'
                    );
                },
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = evt.loaded / evt.total * 100;
                            $('.progress-bar-inner').css('width', percentComplete + '%');
                            $('.progress-text').text(Math.round(percentComplete) + '%');
                        }
                    }, false);

                    return xhr;
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render domains admin page
 */
function dm_render_domains_page() {
    global $wpdb, $current_site;

    WP_Domain_Mapping_Core::get_instance()->create_tables();

    if ( isset($current_site->path) && $current_site->path != "/" ) {
        wp_die( sprintf(
            esc_html__( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in '%s'.", "wp-domain-mapping" ),
            esc_html( $current_site->path )
        ));
    }

    $tables = dm_get_table_names();
    $total_domains = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['domains']}" );
    $primary_domains = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['domains']} WHERE active = 1" );

    $edit_row = false;
    if ( isset( $_GET['edit_domain'] ) ) {
        $edit_domain = sanitize_text_field( $_GET['edit_domain'] );
        $edit_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['domains']} WHERE domain = %s",
            $edit_domain
        ));
    }

    // Include the admin page template
    require_once WP_DOMAIN_MAPPING_DIR_PATH . 'admin/domains-page.php';
}

/**
 * Render user domain mapping page
 */
function dm_render_user_page( $protocol = null, $domains = null ) {
    global $wpdb;

    if ( null === $protocol ) {
        $protocol = is_ssl() ? 'https://' : 'http://';
    }

    if ( null === $domains ) {
        $domains = dm_get_domains_by_blog_id( $wpdb->blogid );
    }

    // Include original user page content
    require_once WP_DOMAIN_MAPPING_DIR_PATH . 'admin/user-page.php';
}
