<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render admin settings page (continued)
 */
function dm_render_settings_page_continued($dm_301_redirect, $dm_redirect_admin, $dm_user_settings, $dm_no_primary_domain, $dm_ipaddress, $dm_cname, $core) {
    ?>
                                <legend class="screen-reader-text"><?php _e('Permanent Redirect', 'wp-domain-mapping'); ?></legend>
                                <label for="permanent_redirect">
                                    <input type="checkbox" name="permanent_redirect" id="permanent_redirect" value="1" <?php checked($dm_301_redirect, 1); ?> />
                                    <?php _e('Use Permanent Redirect (301)', 'wp-domain-mapping'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Use 301 redirects instead of 302 redirects. This is better for SEO but may cause caching issues.', 'wp-domain-mapping'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('User Settings', 'wp-domain-mapping'); ?></legend>
                                <label for="dm_user_settings">
                                    <input type="checkbox" name="dm_user_settings" id="dm_user_settings" value="1" <?php checked($dm_user_settings, 1); ?> />
                                    <?php _e('Enable User Domain Mapping Page', 'wp-domain-mapping'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Allow site administrators to manage their domain mappings from the Tools menu.', 'wp-domain-mapping'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Redirect Admin', 'wp-domain-mapping'); ?></legend>
                                <label for="always_redirect_admin">
                                    <input type="checkbox" name="always_redirect_admin" id="always_redirect_admin" value="1" <?php checked($dm_redirect_admin, 1); ?> />
                                    <?php _e('Redirect Admin Pages to Original Domain', 'wp-domain-mapping'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Force admin pages to use the original WordPress domain instead of the mapped domain.', 'wp-domain-mapping'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Disable Primary Domain', 'wp-domain-mapping'); ?></legend>
                                <label for="dm_no_primary_domain">
                                    <input type="checkbox" name="dm_no_primary_domain" id="dm_no_primary_domain" value="1" <?php checked($dm_no_primary_domain, 1); ?> />
                                    <?php _e('Disable Primary Domain Check', 'wp-domain-mapping'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Do not redirect to the primary domain, but allow access through any mapped domain.', 'wp-domain-mapping'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Configuration', 'wp-domain-mapping'); ?>" />
                    <a href="<?php echo esc_url(admin_url('network/sites.php?page=domains')); ?>" class="button button-secondary">
                        <?php _e('Manage Domains', 'wp-domain-mapping'); ?>
                    </a>
                </p>
            </form>
        </div>

        <div class="card domain-mapping-card">
            <h2><?php _e('DNS Setup Instructions', 'wp-domain-mapping'); ?></h2>

            <div class="dns-instructions">
                <?php if (!empty($dm_cname)) : ?>
                    <h3><?php _e('CNAME Method (Recommended)', 'wp-domain-mapping'); ?></h3>
                    <p>
                        <?php
                        printf(
                            __('Tell your users to add a DNS "CNAME" record for their domain pointing to: %s', 'wp-domain-mapping'),
                            '<code>' . esc_html($dm_cname) . '</code>'
                        );
                        ?>
                    </p>
                    <div class="dns-example">
                        <h4><?php _e('Example DNS Record', 'wp-domain-mapping'); ?></h4>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'wp-domain-mapping'); ?></th>
                                    <th><?php _e('Name', 'wp-domain-mapping'); ?></th>
                                    <th><?php _e('Value', 'wp-domain-mapping'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>CNAME</code></td>
                                    <td><code>@</code> <?php _e('(or empty)', 'wp-domain-mapping'); ?></td>
                                    <td><code><?php echo esc_html($dm_cname); ?></code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($dm_ipaddress)) : ?>
                    <h3><?php _e('A Record Method', 'wp-domain-mapping'); ?></h3>
                    <p>
                        <?php
                        printf(
                            __('Tell your users to add a DNS "A" record for their domain pointing to: %s', 'wp-domain-mapping'),
                            '<code>' . esc_html($dm_ipaddress) . '</code>'
                        );
                        ?>
                    </p>
                    <div class="dns-example">
                        <h4><?php _e('Example DNS Record', 'wp-domain-mapping'); ?></h4>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'wp-domain-mapping'); ?></th>
                                    <th><?php _e('Name', 'wp-domain-mapping'); ?></th>
                                    <th><?php _e('Value', 'wp-domain-mapping'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ips = array_map('trim', explode(',', $dm_ipaddress));
                                foreach ($ips as $index => $ip) :
                                ?>
                                <tr>
                                    <td><code>A</code></td>
                                    <td><code>@</code> <?php _e('(or empty)', 'wp-domain-mapping'); ?></td>
                                    <td><code><?php echo esc_html($ip); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (empty($dm_ipaddress) && empty($dm_cname)) : ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php _e('Please configure either a Server IP Address or CNAME to provide DNS setup instructions.', 'wp-domain-mapping'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <h3><?php _e('Additional DNS Tips', 'wp-domain-mapping'); ?></h3>
                <ul class="dns-tips">
                    <li>
                        <?php _e('Most DNS changes take 24-48 hours to fully propagate worldwide.', 'wp-domain-mapping'); ?>
                    </li>
                    <li>
                        <?php _e('For "www" subdomain, create a separate CNAME record with "www" as the name pointing to the same value.', 'wp-domain-mapping'); ?>
                    </li>
                    <li>
                        <?php _e('If you\'re using Cloudflare or similar services, you may need to adjust proxy settings.', 'wp-domain-mapping'); ?>
                    </li>
                    <li>
                        <?php _e('For SSL to work properly, make sure your web server is configured with the appropriate SSL certificates for mapped domains.', 'wp-domain-mapping'); ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card domain-mapping-card">
            <h2><?php _e('Installation Check', 'wp-domain-mapping'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php _e('Status', 'wp-domain-mapping'); ?></th>
                        <th><?php _e('Check', 'wp-domain-mapping'); ?></th>
                        <th><?php _e('Value', 'wp-domain-mapping'); ?></th>
                    </tr>
                    <tr>
                        <td>
                            <?php if (file_exists(WP_CONTENT_DIR . '/sunrise.php')) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('sunrise.php file', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if (file_exists(WP_CONTENT_DIR . '/sunrise.php')) : ?>
                                <?php _e('Found', 'wp-domain-mapping'); ?>
                            <?php else : ?>
                                <?php
                                printf(
                                    __('Not found - copy sunrise.php to %s', 'wp-domain-mapping'),
                                    '<code>' . esc_html(WP_CONTENT_DIR) . '</code>'
                                );
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if (defined('SUNRISE')) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('SUNRISE constant', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if (defined('SUNRISE')) : ?>
                                <?php
                                printf(
                                    __('Defined as: %s', 'wp-domain-mapping'),
                                    '<code>' . esc_html(SUNRISE) . '</code>'
                                );
                                ?>
                            <?php else : ?>
                                <?php _e('Not defined - add to wp-config.php: ', 'wp-domain-mapping'); ?>
                                <code>define( 'SUNRISE', 'on' );</code>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if (defined('SUNRISE_LOADED')) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('SUNRISE_LOADED', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if (defined('SUNRISE_LOADED')) : ?>
                                <?php _e('Loaded successfully', 'wp-domain-mapping'); ?>
                            <?php else : ?>
                                <?php
                                if (defined('SUNRISE')) {
                                    _e('Not loaded - make sure SUNRISE is defined before the require_once() in wp-config.php', 'wp-domain-mapping');
                                } else {
                                    _e('Not loaded - SUNRISE constant not defined', 'wp-domain-mapping');
                                }
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if (!defined('COOKIE_DOMAIN')) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('COOKIE_DOMAIN', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if (!defined('COOKIE_DOMAIN')) : ?>
                                <?php _e('Not defined (correct)', 'wp-domain-mapping'); ?>
                            <?php else : ?>
                                <?php
                                printf(
                                    __('Defined as: %s - remove this from wp-config.php', 'wp-domain-mapping'),
                                    '<code>' . esc_html(COOKIE_DOMAIN) . '</code>'
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
                            foreach (array(
                                $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS,
                                $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGINS,
                                $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGS,
                            ) as $table) {
                                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                                    $tables_exist = false;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($tables_exist) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('Database tables', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if ($tables_exist) : ?>
                                <?php _e('All tables exist', 'wp-domain-mapping'); ?>
                            <?php else : ?>
                                <?php _e('Some tables are missing - deactivate and reactivate the plugin', 'wp-domain-mapping'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if (is_multisite()) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php _e('Multisite', 'wp-domain-mapping'); ?></td>
                        <td>
                            <?php if (is_multisite()) : ?>
                                <?php _e('Enabled', 'wp-domain-mapping'); ?>
                            <?php else : ?>
                                <?php _e('Not enabled - this plugin requires WordPress Multisite', 'wp-domain-mapping'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Render the settings page by calling both parts
 */
function dm_render_settings_page() {
    // Get current options
    $dm_ipaddress = get_site_option('dm_ipaddress', '');
    $dm_cname = get_site_option('dm_cname', '');
    $dm_remote_login = get_site_option('dm_remote_login', 1);
    $dm_301_redirect = get_site_option('dm_301_redirect', 0);
    $dm_redirect_admin = get_site_option('dm_redirect_admin', 1);
    $dm_user_settings = get_site_option('dm_user_settings', 1);
    $dm_no_primary_domain = get_site_option('dm_no_primary_domain', 0);
    
    // Get core instance
    $core = WP_Domain_Mapping_Core::get_instance();
    
    // Call the first part of the function
    dm_render_settings_page_continued($dm_301_redirect, $dm_redirect_admin, $dm_user_settings, $dm_no_primary_domain, $dm_ipaddress, $dm_cname, $core);
}

/**
 * Render user domain mapping page
 */
function dm_render_user_page() {
    global $wpdb;
    
    $core = WP_Domain_Mapping_Core::get_instance();
    $db = WP_Domain_Mapping_DB::get_instance();
    
    $protocol = is_ssl() ? 'https://' : 'http://';
    $domains = $db->get_domains_by_blog_id($wpdb->blogid);
    
    // Display updated messages
    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>';
        switch ($_GET['updated']) {
            case 'add':
                _e('Domain added successfully.', 'wp-domain-mapping');
                break;
            case 'exists':
                _e('This domain is already mapped to a site.', 'wp-domain-mapping');
                break;
            case 'primary':
                _e('Primary domain updated.', 'wp-domain-mapping');
                break;
            case 'del':
                _e('Domain deleted successfully.', 'wp-domain-mapping');
                break;
        }
        echo '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Domain Mapping', 'wp-domain-mapping'); ?></h1>

        <div class="card domain-mapping-card">
            <h2><?php _e('Add New Domain', 'wp-domain-mapping'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('tools.php?page=domainmapping')); ?>">
                <?php wp_nonce_field('domain_mapping'); ?>
                <input type="hidden" name="action" value="add" />

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="domain"><?php _e('Domain', 'wp-domain-mapping'); ?></label></th>
                        <td>
                            <input name="domain" id="domain" type="text" value="" class="regular-text"
                                   placeholder="example.com" pattern="^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$" required />
                            <p class="description">
                                <?php _e('Enter the domain without http:// or https:// (e.g., example.com)', 'wp-domain-mapping'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Primary Domain', 'wp-domain-mapping'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Primary Domain', 'wp-domain-mapping'); ?></legend>
                                <label for="primary">
                                    <input name="primary" type="checkbox" id="primary" value="1" />
                                    <?php _e('Set this domain as the primary domain for this site', 'wp-domain-mapping'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Add Domain', 'wp-domain-mapping'); ?>" />
                </p>
            </form>
        </div>

        <?php if (!empty($domains)): ?>
        <div class="card domain-mapping-card">
            <h2><?php _e('Your Mapped Domains', 'wp-domain-mapping'); ?></h2>

            <table class="wp-list-table widefat fixed striped domains-table">
                <thead>
                    <tr>
                        <th><?php _e('Domain', 'wp-domain-mapping'); ?></th>
                        <th><?php _e('Primary', 'wp-domain-mapping'); ?></th>
                        <th><?php _e('Actions', 'wp-domain-mapping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($protocol . $domain->domain); ?>" target="_blank">
                                <?php echo esc_html($domain->domain); ?>
                                <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.3; opacity: 0.7;"></span>
                            </a>
                        </td>
                        <td>
                            <?php if ($domain->active == 1): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <span class="screen-reader-text"><?php _e('Yes', 'wp-domain-mapping'); ?></span>
                            <?php else: ?>
                                <a href="<?php echo wp_nonce_url(add_query_arg(
                                    array('page' => 'domainmapping', 'action' => 'primary', 'domain' => $domain->domain),
                                    admin_url('tools.php')
                                ), 'domain_mapping'); ?>" class="button button-small">
                                    <?php _e('Make Primary', 'wp-domain-mapping'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($domain->active != 1): ?>
                                <a href="<?php echo wp_nonce_url(add_query_arg(
                                    array('page' => 'domainmapping', 'action' => 'delete', 'domain' => $domain->domain),
                                    admin_url('tools.php')
                                ), 'delete' . $domain->domain); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this domain?', 'wp-domain-mapping'); ?>');">
                                    <?php _e('Delete', 'wp-domain-mapping'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="card domain-mapping-card">
            <h2><?php _e('DNS Configuration Instructions', 'wp-domain-mapping'); ?></h2>

            <?php
            $ipaddress = get_site_option('dm_ipaddress');
            $cname = get_site_option('dm_cname');

            if (!$ipaddress && !$cname): ?>
                <div class="notice notice-error">
                    <p><?php _e('The site administrator has not configured the DNS settings. Please contact them for assistance.', 'wp-domain-mapping'); ?></p>
                </div>
            <?php else: ?>
                <p><?php _e('To map your domain to this site, you need to update your DNS records with your domain registrar.', 'wp-domain-mapping'); ?></p>

                <?php if ($cname): ?>
                    <h3><?php _e('CNAME Method (Recommended)', 'wp-domain-mapping'); ?></h3>
                    <p><?php printf(__('Create a CNAME record for your domain pointing to: <code>%s</code>', 'wp-domain-mapping'), esc_html($cname)); ?></p>
                <?php endif; ?>

                <?php if ($ipaddress): ?>
                    <h3><?php _e('A Record Method', 'wp-domain-mapping'); ?></h3>
                    <p><?php printf(__('Create an A record for your domain pointing to: <code>%s</code>', 'wp-domain-mapping'), esc_html($ipaddress)); ?></p>
                <?php endif; ?>

                <p class="description"><?php _e('DNS changes may take 24-48 hours to fully propagate across the internet.', 'wp-domain-mapping'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render health page
 */
function dm_render_health_page() {
    // Check permissions
    if (!current_user_can('manage_network')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-domain-mapping'));
    }

    // Get all domains
    global $wpdb;
    $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;
    $domains = $wpdb->get_results("
        SELECT d.*, b.domain as original_domain, b.path
        FROM {$table} d
        JOIN {$wpdb->blogs} b ON d.blog_id = b.blog_id
        ORDER BY d.blog_id ASC, d.active DESC
    ");

    // Get health check results
    $health_results = get_site_option('dm_domain_health_results', array());

    // Display page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php
        // Display success messages
        if (isset($_GET['checked']) && $_GET['checked']) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __('Domain health check completed.', 'wp-domain-mapping') .
                 '</p></div>';
        }

        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __('Settings saved.', 'wp-domain-mapping') .
                 '</p></div>';
        }
        ?>

        <div class="card domain-mapping-card">
            <h2><?php _e('Domain Health Status', 'wp-domain-mapping'); ?></h2>

            <p>
                <form method="post" action="">
                    <?php wp_nonce_field('dm_manual_health_check', 'dm_manual_health_check_nonce'); ?>
                    <input type="hidden" name="dm_manual_health_check" value="1">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Check All Domains Now', 'wp-domain-mapping'); ?>">
                </form>
            </p>

            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        if (!empty($domains)) {
                            printf(
                                _n('%s domain', '%s domains', count($domains), 'wp-domain-mapping'),
                                number_format_i18n(count($domains))
                            );
                        } else {
                            _e('No domains found', 'wp-domain-mapping');
                        }
                        ?>
                    </span>
                </div>
                <br class="clear">
            </div>

            <table class="wp-list-table widefat fixed striped domains-health-table">
                <thead>
                    <tr>
                        <th class="column-domain"><?php _e('Domain', 'wp-domain-mapping'); ?></th>
                        <th class="column-site"><?php _e('Site', 'wp-domain-mapping'); ?></th>
                        <th class="column-dns"><?php _e('DNS Status', 'wp-domain-mapping'); ?></th>
                        <th class="column-ssl"><?php _e('SSL Status', 'wp-domain-mapping'); ?></th>
                        <th class="column-status"><?php _e('Reachable', 'wp-domain-mapping'); ?></th>
                        <th class="column-last-check"><?php _e('Last Check', 'wp-domain-mapping'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'wp-domain-mapping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($domains)) : ?>
                        <?php foreach ($domains as $domain) :
                            $domain_key = md5($domain->domain);
                            $health_data = isset($health_results[$domain_key]) ? $health_results[$domain_key] : null;
                            $site_name = get_blog_option($domain->blog_id, 'blogname', __('Unknown', 'wp-domain-mapping'));
                        ?>
                            <tr data-domain="<?php echo esc_attr($domain->domain); ?>" data-blog-id="<?php echo esc_attr($domain->blog_id); ?>">
                                <td class="column-domain">
                                    <?php echo esc_html($domain->domain); ?>
                                    <?php if ($domain->active) : ?>
                                        <span class="dashicons dashicons-star-filled" style="color: #f0b849;" title="<?php esc_attr_e('Primary Domain', 'wp-domain-mapping'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-site">
                                    <a href="<?php echo esc_url(network_admin_url('site-info.php?id=' . $domain->blog_id)); ?>">
                                        <?php echo esc_html($site_name); ?>
                                        <div class="row-actions">
                                            <span class="original-domain"><?php echo esc_html($domain->original_domain . $domain->path); ?></span>
                                        </div>
                                    </a>
                                </td>
                                <td class="column-dns">
                                    <?php if ($health_data && isset($health_data['dns_status'])) : ?>
                                        <?php if ($health_data['dns_status'] === 'success') : ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e('DNS correctly configured', 'wp-domain-mapping'); ?>"></span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php echo esc_attr($health_data['dns_message']); ?>"></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e('Not checked yet', 'wp-domain-mapping'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-ssl">
                                    <?php if ($health_data && isset($health_data['ssl_valid'])) : ?>
                                        <?php if ($health_data['ssl_valid']) : ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e('SSL certificate valid', 'wp-domain-mapping'); ?>"></span>
                                            <div class="row-actions">
                                                <span><?php echo esc_html(sprintf(__('Expires: %s', 'wp-domain-mapping'), isset($health_data['ssl_expiry']) ? date('Y-m-d', strtotime($health_data['ssl_expiry'])) : '-')); ?></span>
                                            </div>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php esc_attr_e('SSL certificate issue', 'wp-domain-mapping'); ?>"></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e('Not checked yet', 'wp-domain-mapping'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php if ($health_data && isset($health_data['accessible'])) : ?>
                                        <?php if ($health_data['accessible']) : ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e('Site is accessible', 'wp-domain-mapping'); ?>"></span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php esc_attr_e('Site is not accessible', 'wp-domain-mapping'); ?>"></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus" style="color: #999;" title="<?php esc_attr_e('Not checked yet', 'wp-domain-mapping'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-last-check">
                                    <?php
                                    if ($health_data && isset($health_data['last_check'])) {
                                        echo esc_html(human_time_diff(strtotime($health_data['last_check']), current_time('timestamp'))) . ' ' . __('ago', 'wp-domain-mapping');
                                    } else {
                                        _e('Never', 'wp-domain-mapping');
                                    }
                                    ?>
                                </td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small check-domain-health" data-domain="<?php echo esc_attr($domain->domain); ?>">
                                        <?php _e('Check Now', 'wp-domain-mapping'); ?>
                                    </button>

                                    <?php if ($health_data) : ?>
                                        <button type="button" class="button button-small show-health-details" data-domain="<?php echo esc_attr($domain->domain); ?>">
                                            <?php _e('Details', 'wp-domain-mapping'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($health_data) : ?>
                            <tr class="health-details-row" id="health-details-<?php echo esc_attr($domain_key); ?>" style="display: none;">
                                <td colspan="7">
                                    <div class="health-details-content">
                                        <h4><?php echo sprintf(__('Health Details for %s', 'wp-domain-mapping'), esc_html($domain->domain)); ?></h4>

                                        <table class="widefat striped" style="margin-top: 10px;">
                                            <tbody>
                                                <tr>
                                                    <th><?php _e('Last Check', 'wp-domain-mapping'); ?></th>
                                                    <td><?php echo isset($health_data['last_check']) ? esc_html(date('Y-m-d H:i:s', strtotime($health_data['last_check']))) : '-'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th><?php _e('DNS Status', 'wp-domain-mapping'); ?></th>
                                                    <td>
                                                        <?php if (isset($health_data['dns_status'])) : ?>
                                                            <?php if ($health_data['dns_status'] === 'success') : ?>
                                                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                                <?php _e('DNS correctly configured', 'wp-domain-mapping'); ?>
                                                            <?php else : ?>
                                                                <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                                                <?php echo esc_html($health_data['dns_message']); ?>
                                                            <?php endif; ?>
                                                        <?php else : ?>
                                                            <?php _e('Not checked', 'wp-domain-mapping'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th><?php _e('Resolved IP', 'wp-domain-mapping'); ?></th>
                                                    <td><?php echo isset($health_data['resolved_ip']) ? esc_html($health_data['resolved_ip']) : '-'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th><?php _e('SSL Valid', 'wp-domain-mapping'); ?></th>
                                                    <td>
                                                        <?php if (isset($health_data['ssl_valid'])) : ?>
                                                            <?php if ($health_data['ssl_valid']) : ?>
                                                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                                <?php _e('Valid', 'wp-domain-mapping'); ?>
                                                            <?php else : ?>
                                                                <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                                                <?php _e('Invalid', 'wp-domain-mapping'); ?>
                                                            <?php endif; ?>
                                                        <?php else : ?>
                                                            <?php _e('Not checked', 'wp-domain-mapping'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th><?php _e('SSL Expiry', 'wp-domain-mapping'); ?></th>
                                                    <td><?php echo isset($health_data['ssl_expiry']) ? esc_html(date('Y-m-d', strtotime($health_data['ssl_expiry']))) : '-'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th><?php _e('Response Code', 'wp-domain-mapping'); ?></th>
                                                    <td>
                                                        <?php
                                                        if (isset($health_data['response_code'])) {
                                                            echo esc_html($health_data['response_code']);
                                                            if ($health_data['response_code'] >= 200 && $health_data['response_code'] < 400) {
                                                                echo ' <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>';
                                                            } else {
                                                                echo ' <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>';
                                                            }
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php _e('No domains found.', 'wp-domain-mapping'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card domain-mapping-card">
            <h2><?php _e('Health Check Settings', 'wp-domain-mapping'); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field('dm_health_settings', 'dm_health_settings_nonce'); ?>
                <input type="hidden" name="dm_health_settings" value="1">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php _e('Automatic Health Checks', 'wp-domain-mapping'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Automatic Health Checks', 'wp-domain-mapping'); ?></span></legend>
                                <label for="health_check_enabled">
                                    <input name="health_check_enabled" type="checkbox" id="health_check_enabled" value="1" <?php checked(get_site_option('dm_health_check_enabled', true)); ?>>
                                    <?php _e('Enable automatic daily health checks', 'wp-domain-mapping'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email Notifications', 'wp-domain-mapping'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Email Notifications', 'wp-domain-mapping'); ?></span></legend>
                                <label for="health_notifications_enabled">
                                    <input name="health_notifications_enabled" type="checkbox" id="health_notifications_enabled" value="1" <?php checked(get_site_option('dm_health_notifications_enabled', true)); ?>>
                                    <?php _e('Send email notifications when domain health issues are detected', 'wp-domain-mapping'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notification_email"><?php _e('Notification Email', 'wp-domain-mapping'); ?></label></th>
                        <td>
                            <input name="notification_email" type="email" id="notification_email" class="regular-text" value="<?php echo esc_attr(get_site_option('dm_notification_email', get_option('admin_email'))); ?>">
                            <p class="description"><?php _e('Email address for domain health notifications.', 'wp-domain-mapping'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ssl_expiry_threshold"><?php _e('SSL Expiry Warning', 'wp-domain-mapping'); ?></label></th>
                        <td>
                            <input name="ssl_expiry_threshold" type="number" id="ssl_expiry_threshold" min="1" max="90" class="small-text" value="<?php echo esc_attr(get_site_option('dm_ssl_expiry_threshold', 14)); ?>">
                            <span><?php _e('days', 'wp-domain-mapping'); ?></span>
                            <p class="description"><?php _e('Send notifications when SSL certificates are expiring within this many days.', 'wp-domain-mapping'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'wp-domain-mapping'); ?>">
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Render import/export page
 */
function dm_render_import_export_page() {
    // Check permissions
    if (!current_user_can('manage_network')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-domain-mapping'));
    }

    // Output success messages
    if (isset($_GET['imported']) && $_GET['imported']) {
        $count = intval($_GET['imported']);
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

    if (isset($_GET['export']) && $_GET['export'] == 'success') {
        echo '<div class="notice notice-success is-dismissible"><p>' .
             __('Domain mappings exported successfully.', 'wp-domain-mapping') .
             '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="card domain-mapping-card">
            <h2><?php _e('Export Domain Mappings', 'wp-domain-mapping'); ?></h2>
            <p><?php _e('Export all domain mappings to a CSV file.', 'wp-domain-mapping'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('domain_mapping_export', 'domain_mapping_export_nonce'); ?>
                <input type="hidden" name="domain_mapping_export" value="1">

                <div style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="include_header" value="1" checked>
                        <?php _e('Include column headers', 'wp-domain-mapping'); ?>
                    </label>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="blog_id_filter"><?php _e('Export for specific site ID (optional):', 'wp-domain-mapping'); ?></label>
                    <input type="number" id="blog_id_filter" name="blog_id_filter" min="1" class="regular-text">
                    <p class="description"><?php _e('Leave empty to export all domains.', 'wp-domain-mapping'); ?></p>
                </div>

                <p>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Export to CSV', 'wp-domain-mapping'); ?>">
                </p>
            </form>
        </div>

        <div class="card domain-mapping-card">
            <h2><?php _e('Import Domain Mappings', 'wp-domain-mapping'); ?></h2>
            <p><?php _e('Import domain mappings from a CSV file.', 'wp-domain-mapping'); ?></p>

            <form method="post" enctype="multipart/form-data" id="domain-mapping-import-form">
                <?php wp_nonce_field('domain_mapping_import', 'domain_mapping_import_nonce'); ?>
                <input type="hidden" name="domain_mapping_import" value="1">

                <div style="margin-bottom: 15px;">
                    <label for="csv_file"><?php _e('CSV File:', 'wp-domain-mapping'); ?></label><br>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    <p class="description">
                        <?php _e('The CSV file should have the columns: blog_id, domain, active (1 or 0).', 'wp-domain-mapping'); ?><br>
                        <?php _e('Example: 1,example.com,1', 'wp-domain-mapping'); ?>
                    </p>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="has_header" value="1" checked>
                        <?php _e('First row contains column headers', 'wp-domain-mapping'); ?>
                    </label>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="update_existing" value="1" checked>
                        <?php _e('Update existing mappings', 'wp-domain-mapping'); ?>
                    </label>
                    <p class="description"><?php _e('If unchecked, will skip domains that already exist.', 'wp-domain-mapping'); ?></p>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="validate_sites" value="1" checked>
                        <?php _e('Validate site IDs', 'wp-domain-mapping'); ?>
                    </label>
                    <p class="description"><?php _e('If checked, will only import domains for existing sites.', 'wp-domain-mapping'); ?></p>
                </div>

                <p>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Import from CSV', 'wp-domain-mapping'); ?>">
                </p>
            </form>

            <div id="import-progress" style="display: none; margin-top: 20px;">
                <p><?php _e('Processing import...', 'wp-domain-mapping'); ?></p>
                <div class="progress-bar-outer" style="background-color: #f0f0f1; border-radius: 4px; height: 20px; width: 100%; overflow: hidden;">
                    <div class="progress-bar-inner" style="background-color: #2271b1; height: 100%; width: 0%;"></div>
                </div>
                <p class="progress-text">0%</p>
            </div>

            <div id="import-results" style="display: none; margin-top: 20px;">
                <h3><?php _e('Import Results', 'wp-domain-mapping'); ?></h3>
                <div class="import-summary"></div>
                <div class="import-details"></div>
            </div>
        </div>

        <div class="card domain-mapping-card">
            <h2><?php _e('CSV Format', 'wp-domain-mapping'); ?></h2>
            <p><?php _e('The CSV file should follow this format:', 'wp-domain-mapping'); ?></p>

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
                <li><strong>blog_id</strong>: <?php _e('The ID of the WordPress site (required)', 'wp-domain-mapping'); ?></li>
                <li><strong>domain</strong>: <?php _e('The domain name without http:// or https:// (required)', 'wp-domain-mapping'); ?></li>
                <li><strong>active</strong>: <?php _e('Set to 1 to make this the primary domain, 0 otherwise (required)', 'wp-domain-mapping'); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
