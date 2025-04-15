<?php
/**
 * Plugin Name: WP Domain Mapping
 * Plugin URI: https://wenpai.org/plugins/wp-domain-mapping/
 * Description: Map any site on a WordPress website to another domain with enhanced management features.
 * Version: 1.3.4
 * Author: WPDomain.com
 * Author URI: https://wpdomain.com/
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-domain-mapping
 * Domain Path: /languages
 * Requires at least: 6.7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_DOMAIN_MAPPING_VERSION', '1.3.4');
define('WP_DOMAIN_MAPPING_DIR_URL', plugin_dir_url(__FILE__));
define('WP_DOMAIN_MAPPING_DIR_PATH', plugin_dir_path(__FILE__));
define('WP_DOMAIN_MAPPING_BASENAME', plugin_basename(__FILE__));


// Load text domain
function wp_domain_mapping_text_domain() {
    load_plugin_textdomain('wp-domain-mapping', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'wp_domain_mapping_text_domain');


// Integrate UpdatePulse Server for updates using PUC v5.3
require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5p3\PucFactory;

$WpDomainMappingUpdateChecker = PucFactory::buildUpdateChecker(
    'https://updates.weixiaoduo.com/wp-domain-mapping.json',
    __FILE__,
    'wp-domain-mapping'
);

// Warning: Network not configured
function domain_mapping_warning() {
    echo '<div class="notice notice-error"><p><strong>' . __('Domain Mapping Disabled.', 'wp-domain-mapping') . '</strong> ' . sprintf(__('You must <a href="%1$s">create a network</a> for it to work.', 'wp-domain-mapping'), 'http://codex.wordpress.org/Create_A_Network') . '</p></div>';
}

// Add admin pages
function dm_add_pages() {
    global $current_site, $wpdb, $wp_db_version;

    if (!isset($current_site) && $wp_db_version >= 15260) {
        add_action('admin_notices', 'domain_mapping_warning');
        return false;
    }
    if ($current_site->path != "/") {
        wp_die(__("The domain mapping plugin only works if the site is installed in /. This is a limitation of how virtual servers work and is very difficult to work around.", 'wp-domain-mapping'));
    }

    if (get_site_option('dm_user_settings') && $current_site->blog_id != $wpdb->blogid && !dm_sunrise_warning(false)) {
        add_management_page(__('Domain Mapping', 'wp-domain-mapping'), __('Domain Mapping', 'wp-domain-mapping'), 'manage_options', 'domainmapping', 'dm_manage_page');
    }
}
add_action('admin_menu', 'dm_add_pages');

// Add network admin pages
function dm_network_pages() {
    add_submenu_page('settings.php', __('Domain Mapping', 'wp-domain-mapping'), __('Domain Mapping', 'wp-domain-mapping'), 'manage_network', 'domain-mapping', 'dm_admin_page');
    add_submenu_page('sites.php', __('Domains', 'wp-domain-mapping'), __('Domains', 'wp-domain-mapping'), 'manage_network', 'domains', 'dm_domains_admin');
}
add_action('network_admin_menu', 'dm_network_pages');

// Default update messages
function dm_echo_default_updated_msg() {
    switch ($_GET['updated']) {
        case "add":
            $msg = __('New domain added.', 'wp-domain-mapping');
            break;
        case "exists":
            $msg = __('New domain already exists.', 'wp-domain-mapping');
            break;
        case "primary":
            $msg = __('New primary domain.', 'wp-domain-mapping');
            break;
        case "del":
            $msg = __('Domain deleted.', 'wp-domain-mapping');
            break;
    }
    echo "<div class='notice notice-success'><p>$msg</p></div>";
}
add_action('dm_echo_updated_msg', 'dm_echo_default_updated_msg');

// Create database tables
function maybe_create_db() {
    global $wpdb;

    // Initialize remote login hash
    get_dm_hash();

    // Set global table names
    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    $wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
    $wpdb->dmtablelogs = $wpdb->base_prefix . 'domain_mapping_logs';

    // Only network admins can create tables
    if (!dm_site_admin()) {
        return;
    }

    // Use static variable to prevent repeated creation
    static $tables_created = false;
    if ($tables_created) {
        return;
    }

    $created = 0;

    // Create domain_mapping table
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") != $wpdb->dmtable) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->dmtable}` (
            `id` bigint(20) NOT NULL auto_increment,
            `blog_id` bigint(20) NOT NULL,
            `domain` varchar(255) NOT NULL,
            `active` tinyint(4) default '1',
            PRIMARY KEY  (`id`),
            KEY `blog_id` (`blog_id`,`domain`,`active`)
        );");
        $created = 1;
    }

    // Create domain_mapping_logins table
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtablelogins}'") != $wpdb->dmtablelogins) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->dmtablelogins}` (
            `id` varchar(32) NOT NULL,
            `user_id` bigint(20) NOT NULL,
            `blog_id` bigint(20) NOT NULL,
            `t` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
            PRIMARY KEY  (`id`)
        );");
        $created = 1;
    }

    // Create domain_mapping_logs table
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtablelogs}'") != $wpdb->dmtablelogs) {
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->dmtablelogs}` (
            `id` bigint(20) NOT NULL auto_increment,
            `user_id` bigint(20) NOT NULL,
            `action` varchar(50) NOT NULL,
            `domain` varchar(255) NOT NULL,
            `blog_id` bigint(20) NOT NULL,
            `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        );");
        $created = 1;
    }

    // If any table was created, show success message and mark as created
    if ($created) {
        echo '<div class="notice notice-success"><p><strong>' . __('Domain mapping database table created.', 'wp-domain-mapping') . '</strong></p></div>';
        $tables_created = true;
    }
}

// Ajax handler for domain actions
function dm_ajax_handle_actions() {
    check_ajax_referer('domain_mapping', 'nonce');

    if (!current_user_can('manage_network')) {
        wp_send_json_error(__('Permission denied.', 'wp-domain-mapping'));
    }

    global $wpdb;
    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    $wpdb->dmtablelogs = $wpdb->base_prefix . 'domain_mapping_logs';
    $action = sanitize_text_field($_POST['action_type']);
    $domain = dm_clean_domain(sanitize_text_field(strtolower($_POST['domain'])));
    $blog_id = absint($_POST['blog_id']);
    $active = isset($_POST['active']) ? absint($_POST['active']) : 0;
    $orig_domain = dm_clean_domain(sanitize_text_field($_POST['orig_domain']));
    $current_user_id = get_current_user_id();

    switch ($action) {
        case 'save':
            if ($blog_id != 0 && $blog_id != 1 && null == $wpdb->get_var($wpdb->prepare("SELECT domain FROM {$wpdb->dmtable} WHERE blog_id != %d AND domain = %s", $blog_id, $domain))) {
                if (empty($orig_domain)) {
                    $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->dmtable} ( `blog_id`, `domain`, `active` ) VALUES ( %d, %s, %d )", $blog_id, $domain, $active));
                    $wpdb->insert($wpdb->dmtablelogs, array('user_id' => $current_user_id, 'action' => 'add', 'domain' => $domain, 'blog_id' => $blog_id));
                    wp_send_json_success(__('Domain added successfully.', 'wp-domain-mapping'));
                } else {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->dmtable} SET blog_id = %d, domain = %s, active = %d WHERE domain = %s", $blog_id, $domain, $active, $orig_domain));
                    $wpdb->insert($wpdb->dmtablelogs, array('user_id' => $current_user_id, 'action' => 'edit', 'domain' => $domain, 'blog_id' => $blog_id));
                    wp_send_json_success(__('Domain updated successfully.', 'wp-domain-mapping'));
                }
            } else {
                wp_send_json_error(__('Invalid site ID or domain already exists.', 'wp-domain-mapping'));
            }
            break;
        case 'delete':
            $domains = isset($_POST['domains']) ? array_map('sanitize_text_field', (array)$_POST['domains']) : array($domain);
            foreach ($domains as $del_domain) {
                $affected_blog_id = $wpdb->get_var($wpdb->prepare("SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = %s", $del_domain));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtable} WHERE domain = %s", $del_domain));
                $wpdb->insert($wpdb->dmtablelogs, array('user_id' => $current_user_id, 'action' => 'delete', 'domain' => $del_domain, 'blog_id' => $affected_blog_id));
            }
            wp_send_json_success(__('Selected domains deleted successfully.', 'wp-domain-mapping'));
            break;
        default:
            wp_send_json_error(__('Invalid action.', 'wp-domain-mapping'));
    }
}
add_action('wp_ajax_dm_handle_actions', 'dm_ajax_handle_actions');

// Domains admin page
function dm_domains_admin() {
    global $wpdb, $current_site;
    if (!dm_site_admin()) {
        return false;
    }

    dm_sunrise_warning();
    maybe_create_db();

    if ($current_site->path != "/") {
        wp_die(sprintf(__("<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in ’%s’.", "wp-domain-mapping"), $current_site->path));
    }

    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    $total_domains = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->dmtable}");
    $primary_domains = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->dmtable} WHERE active = 1");

    $edit_row = false;
    if (isset($_GET['edit_domain'])) {
        $edit_domain = sanitize_text_field($_GET['edit_domain']);
        $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->dmtable} WHERE domain = %s", $edit_domain));
    }

    ?>
    <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?>
                <span style="font-size: 13px; padding-left: 10px;"><?php printf(esc_html__('Version: %s', 'wp-domain-mapping'), esc_html(WP_DOMAIN_MAPPING_VERSION)); ?></span>
                <a href="https://wpmultisite.com/document/wp-domain-mapping" target="_blank" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('Document', 'wp-domain-mapping'); ?></a>
                <a href="https://wpmultisite.com/forums/" target="_blank" class="button button-secondary"><?php esc_html_e('Support', 'wp-domain-mapping'); ?></a>
            </h1>

        <div class="card">
            <h2><?php echo $edit_row ? __('Edit Domain', 'wp-domain-mapping') : __('Add New Domain', 'wp-domain-mapping'); ?></h2>
            <div id="edit-domain-status" class="notice" style="display:none;"></div>
            <?php dm_edit_domain($edit_row); ?>
        </div>

        <div class="card">
            <h2><?php _e('Search & Filter Domains', 'wp-domain-mapping'); ?></h2>
            <form method="GET" id="domain-filter-form">
                <input type="hidden" name="page" value="domains" />
                <p>
                    <label for="domain"><?php _e('Domain:', 'wp-domain-mapping'); ?></label>
                    <input type="text" id="domain" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" class="regular-text" />
                    <label for="blog_id"><?php _e('Site ID:', 'wp-domain-mapping'); ?></label>
                    <input type="number" id="blog_id" name="blog_id" value="<?php echo esc_attr(isset($_GET['blog_id']) ? $_GET['blog_id'] : ''); ?>" class="small-text" />
                    <label for="active"><?php _e('Primary:', 'wp-domain-mapping'); ?></label>
                    <select id="active" name="active">
                        <option value=""><?php _e('All', 'wp-domain-mapping'); ?></option>
                        <option value="1" <?php selected(isset($_GET['active']) && $_GET['active'] == '1'); ?>><?php _e('Yes', 'wp-domain-mapping'); ?></option>
                        <option value="0" <?php selected(isset($_GET['active']) && $_GET['active'] == '0'); ?>><?php _e('No', 'wp-domain-mapping'); ?></option>
                    </select>
                    <input type="submit" class="button button-secondary" value="<?php _e('Filter', 'wp-domain-mapping'); ?>" />
                </p>
            </form>
        </div>

        <div class="card">
            <div class="styles-sync-tabs">
                <button type="button" class="styles-tab active" data-tab="manage-domains"><?php _e('Manage Domains', 'wp-domain-mapping'); ?></button>
                <button type="button" class="styles-tab" data-tab="domain-logs"><?php _e('Domain Logs', 'wp-domain-mapping'); ?></button>
            </div>
            <div class="styles-sync-content">
                <div class="styles-section" data-section="manage-domains">
                    <div id="domain-status" class="notice" style="display:none;"></div>
                    <form id="domain-list-form" method="POST">
                        <?php
                        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 20;
                        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
                        $offset = ($paged - 1) * $per_page;

                        $where = array();
                        if (!empty($_GET['s'])) $where[] = $wpdb->prepare("domain LIKE %s", '%' . $wpdb->esc_like($_GET['s']) . '%');
                        if (!empty($_GET['blog_id'])) $where[] = $wpdb->prepare("blog_id = %d", $_GET['blog_id']);
                        if (isset($_GET['active']) && $_GET['active'] !== '') $where[] = $wpdb->prepare("active = %d", $_GET['active']);
                        $where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

                        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->dmtable}" . $where_sql);
                        $total_pages = ceil($total_items / $per_page);

                        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->dmtable}" . $where_sql . " ORDER BY id DESC LIMIT %d, %d", $offset, $per_page));
                        dm_domain_listing($rows);

                        echo '<div class="tablenav bottom">';
                        echo '<div class="tablenav-pages">';
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $paged,
                            'mid_size' => 2,
                            'end_size' => 1,
                        ));
                        echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items, 'wp-domain-mapping'), number_format_i18n($total_items)) . '</span>';
                        echo '</div>';
                        echo '</div>';
                        ?>
                        <p class="description"><?php printf(__('<strong>Note:</strong> %s', 'wp-domain-mapping'), dm_idn_warning()); ?></p>
                    </form>
                </div>
                <div class="styles-section" data-section="domain-logs" style="display:none;">
                    <?php dm_domain_logs(); ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><?php _e('Domain Statistics', 'wp-domain-mapping'); ?></h2>
            <table class="wp-list-table widefat fixed">
                <tbody>
                    <tr><th><?php _e('Total Domains', 'wp-domain-mapping'); ?></th><td><?php echo esc_html($total_domains); ?></td></tr>
                    <tr><th><?php _e('Primary Domains', 'wp-domain-mapping'); ?></th><td><?php echo esc_html($primary_domains); ?></td></tr>
                </tbody>
            </table>
        </div>

    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.styles-tab').on('click', function() {
            $('.styles-tab').removeClass('active');
            $(this).addClass('active');
            var tab = $(this).data('tab');
            $('.styles-section').hide();
            $('.styles-section[data-section="' + tab + '"]').show();
        });

        function showNotice(selector, message, type) {
            $(selector).removeClass('notice-success notice-error')
                .addClass('notice-' + type)
                .text(message)
                .show()
                .delay(3000)
                .fadeOut();
        }

        $('#edit-domain-form').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serializeArray();
            formData.push({name: 'action', value: 'dm_handle_actions'});
            formData.push({name: 'action_type', value: 'save'});
            formData.push({name: 'nonce', value: '<?php echo wp_create_nonce('domain_mapping'); ?>'});

            $('#edit-domain-status').text('<?php _e('Saving...', 'wp-domain-mapping'); ?>').show();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showNotice('#edit-domain-status', response.data, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showNotice('#edit-domain-status', response.data || '<?php _e('Failed to save domain.', 'wp-domain-mapping'); ?>', 'error');
                    }
                },
                error: function() {
                    showNotice('#edit-domain-status', '<?php _e('Server error occurred.', 'wp-domain-mapping'); ?>', 'error');
                }
            });
        });

        $('#domain-list-form').on('submit', function(e) {
            e.preventDefault();
            var selectedDomains = [];
            $('.domain-checkbox:checked').each(function() {
                selectedDomains.push($(this).val());
            });
            if (selectedDomains.length === 0) {
                showNotice('#domain-status', '<?php _e('Please select at least one domain.', 'wp-domain-mapping'); ?>', 'error');
                return;
            }

            var action = $('#bulk-action-selector-top').val();
            if (action === '-1') return;

            $('#domain-status').text('<?php _e('Processing...', 'wp-domain-mapping'); ?>').show();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dm_handle_actions',
                    action_type: 'delete',
                    domains: selectedDomains,
                    nonce: '<?php echo wp_create_nonce('domain_mapping'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('#domain-status', response.data, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showNotice('#domain-status', response.data || '<?php _e('Failed to delete domains.', 'wp-domain-mapping'); ?>', 'error');
                    }
                },
                error: function() {
                    showNotice('#domain-status', '<?php _e('Server error occurred.', 'wp-domain-mapping'); ?>', 'error');
                }
            });
        });
    });
    </script>

    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        max-width: unset;
        margin-top: 20px;
        padding: 20px;
    }
    .styles-sync-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        border-bottom: 1px solid #c3c4c7;
        margin-bottom: 20px;
    }
    .styles-tab {
        padding: 8px 16px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 14px;
        border-bottom: 2px solid transparent;
    }
    .styles-tab.active {
        border-bottom: 2px solid #007cba;
        font-weight: 600;
        background: #f0f0f1;
    }
    .styles-tab:hover:not(.active) {
        background: #f0f0f1;
        border-bottom-color: #dcdcde;
    }
    .styles-sync-content { flex: 1; }
    .tablenav { margin: 10px 0; }
    .tablenav-pages { float: right; }
    .tablenav-pages a, .tablenav-pages span { padding: 5px 10px; }
    .form-table th { width: 200px; }
    .form-table td { padding: 10px 0; }
    .description { color: #666; font-size: 12px; }
    .notice { padding: 8px 12px; border-radius: 3px; }
    .notice-success { background-color: #dff0d8; border-left: 4px solid #46b450; }
    .notice-error { background-color: #f2dede; border-left: 4px solid #dc3232; }
    </style>
    <?php
}

// Edit domain form
function dm_edit_domain($row = false) {
    $is_edit = is_object($row);
    if (!$row) {
        $row = new stdClass();
        $row->blog_id = '';
        $row->domain = '';
        $row->active = 1;
    }
    ?>
    <form id="edit-domain-form" method="POST">
        <input type="hidden" name="orig_domain" value="<?php echo esc_attr($is_edit ? $row->domain : ''); ?>" />
        <table class="form-table">
            <tr>
                <th><label for="blog_id"><?php _e('Site ID', 'wp-domain-mapping'); ?></label></th>
                <td><input type="number" id="blog_id" name="blog_id" value="<?php echo esc_attr($row->blog_id); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="domain"><?php _e('Domain', 'wp-domain-mapping'); ?></label></th>
                <td>
                    <input type="text" id="domain" name="domain" value="<?php echo esc_attr($row->domain); ?>" class="regular-text" required />
                    <p class="description"><?php _e('Enter the domain without http:// or https:// (e.g., example.com)', 'wp-domain-mapping'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="active"><?php _e('Primary', 'wp-domain-mapping'); ?></label></th>
                <td><input type="checkbox" id="active" name="active" value="1" <?php checked($row->active, 1); ?> /></td>
            </tr>
            <?php if (get_site_option('dm_no_primary_domain') == 1): ?>
            <tr>
                <td colspan="2"><?php _e('<strong>Warning!</strong> Primary domains are currently disabled.', 'wp-domain-mapping'); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <p><input type="submit" class="button button-primary" value="<?php echo $is_edit ? __('Update Domain', 'wp-domain-mapping') : __('Add Domain', 'wp-domain-mapping'); ?>" /></p>
    </form>
    <?php
}

// Domain listing display
function dm_domain_listing($rows) {
    global $wpdb;
    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    if (!$rows) {
        echo '<p>' . __('No domains found.', 'wp-domain-mapping') . '</p>';
        return;
    }

    $edit_url = network_admin_url(file_exists(ABSPATH . 'wp-admin/network/site-info.php') ? 'site-info.php' : (file_exists(ABSPATH . 'wp-admin/ms-sites.php') ? 'ms-sites.php' : 'wpmu-blogs.php'));
    ?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="bulk-action-selector-top" name="action">
                <option value="-1"><?php _e('Bulk Actions', 'wp-domain-mapping'); ?></option>
                <option value="delete"><?php _e('Delete', 'wp-domain-mapping'); ?></option>
            </select>
            <input type="submit" class="button action" value="<?php _e('Apply', 'wp-domain-mapping'); ?>" />
        </div>
    </div>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all" /></th>
                <th><?php _e('Site ID', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Domain', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Primary', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Edit', 'wp-domain-mapping'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><input type="checkbox" class="domain-checkbox" value="<?php echo esc_attr($row->domain); ?>" /></td>
                    <td><a href="<?php echo esc_url(add_query_arg(array('action' => 'editblog', 'id' => $row->blog_id), $edit_url)); ?>"><?php echo esc_html($row->blog_id); ?></a></td>
                    <td><a href="<?php echo esc_url(dm_ensure_protocol($row->domain)); ?>"><?php echo esc_html($row->domain); ?></a></td>
                    <td>
                        <?php if ($row->active == 1): ?>
                            <span class="dashicons dashicons-yes" style="color: green;"></span> <?php _e('Yes', 'wp-domain-mapping'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color: red;"></span> <?php _e('No', 'wp-domain-mapping'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('edit_domain' => $row->domain), admin_url('network/sites.php?page=domains'))); ?>" class="button button-secondary"><?php _e('Edit', 'wp-domain-mapping'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
    jQuery(document).ready(function($) {
        $('#select-all').on('change', function() {
            $('.domain-checkbox').prop('checked', this.checked);
        });
    });
    </script>
    <?php
    if (get_site_option('dm_no_primary_domain') == 1) {
        echo "<p>" . __('<strong>Warning!</strong> Primary domains are currently disabled.', 'wp-domain-mapping') . "</p>";
    }
}

function dm_ensure_protocol($domain) {
    if (preg_match('#^https?://#', $domain)) {
        return $domain;
    }
    return 'http://' . $domain;
}

function dm_clean_domain($domain) {
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = rtrim($domain, '/');
    return $domain;
}

// Domain logs display
function dm_domain_logs() {
    global $wpdb;
    $wpdb->dmtablelogs = $wpdb->base_prefix . 'domain_mapping_logs';
    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->dmtablelogs} ORDER BY timestamp DESC LIMIT 20");
    if (!$logs) {
        echo '<p>' . __('No logs available.', 'wp-domain-mapping') . '</p>';
        return;
    }
    ?>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php _e('User', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Action', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Domain', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Site ID', 'wp-domain-mapping'); ?></th>
                <th><?php _e('Timestamp', 'wp-domain-mapping'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html(get_userdata($log->user_id)->user_login); ?></td>
                    <td><?php echo esc_html($log->action); ?></td>
                    <td><?php echo esc_html($log->domain); ?></td>
                    <td><?php echo esc_html($log->blog_id); ?></td>
                    <td><?php echo esc_html($log->timestamp); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Add "Domains" link to sites list
function dm_add_domain_link_to_sites($actions, $blog_id) {
    $domains_url = add_query_arg(array('page' => 'domains', 'blog_id' => $blog_id), admin_url('network/sites.php'));
    $actions['domains'] = '<a href="' . esc_url($domains_url) . '">' . __('Domains', 'wp-domain-mapping') . '</a>';
    return $actions;
}
add_filter('manage_sites_action_links', 'dm_add_domain_link_to_sites', 10, 2);

// Configuration page
function dm_admin_page() {
    global $wpdb, $current_site;
    if (!dm_site_admin()) {
        return false;
    }

    dm_sunrise_warning();
    maybe_create_db();

    if ($current_site->path != "/") {
        wp_die(sprintf(__("<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in ’%s’.", "wp-domain-mapping"), $current_site->path));
    }

    if (get_site_option('dm_remote_login', 'NA') == 'NA') add_site_option('dm_remote_login', 1);
    if (get_site_option('dm_redirect_admin', 'NA') == 'NA') add_site_option('dm_redirect_admin', 1);
    if (get_site_option('dm_user_settings', 'NA') == 'NA') add_site_option('dm_user_settings', 1);

    if (!empty($_POST['action']) && $_POST['action'] == 'update') {
        check_admin_referer('domain_mapping');
        $ipok = true;
        $ipaddresses = explode(',', sanitize_text_field($_POST['ipaddress']));
        foreach ($ipaddresses as $address) {
            if (($ip = trim($address)) && !preg_match('|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$|', $ip)) {
                $ipok = false;
                break;
            }
        }
        if ($ipok) update_site_option('dm_ipaddress', $_POST['ipaddress']);
        if (intval($_POST['always_redirect_admin']) == 0) $_POST['dm_remote_login'] = 0;
        update_site_option('dm_remote_login', intval($_POST['dm_remote_login']));
        if (!preg_match('/(--|\.\.)/', $_POST['cname']) && preg_match('|^([a-zA-Z0-9-\.])+$|', $_POST['cname']))
            update_site_option('dm_cname', sanitize_text_field($_POST['cname']));
        else
            update_site_option('dm_cname', '');
        update_site_option('dm_301_redirect', isset($_POST['permanent_redirect']) ? intval($_POST['permanent_redirect']) : 0);
        update_site_option('dm_redirect_admin', isset($_POST['always_redirect_admin']) ? intval($_POST['always_redirect_admin']) : 0);
        update_site_option('dm_user_settings', isset($_POST['dm_user_settings']) ? intval($_POST['dm_user_settings']) : 0);
        update_site_option('dm_no_primary_domain', isset($_POST['dm_no_primary_domain']) ? intval($_POST['dm_no_primary_domain']) : 0);
    }

    ?>
    <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?>
                <span style="font-size: 13px; padding-left: 10px;"><?php printf(esc_html__('Version: %s', 'wp-domain-mapping'), esc_html(WP_DOMAIN_MAPPING_VERSION)); ?></span>
                <a href="https://wpmultisite.com/document/wp-domain-mapping" target="_blank" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('Document', 'wp-domain-mapping'); ?></a>
                <a href="https://wpmultisite.com/forums/" target="_blank" class="button button-secondary"><?php esc_html_e('Support', 'wp-domain-mapping'); ?></a>
            </h1>
            <div class="card">
            <h2><?php _e('Server Configuration', 'wp-domain-mapping'); ?></h2>
            <p><?php _e('Configure the IP address or CNAME for domain mapping.', 'wp-domain-mapping'); ?></p>
            <form method="POST">
                <input type="hidden" name="action" value="update" />
                <?php wp_nonce_field('domain_mapping'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ipaddress"><?php _e('Server IP Address:', 'wp-domain-mapping'); ?></label></th>
                        <td>
                            <input type="text" id="ipaddress" name="ipaddress" value="<?php echo esc_attr(get_site_option('dm_ipaddress')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter the IP address(es) users should point their DNS A records to. Use commas to separate multiple IPs.', 'wp-domain-mapping'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cname"><?php _e('Server CNAME Domain:', 'wp-domain-mapping'); ?></label></th>
                        <td>
                            <input type="text" id="cname" name="cname" value="<?php echo esc_attr(get_site_option('dm_cname')); ?>" class="regular-text" />
                            <p class="description"><?php printf(__('Use a CNAME instead of an IP (overrides IP settings). %s', 'wp-domain-mapping'), dm_idn_warning()); ?></p>
                        </td>
                    </tr>
                </table>
                <h3><?php _e('Domain Options', 'wp-domain-mapping'); ?></h3>
                <table class="form-table">
                    <tr><td><input type="checkbox" name="dm_remote_login" value="1" <?php checked(get_site_option('dm_remote_login'), 1); ?> /></td><td><?php _e('Enable Remote Login', 'wp-domain-mapping'); ?></td></tr>
                    <tr><td><input type="checkbox" name="permanent_redirect" value="1" <?php checked(get_site_option('dm_301_redirect'), 1); ?> /></td><td><?php _e('Use Permanent Redirect (301) - Better for SEO', 'wp-domain-mapping'); ?></td></tr>
                    <tr><td><input type="checkbox" name="dm_user_settings" value="1" <?php checked(get_site_option('dm_user_settings'), 1); ?> /></td><td><?php _e('Enable User Domain Mapping Page', 'wp-domain-mapping'); ?></td></tr>
                    <tr><td><input type="checkbox" name="always_redirect_admin" value="1" <?php checked(get_site_option('dm_redirect_admin'), 1); ?> /></td><td><?php _e('Redirect Admin Pages to Original Domain', 'wp-domain-mapping'); ?></td></tr>
                    <tr><td><input type="checkbox" name="dm_no_primary_domain" value="1" <?php checked(get_site_option('dm_no_primary_domain'), 1); ?> /></td><td><?php _e('Disable Primary Domain Check', 'wp-domain-mapping'); ?></td></tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="<?php _e('Save Configuration', 'wp-domain-mapping'); ?>" /></p>
            </form>
        </div>
    </div>
    <style>
    .card { background: #fff; border: 1px solid #ccd0d4; max-width: unset; border-radius: 4px; margin-top: 20px; padding: 20px; }
    .form-table th { width: 200px; }
    .form-table td { padding: 10px 0; }
    .form-table input[type="checkbox"] { margin-right: 10px; }
    .description { color: #666; font-size: 12px; }
    </style>
    <?php
}

// User management page
function dm_manage_page() {
    global $wpdb, $parent_file;

    if (isset($_GET['updated'])) {
        do_action('dm_echo_updated_msg');
    }

    dm_sunrise_warning();

    if (!get_site_option('dm_ipaddress') && !get_site_option('dm_cname')) {
        if (dm_site_admin()) {
            _e("Please set the IP address or CNAME of your server in the <a href='wpmu-admin.php?page=domain-mapping'>site admin page</a>.", 'wp-domain-mapping');
        } else {
            _e("This plugin has not been configured correctly yet.", 'wp-domain-mapping');
        }
        return false;
    }

    $protocol = is_ssl() ? 'https://' : 'http://';
    $domains = $wpdb->get_results("SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'", ARRAY_A);
    ?>
    <div class="wrap">
        <h1><?php _e('Domain Mapping', 'wp-domain-mapping'); ?></h1>
        <div class="card">
            <h2><?php _e('Active Domains', 'wp-domain-mapping'); ?></h2>
            <?php if (is_array($domains) && !empty($domains)): ?>
                <?php $orig_url = parse_url(get_original_url('siteurl')); $domains[] = array('domain' => $orig_url['host'], 'path' => $orig_url['path'], 'active' => 0); ?>
                <form method="POST">
                    <table class="wp-list-table widefat striped">
                        <thead><tr><th><?php _e('Primary', 'wp-domain-mapping'); ?></th><th><?php _e('Domain', 'wp-domain-mapping'); ?></th><th><?php _e('Delete', 'wp-domain-mapping'); ?></th></tr></thead>
                        <tbody>
                            <?php
                            $primary_found = 0;
                            $del_url = add_query_arg(array('page' => 'domainmapping', 'action' => 'delete'), admin_url($parent_file));
                            foreach ($domains as $details):
                                if (0 == $primary_found && $details['domain'] == $orig_url['host']) $details['active'] = 1;
                                ?>
                                <tr>
                                    <td><input type="radio" name="domain" value="<?php echo esc_attr($details['domain']); ?>" <?php checked($details['active'], 1); ?> /></td>
                                    <td><?php $url = "{$protocol}{$details['domain']}{$details['path']}"; ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($url); ?></a></td>
                                    <td><?php if ($details['domain'] != $orig_url['host'] && $details['active'] != 1): ?><a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('domain' => $details['domain']), $del_url), 'delete' . $details['domain'])); ?>"><?php _e('Delete', 'wp-domain-mapping'); ?></a><?php endif; ?></td>
                                </tr>
                                <?php if (0 == $primary_found) $primary_found = $details['active']; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="action" value="primary" />
                    <?php wp_nonce_field('domain_mapping'); ?>
                    <p class="submit"><input type="submit" class="button button-primary" value="<?php _e('Set Primary Domain', 'wp-domain-mapping'); ?>" /></p>
                </form>
                <p class="description"><?php _e('* The primary domain cannot be deleted.', 'wp-domain-mapping'); ?></p>
                <?php if (get_site_option('dm_no_primary_domain') == 1): ?><p><?php _e('<strong>Warning!</strong> Primary domains are currently disabled.', 'wp-domain-mapping'); ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2><?php _e('Add New Domain', 'wp-domain-mapping'); ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="add" />
                <?php wp_nonce_field('domain_mapping'); ?>
                <p><label for="new_domain"><?php _e('Domain:', 'wp-domain-mapping'); ?></label>
                <input type="text" id="new_domain" name="domain" value="" class="regular-text" placeholder="example.com" />
                <input type="checkbox" name="primary" value="1" /> <?php _e('Set as Primary Domain', 'wp-domain-mapping'); ?></p>
                <p class="submit"><input type="submit" class="button button-secondary" value="<?php _e('Add', 'wp-domain-mapping'); ?>" /></p>
            </form>
            <?php
            if (get_site_option('dm_cname')) {
                echo "<p>" . sprintf(__('Add a DNS "CNAME" record pointing to: <strong>%s</strong>', 'wp-domain-mapping'), esc_html(get_site_option('dm_cname'))) . "</p>";
            } else {
                $dm_ipaddress = get_site_option('dm_ipaddress', 'IP not set.');
                echo "<p>" . sprintf(__('Add a DNS "A" record pointing to: <strong>%s</strong>', 'wp-domain-mapping'), esc_html($dm_ipaddress)) . "</p>";
            }
            ?>
            <p class="description"><?php printf(__('<strong>Note:</strong> %s', 'wp-domain-mapping'), dm_idn_warning()); ?></p>
        </div>
    </div>
    <style>
    .wrap { max-width: unset; margin: 0 auto; }
    .card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 20px; padding: 20px; }
    .description { color: #666; font-size: 12px; }
    </style>
    <?php
}

// Handle user actions
function dm_handle_actions() {
    global $wpdb, $parent_file;
    $url = add_query_arg(array('page' => 'domainmapping'), admin_url($parent_file));
    if (!empty($_POST['action'])) {
        $domain = sanitize_text_field($_POST['domain']);
        if (empty($domain)) wp_die(__("You must enter a domain", 'wp-domain-mapping'));
        check_admin_referer('domain_mapping');
        do_action('dm_handle_actions_init', $domain);
        switch ($_POST['action']) {
            case "add":
                do_action('dm_handle_actions_add', $domain);
                if (null == $wpdb->get_row("SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain'") && null == $wpdb->get_row("SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '$domain'")) {
                    if ($_POST['primary']) {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid));
                    }
                    $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->dmtable} ( `id`, `blog_id`, `domain`, `active` ) VALUES ( NULL, %d, %s, %d )", $wpdb->blogid, $domain, $_POST['primary']));
                    wp_redirect(add_query_arg(array('updated' => 'add'), $url));
                    exit;
                } else {
                    wp_redirect(add_query_arg(array('updated' => 'exists'), $url));
                    exit;
                }
            case "primary":
                do_action('dm_handle_actions_primary', $domain);
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid));
                $orig_url = parse_url(get_original_url('siteurl'));
                if ($domain != $orig_url['host']) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->dmtable} SET active = 1 WHERE domain = %s", $domain));
                }
                wp_redirect(add_query_arg(array('updated' => 'primary'), $url));
                exit;
        }
    } elseif ($_GET['action'] == 'delete') {
        $domain = sanitize_text_field($_GET['domain']);
        if (empty($domain)) wp_die(__("You must enter a domain", 'wp-domain-mapping'));
        check_admin_referer("delete" . $_GET['domain']);
        do_action('dm_handle_actions_del', $domain);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtable} WHERE domain = %s", $domain));
        wp_redirect(add_query_arg(array('updated' => 'del'), $url));
        exit;
    }
}
if (isset($_GET['page']) && $_GET['page'] == 'domainmapping') {
    add_action('admin_init', 'dm_handle_actions');
}

// Sunrise check
function dm_sunrise_warning($die = true) {
    if (!file_exists(WP_CONTENT_DIR . '/sunrise.php')) {
        if (!$die) return true;
        if (dm_site_admin()) {
            wp_die(sprintf(__("Please copy sunrise.php to %s/sunrise.php and ensure the SUNRISE definition is in %swp-config.php", 'wp-domain-mapping'), WP_CONTENT_DIR, ABSPATH));
        } else {
            wp_die(__("This plugin has not been configured correctly yet.", 'wp-domain-mapping'));
        }
    } elseif (!defined('SUNRISE')) {
        if (!$die) return true;
        if (dm_site_admin()) {
            wp_die(sprintf(__("Please uncomment the line <em>define( 'SUNRISE', 'on' );</em> or add it to your %swp-config.php", 'wp-domain-mapping'), ABSPATH));
        } else {
            wp_die(__("This plugin has not been configured correctly yet.", 'wp-domain-mapping'));
        }
    } elseif (!defined('SUNRISE_LOADED')) {
        if (!$die) return true;
        if (dm_site_admin()) {
            wp_die(sprintf(__("Please edit your %swp-config.php and move the line <em>define( 'SUNRISE', 'on' );</em> above the last require_once() in that file or make sure you updated sunrise.php.", 'wp-domain-mapping'), ABSPATH));
        } else {
            wp_die(__("This plugin has not been configured correctly yet.", 'wp-domain-mapping'));
        }
    }
    return false;
}

// Core domain mapping functions
function domain_mapping_siteurl($setting) {
    global $wpdb, $current_blog;
    static $return_url = array();

    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

    if (!isset($return_url[$wpdb->blogid])) {
        $s = $wpdb->suppress_errors();
        if (get_site_option('dm_no_primary_domain') == 1) {
            $domain = $wpdb->get_var($wpdb->prepare("SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = %d AND domain = %s LIMIT 1", $wpdb->blogid, $_SERVER['HTTP_HOST']));
            if (null == $domain) {
                $return_url[$wpdb->blogid] = untrailingslashit(get_original_url("siteurl"));
                return $return_url[$wpdb->blogid];
            }
        } else {
            $domain = $wpdb->get_var($wpdb->prepare("SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = %d AND active = 1 LIMIT 1", $wpdb->blogid));
            if (null == $domain) {
                $return_url[$wpdb->blogid] = untrailingslashit(get_original_url("siteurl"));
                return $return_url[$wpdb->blogid];
            }
        }
        $wpdb->suppress_errors($s);
        $protocol = is_ssl() ? 'https://' : 'http://';
        if ($domain) {
            $return_url[$wpdb->blogid] = untrailingslashit($protocol . $domain);
            $setting = $return_url[$wpdb->blogid];
        } else {
            $return_url[$wpdb->blogid] = false;
        }
    } elseif ($return_url[$wpdb->blogid] !== false) {
        $setting = $return_url[$wpdb->blogid];
    }
    return $setting;
}

function get_original_url($url, $blog_id = 0) {
    global $wpdb;
    $id = $blog_id ?: $wpdb->blogid;
    static $orig_urls = array();

    if (!isset($orig_urls[$id])) {
        if (defined('DOMAIN_MAPPING')) remove_filter('pre_option_' . $url, 'domain_mapping_' . $url);
        $orig_url = $blog_id == 0 ? get_option($url) : get_blog_option($blog_id, $url);
        $orig_url = is_ssl() ? str_replace("http://", "https://", $orig_url) : str_replace("https://", "http://", $orig_url);
        $orig_urls[$id] = $orig_url;
        if (defined('DOMAIN_MAPPING')) add_filter('pre_option_' . $url, 'domain_mapping_' . $url);
    }
    return $orig_urls[$id];
}

function domain_mapping_adminurl($url, $path, $blog_id = 0) {
    $index = strpos($url, '/wp-admin');
    if ($index !== false) {
        $url = get_original_url('siteurl', $blog_id) . substr($url, $index);
        if ((is_ssl() || force_ssl_admin()) && 0 === strpos($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }
    }
    return $url;
}

function domain_mapping_post_content($post_content) {
    $orig_url = get_original_url('siteurl');
    $url = domain_mapping_siteurl('NA');
    if ($url == 'NA') return $post_content;
    return str_replace($orig_url, $url, $post_content);
}

function dm_redirect_admin() {
    if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/admin-ajax.php') !== false) return;
    if (get_site_option('dm_redirect_admin')) {
        $url = get_original_url('siteurl');
        if (false === strpos($url, $_SERVER['HTTP_HOST'])) {
            wp_redirect(untrailingslashit($url) . $_SERVER['REQUEST_URI']);
            exit;
        }
    } else {
        global $current_blog;
        $url = domain_mapping_siteurl(false);
        $request_uri = str_replace($current_blog->path, '/', $_SERVER['REQUEST_URI']);
        if (false === strpos($url, $_SERVER['HTTP_HOST'])) {
            wp_redirect(str_replace('//wp-admin', '/wp-admin', trailingslashit($url) . $request_uri));
            exit;
        }
    }
}

function redirect_login_to_orig() {
    if (!get_site_option('dm_remote_login') || $_GET['action'] == 'logout' || isset($_GET['loggedout'])) return;
    $url = get_original_url('siteurl');
    if ($url != site_url()) {
        $url .= "/wp-login.php";
        echo "<script type='text/javascript'>\nwindow.location = '$url'</script>";
    }
}

function domain_mapping_plugins_uri($full_url, $path = null, $plugin = null) {
    return get_option('siteurl') . substr($full_url, stripos($full_url, PLUGINDIR) - 1);
}

function domain_mapping_themes_uri($full_url) {
    return str_replace(get_original_url('siteurl'), get_option('siteurl'), $full_url);
}

if (defined('DOMAIN_MAPPING')) {
    add_filter('plugins_url', 'domain_mapping_plugins_uri', 1);
    add_filter('theme_root_uri', 'domain_mapping_themes_uri', 1);
    add_filter('pre_option_siteurl', 'domain_mapping_siteurl');
    add_filter('pre_option_home', 'domain_mapping_siteurl');
    add_filter('the_content', 'domain_mapping_post_content');
    add_action('wp_head', 'remote_login_js_loader');
    add_action('login_head', 'redirect_login_to_orig');
    add_action('wp_logout', 'remote_logout_loader', 9999);

    add_filter('stylesheet_uri', 'domain_mapping_post_content');
    add_filter('stylesheet_directory', 'domain_mapping_post_content');
    add_filter('stylesheet_directory_uri', 'domain_mapping_post_content');
    add_filter('template_directory', 'domain_mapping_post_content');
    add_filter('template_directory_uri', 'domain_mapping_post_content');
    add_filter('plugins_url', 'domain_mapping_post_content');
} else {
    add_filter('admin_url', 'domain_mapping_adminurl', 10, 3);
}
add_action('admin_init', 'dm_redirect_admin');
if (isset($_GET['dm'])) add_action('template_redirect', 'remote_login_js');

function remote_logout_loader() {
    global $current_site, $current_blog, $wpdb;
    $wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
    $protocol = is_ssl() ? 'https://' : 'http://';
    $hash = get_dm_hash();
    $key = md5(time());
    $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->dmtablelogins} ( `id`, `user_id`, `blog_id`, `t` ) VALUES( %s, 0, %d, NOW() )", $key, $current_blog->blog_id));
    if (get_site_option('dm_redirect_admin')) {
        wp_redirect($protocol . $current_site->domain . $current_site->path . "?dm={$hash}&action=logout&blogid={$current_blog->blog_id}&k={$key}&t=" . mt_rand());
        exit;
    }
}

function redirect_to_mapped_domain() {
    global $current_blog, $wpdb;
    if (is_main_site() || (isset($_GET['preview']) && $_GET['preview'] == 'true') || (isset($_POST['customize']) && isset($_POST['theme']) && $_POST['customize'] == 'on')) return;

    $protocol = is_ssl() ? 'https://' : 'http://';
    $url = domain_mapping_siteurl(false);
    if ($url && $url != untrailingslashit($protocol . $current_blog->domain . $current_blog->path)) {
        $redirect = get_site_option('dm_301_redirect') ? '301' : '302';
        if ((defined('VHOST') && constant("VHOST") != 'yes') || (defined('SUBDOMAIN_INSTALL') && constant('SUBDOMAIN_INSTALL') == false)) {
            $_SERVER['REQUEST_URI'] = str_replace($current_blog->path, '/', $_SERVER['REQUEST_URI']);
        }
        header("Location: {$url}{$_SERVER['REQUEST_URI']}", true, $redirect);
        exit;
    }
}
add_action('template_redirect', 'redirect_to_mapped_domain');

function get_dm_hash() {
    $remote_login_hash = get_site_option('dm_hash');
    if (null == $remote_login_hash) {
        $remote_login_hash = md5(time());
        update_site_option('dm_hash', $remote_login_hash);
    }
    return $remote_login_hash;
}

function remote_login_js() {
    global $current_blog, $current_user, $wpdb;
    if (0 == get_site_option('dm_remote_login')) return;

    $wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
    $hash = get_dm_hash();
    $protocol = is_ssl() ? 'https://' : 'http://';
    if ($_GET['dm'] == $hash) {
        if ($_GET['action'] == 'load') {
            if (!is_user_logged_in()) exit;
            $key = md5(time() . mt_rand());
            $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->dmtablelogins} ( `id`, `user_id`, `blog_id`, `t` ) VALUES( %s, %d, %d, NOW() )", $key, $current_user->ID, $_GET['blogid']));
            $url = add_query_arg(array('action' => 'login', 'dm' => $hash, 'k' => $key, 't' => mt_rand()), $_GET['back']);
            echo "window.location = '$url'";
            exit;
        } elseif ($_GET['action'] == 'login') {
            if ($details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %s AND blog_id = %d", $_GET['k'], $wpdb->blogid))) {
                if ($details->blog_id == $wpdb->blogid) {
                    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET['k']));
                    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtablelogins} WHERE t < %d", (time() - 120)));
                    wp_set_auth_cookie($details->user_id);
                    wp_redirect(remove_query_arg(array('dm', 'action', 'k', 't', $protocol . $current_blog->domain . $_SERVER['REQUEST_URI'])));
                    exit;
                } else {
                    wp_die(__("Incorrect or out of date login key", 'wp-domain-mapping'));
                }
            } else {
                wp_die(__("Unknown login key", 'wp-domain-mapping'));
            }
        } elseif ($_GET['action'] == 'logout') {
            if ($details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %s AND blog_id = %d", $_GET['k'], $_GET['blogid']))) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET['k']));
                $blog = get_blog_details($_GET['blogid']);
                wp_clear_auth_cookie();
                wp_redirect(trailingslashit($blog->siteurl) . "wp-login.php?loggedout=true");
                exit;
            } else {
                wp_die(__("Unknown logout key", 'wp-domain-mapping'));
            }
        }
    }
}

function remote_login_js_loader() {
    global $current_site, $current_blog;
    if (0 == get_site_option('dm_remote_login') || is_user_logged_in()) return;

    $protocol = is_ssl() ? 'https://' : 'http://';
    $hash = get_dm_hash();
    echo "<script src='{$protocol}{$current_site->domain}{$current_site->path}?dm={$hash}&action=load&blogid={$current_blog->blog_id}&siteid={$current_blog->site_id}&t=" . mt_rand() . "&back=" . urlencode($protocol . $current_blog->domain . $_SERVER['REQUEST_URI']) . "' type='text/javascript'></script>";
}

function delete_blog_domain_mapping($blog_id, $drop) {
    global $wpdb;
    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    if ($blog_id && $drop) {
        $domains = $wpdb->get_col($wpdb->prepare("SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = %d", $blog_id));
        do_action('dm_delete_blog_domain_mappings', $domains);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->dmtable} WHERE blog_id = %d", $blog_id));
    }
}
add_action('delete_blog', 'delete_blog_domain_mapping', 1, 2);

function ra_domain_mapping_columns($columns) {
    $columns['map'] = __('Mapping');
    return $columns;
}
add_filter('wpmu_blogs_columns', 'ra_domain_mapping_columns');

function ra_domain_mapping_field($column, $blog_id) {
    global $wpdb;
    static $maps = false;

    if ($column == 'map') {
        if ($maps === false) {
            $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
            $work = $wpdb->get_results("SELECT blog_id, domain FROM {$wpdb->dmtable} ORDER BY blog_id");
            $maps = array();
            if ($work) {
                foreach ($work as $blog) {
                    $maps[$blog->blog_id][] = $blog->domain;
                }
            }
        }
        if (!empty($maps[$blog_id]) && is_array($maps[$blog_id])) {
            foreach ($maps[$blog_id] as $blog) {
                echo esc_html($blog) . '<br />';
            }
        }
    }
}
add_action('manage_blogs_custom_column', 'ra_domain_mapping_field', 1, 3);
add_action('manage_sites_custom_column', 'ra_domain_mapping_field', 1, 3);

function dm_site_admin() {
    return current_user_can('manage_network');
}

function dm_idn_warning() {
    return sprintf(__('International Domain Names should be in <a href="%s" target="_blank">punycode</a> format.', 'wp-domain-mapping'), "https://www.punycoder.com/");
}
?>
