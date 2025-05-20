<?php
/**
 * Settings administration page
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

// Display settings errors
settings_errors( 'dm_settings' );
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php printf( esc_html__( 'Version: %s', 'wp-domain-mapping' ), esc_html( WP_DOMAIN_MAPPING_VERSION ) ); ?>
        </span>
        <a href="https://wpmultisite.com/document/wp-domain-mapping" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e( 'Documentation', 'wp-domain-mapping' ); ?>
        </a>
        <a href="https://wpmultisite.com/forums/" target="_blank" class="button button-secondary">
            <?php esc_html_e( 'Support', 'wp-domain-mapping' ); ?>
        </a>
    </h1>

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
                        <?php if ( ! empty( $server_ip ) && $server_ip != $dm_ipaddress ) : ?>
                            <p class="description">
                                <?php printf(
                                    /* translators: %s: Server IP address */
                                    esc_html__( 'Detected server IP: %s', 'wp-domain-mapping' ),
                                    '<code>' . esc_html( $server_ip ) . '</code>'
                                ); ?>
                            </p>
                        <?php endif; ?>
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
                <li>
                    <?php esc_html_e( 'Most DNS changes take 24-48 hours to fully propagate worldwide.', 'wp-domain-mapping' ); ?>
                </li>
                <li>
                    <?php esc_html_e( 'For "www" subdomain, create a separate CNAME record with "www" as the name pointing to the same value.', 'wp-domain-mapping' ); ?>
                </li>
                <li>
                    <?php esc_html_e( 'If you\'re using Cloudflare or similar services, you may need to adjust proxy settings.', 'wp-domain-mapping' ); ?>
                </li>
                <li>
                    <?php esc_html_e( 'For SSL to work properly, make sure your web server is configured with the appropriate SSL certificates for mapped domains.', 'wp-domain-mapping' ); ?>
                </li>
            </ul>
        </div>
    </div>

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
                        $tables_exist = true;
                        foreach ( array(
                            $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS,
                            $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGINS,
                            $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGS,
                        ) as $table ) {
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
</div>

<style>
/* Main cards */
.domain-mapping-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-width: unset;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

/* Form elements */
.form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.form-table td {
    padding: 15px 0;
}

/* Description text */
.description {
    color: #666;
    font-size: 13px;
    margin-top: 4px;
}

/* DNS instructions */
.dns-instructions {
    margin-top: 10px;
}

.dns-example {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
    padding: 15px;
    margin: 15px 0;
}

.dns-example h4 {
    margin-top: 0;
}

.dns-tips {
    list-style: disc;
    margin-left: 20px;
}

.dns-tips li {
    margin-bottom: 8px;
}

/* Status indicators */
.dashicons-yes-alt,
.dashicons-no-alt {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

/* Notices */
.notice {
    padding: 8px 12px;
    border-radius: 3px;
    margin: 5px 0 15px;
}

.notice p {
    margin: 0.5em 0;
    padding: 2px;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .form-table th {
        width: 100%;
        display: block;
    }

    .form-table td {
        display: block;
        padding: 0 0 15px;
    }
}
</style>
