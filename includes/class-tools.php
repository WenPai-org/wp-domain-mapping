<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Domain_Mapping_Tools {
    private static $instance = null;
    private $db;
    private $core;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = WP_Domain_Mapping_DB::get_instance();
        $this->core = WP_Domain_Mapping_Core::get_instance();
        
        // Setup hooks
        $this->setup_hooks();
    }
    
    private function setup_hooks() {
        // Add menu pages for tools
        add_action('network_admin_menu', array($this, 'add_menu_pages'), 20);
        
        // Add Site ID to toolbar
        add_action('admin_bar_menu', array($this, 'add_site_id_to_toolbar'), 100);
        
        // Add Site ID column to sites list
        add_filter('manage_sites-network_columns', array($this, 'add_site_id_column'), 20);
        add_action('manage_sites_custom_column', array($this, 'display_site_id_column'), 10, 2);
        add_action('admin_print_styles', array($this, 'site_id_column_style'));
        
        // AJAX handlers
        add_action('wp_ajax_dm_check_domain_health', array($this, 'ajax_check_domain_health'));
        add_action('wp_ajax_dm_import_csv', array($this, 'ajax_import_csv'));
        
        // Scheduled tasks
        add_action('dm_domain_health_check', array($this, 'scheduled_health_check'));
        
        // Admin init actions
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_health_manual_check'));
        add_action('admin_init', array($this, 'handle_health_settings_save'));
    }
    
    public function add_menu_pages() {
        add_submenu_page(
            'settings.php',
            __('Domain Health', 'wp-domain-mapping'),
            __('Domain Health', 'wp-domain-mapping'),
            'manage_network',
            'domain-mapping-health',
            'dm_render_health_page'
        );
        
        add_submenu_page(
            'settings.php',
            __('Import/Export Domains', 'wp-domain-mapping'),
            __('Import/Export Domains', 'wp-domain-mapping'),
            'manage_network',
            'domain-mapping-import-export',
            'dm_render_import_export_page'
        );
    }
    
    // Site ID Display Functions
    public function add_site_id_to_toolbar($admin_bar) {
        // Only show to admins or network admins
        if (!current_user_can('manage_options') && !is_super_admin()) {
            return;
        }

        $blog_id = get_current_blog_id();

        $admin_bar->add_menu(array(
            'id'    => 'wp-site-ID',
            'title' => sprintf(__('Site ID: %d', 'wp-domain-mapping'), $blog_id),
            'href'  => is_super_admin() ? esc_url(network_admin_url('site-info.php?id=' . $blog_id)) : '#',
            'meta'  => array(
                'title' => is_super_admin() ? __('Edit this site', 'wp-domain-mapping') : __('Current site ID', 'wp-domain-mapping'),
                'class' => 'dm-site-id-menu'
            ),
        ));
    }
    
    public function add_site_id_column($columns) {
        // Add site ID column after first column
        $columns = array_slice($columns, 0, 1, true) +
                  array('dm_site_id' => __('Site ID', 'wp-domain-mapping')) +
                  array_slice($columns, 1, count($columns) - 1, true);

        return $columns;
    }
    
    public function display_site_id_column($column, $blog_id) {
        if ('dm_site_id' == $column) {
            echo '<span class="dm-site-id-badge">' . esc_html($blog_id) . '</span>';
        }
    }
    
    public function site_id_column_style() {
        if ('sites-network' == get_current_screen()->id) {
            ?>
            <style type="text/css">
                th#dm_site_id { width: 3.5em; }
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
    
    // Health Check Functions
    public function schedule_health_check() {
        if (!wp_next_scheduled('dm_domain_health_check')) {
            wp_schedule_event(time(), 'daily', 'dm_domain_health_check');
        }
    }
    
    public function unschedule_health_check() {
        $timestamp = wp_next_scheduled('dm_domain_health_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dm_domain_health_check');
        }
    }
    
    public function handle_health_manual_check() {
        if (isset($_POST['dm_manual_health_check']) && $_POST['dm_manual_health_check']) {
            // Verify nonce
            if (!isset($_POST['dm_manual_health_check_nonce']) || !wp_verify_nonce($_POST['dm_manual_health_check_nonce'], 'dm_manual_health_check')) {
                wp_die(__('Security check failed.', 'wp-domain-mapping'));
            }

            // Check permissions
            if (!current_user_can('manage_network')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
            }

            // Run the health check
            $this->run_health_check_for_all_domains();

            // Redirect back to health page
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-health', 'checked' => 1), network_admin_url('settings.php')));
            exit;
        }
    }
    
    public function handle_health_settings_save() {
        if (isset($_POST['dm_health_settings']) && $_POST['dm_health_settings']) {
            // Verify nonce
            if (!isset($_POST['dm_health_settings_nonce']) || !wp_verify_nonce($_POST['dm_health_settings_nonce'], 'dm_health_settings')) {
                wp_die(__('Security check failed.', 'wp-domain-mapping'));
            }

            // Check permissions
            if (!current_user_can('manage_network')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
            }

            // Save settings
            $health_check_enabled = isset($_POST['health_check_enabled']) ? (bool) $_POST['health_check_enabled'] : false;
            $health_notifications_enabled = isset($_POST['health_notifications_enabled']) ? (bool) $_POST['health_notifications_enabled'] : false;
            $notification_email = isset($_POST['notification_email']) ? sanitize_email($_POST['notification_email']) : '';
            $ssl_expiry_threshold = isset($_POST['ssl_expiry_threshold']) ? intval($_POST['ssl_expiry_threshold']) : 14;

            update_site_option('dm_health_check_enabled', $health_check_enabled);
            update_site_option('dm_health_notifications_enabled', $health_notifications_enabled);
            update_site_option('dm_notification_email', $notification_email);
            update_site_option('dm_ssl_expiry_threshold', $ssl_expiry_threshold);

            // If auto check is enabled, ensure cron is set
            if ($health_check_enabled) {
                $this->schedule_health_check();
            } else {
                $this->unschedule_health_check();
            }

            // Redirect back to health page
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-health', 'settings-updated' => 1), network_admin_url('settings.php')));
            exit;
        }
    }
    
    public function ajax_check_domain_health() {
        // Check permissions
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dm_check_domain_health')) {
            wp_send_json_error(__('Security check failed.', 'wp-domain-mapping'));
        }

        // Get domain
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        if (empty($domain)) {
            wp_send_json_error(__('No domain specified.', 'wp-domain-mapping'));
        }

        // Run health check
        $result = $this->check_domain_health($domain);

        // Save result
        $this->db->save_health_result($domain, $result);

        // Return result
        wp_send_json_success($result);
    }
    
    public function scheduled_health_check() {
        // Check if health checks are enabled
        if (!get_site_option('dm_health_check_enabled', true)) {
            return;
        }

        $this->run_health_check_for_all_domains();
    }
    
    private function run_health_check_for_all_domains() {
        global $wpdb;
        $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;

        // Get all domains
        $domains = $wpdb->get_col("SELECT domain FROM {$table}");

        // Initialize issues array
        $issues = array();

        // Check each domain
        foreach ($domains as $domain) {
            $result = $this->check_domain_health($domain);
            $this->db->save_health_result($domain, $result);

            // Check for issues
            if ($this->has_health_issues($result)) {
                $issues[$domain] = $result;
            }
        }

        // Send notifications if enabled and issues exist
        if (!empty($issues) && get_site_option('dm_health_notifications_enabled', true)) {
            $this->send_health_notification($issues);
        }

        return true;
    }
    
    private function check_domain_health($domain) {
        $result = array(
            'domain' => $domain,
            'last_check' => current_time('mysql'),
            'dns_status' => 'error',
            'dns_message' => __('DNS check not performed', 'wp-domain-mapping'),
            'resolved_ip' => '',
            'ssl_valid' => false,
            'ssl_expiry' => '',
            'accessible' => false,
            'response_code' => 0
        );

        // Get server IP or CNAME
        $server_ip = get_site_option('dm_ipaddress', '');
        $server_cname = get_site_option('dm_cname', '');

        // Check DNS setting
        $domain_ip = gethostbyname($domain);
        $result['resolved_ip'] = $domain_ip;

        if ($domain_ip && $domain_ip !== $domain) {
            if ($server_ip && strpos($server_ip, $domain_ip) !== false) {
                $result['dns_status'] = 'success';
                $result['dns_message'] = __('Domain A record is correctly pointing to server IP.', 'wp-domain-mapping');
            } elseif ($server_cname && function_exists('dns_get_record')) {
                $dns_records = @dns_get_record($domain, DNS_CNAME);
                $has_valid_cname = false;

                if ($dns_records) {
                    foreach ($dns_records as $record) {
                        if (isset($record['target']) &&
                            (
                                $record['target'] === $server_cname ||
                                strpos($record['target'], $server_cname) !== false
                            )) {
                            $has_valid_cname = true;
                            break;
                        }
                    }
                }

                if ($has_valid_cname) {
                    $result['dns_status'] = 'success';
                    $result['dns_message'] = __('Domain CNAME record is correctly configured.', 'wp-domain-mapping');
                } else {
                    $result['dns_message'] = __('Domain is not pointing to the correct server.', 'wp-domain-mapping');
                }
            } else {
                $result['dns_message'] = __('Cannot verify DNS configuration.', 'wp-domain-mapping');
            }
        } else {
            $result['dns_message'] = __('Domain does not resolve to an IP address.', 'wp-domain-mapping');
        }

        // Check site accessibility and SSL
        $response = $this->test_domain_connection($domain);

        if ($response) {
            $result['accessible'] = $response['accessible'];
            $result['response_code'] = $response['response_code'];
            $result['ssl_valid'] = $response['ssl_valid'];
            $result['ssl_expiry'] = $response['ssl_expiry'];
        }

        return $result;
    }
    
    private function test_domain_connection($domain) {
        if (!function_exists('curl_init')) {
            return false;
        }

        $result = array(
            'accessible' => false,
            'response_code' => 0,
            'ssl_valid' => false,
            'ssl_expiry' => ''
        );

        // Test HTTPS connection
        $ch = curl_init('https://' . $domain);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CERTINFO, true);

        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['response_code'] = $response_code;

        if ($response !== false && $response_code > 0) {
            $result['accessible'] = ($response_code >= 200 && $response_code < 400);
            $result['ssl_valid'] = ($response !== false);

            // Get SSL certificate info
            $cert_info = curl_getinfo($ch, CURLINFO_CERTINFO);
            if (!empty($cert_info) && isset($cert_info[0]['Expire date'])) {
                $result['ssl_expiry'] = $cert_info[0]['Expire date'];
            }
        }

        curl_close($ch);

        // If HTTPS failed, try HTTP
        if (!$result['accessible']) {
            $ch = curl_init('http://' . $domain);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response !== false && $response_code > 0) {
                $result['accessible'] = ($response_code >= 200 && $response_code < 400);
                $result['response_code'] = $response_code;
            }

            curl_close($ch);
        }

        return $result;
    }
    
    private function has_health_issues($result) {
        // Check DNS issues
        if ($result['dns_status'] !== 'success') {
            return true;
        }

        // Check SSL issues
        if (!$result['ssl_valid']) {
            return true;
        }

        // Check SSL expiry
        if (!empty($result['ssl_expiry'])) {
            $expiry_date = strtotime($result['ssl_expiry']);
            $threshold = get_site_option('dm_ssl_expiry_threshold', 14);
            $threshold_date = strtotime('+' . $threshold . ' days');

            if ($expiry_date <= $threshold_date) {
                return true;
            }
        }

        // Check accessibility issues
        if (!$result['accessible']) {
            return true;
        }

        return false;
    }
    
    private function send_health_notification($issues) {
        $notification_email = get_site_option('dm_notification_email', get_option('admin_email'));

        if (empty($notification_email)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Domain Mapping Health Alert', 'wp-domain-mapping'), $site_name);

        // Build email content
        $message = sprintf(__('Domain health issues were detected on %s.', 'wp-domain-mapping'), $site_name) . "\n\n";
        $message .= __('The following domains have issues:', 'wp-domain-mapping') . "\n\n";

        foreach ($issues as $domain => $result) {
            $message .= sprintf(__('Domain: %s', 'wp-domain-mapping'), $domain) . "\n";

            // Add DNS status
            if ($result['dns_status'] !== 'success') {
                $message .= "  - " . sprintf(__('DNS Issue: %s', 'wp-domain-mapping'), $result['dns_message']) . "\n";
            }

            // Add SSL status
            if (!$result['ssl_valid']) {
                $message .= "  - " . __('SSL Certificate is invalid or missing.', 'wp-domain-mapping') . "\n";
            } elseif (!empty($result['ssl_expiry'])) {
                $expiry_date = strtotime($result['ssl_expiry']);
                $threshold = get_site_option('dm_ssl_expiry_threshold', 14);
                $threshold_date = strtotime('+' . $threshold . ' days');

                if ($expiry_date <= $threshold_date) {
                    $message .= "  - " . sprintf(
                        __('SSL Certificate expires on %s (within %d days).', 'wp-domain-mapping'),
                        date('Y-m-d', $expiry_date),
                        $threshold
                    ) . "\n";
                }
            }

            // Add accessibility status
            if (!$result['accessible']) {
                $message .= "  - " . __('Site is not accessible.', 'wp-domain-mapping') . "\n";
                if ($result['response_code'] > 0) {
                    $message .= "    " . sprintf(__('HTTP Response Code: %d', 'wp-domain-mapping'), $result['response_code']) . "\n";
                }
            }

            $message .= "\n";
        }

        // Add resolution link
        $message .= sprintf(
            __('To view and manage these issues, please visit: %s', 'wp-domain-mapping'),
            network_admin_url('settings.php?page=domain-mapping-health')
        ) . "\n";

        // Send email
        wp_mail($notification_email, $subject, $message);
    }
    
    // Import/Export Functions
    public function handle_export() {
        if (!isset($_POST['domain_mapping_export']) || !$_POST['domain_mapping_export']) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_network')) {
            wp_die(__('You do not have sufficient permissions to export data.', 'wp-domain-mapping'));
        }

        // Verify nonce
        if (!isset($_POST['domain_mapping_export_nonce']) || !wp_verify_nonce($_POST['domain_mapping_export_nonce'], 'domain_mapping_export')) {
            wp_die(__('Invalid security token. Please try again.', 'wp-domain-mapping'));
        }

        // Get options
        $include_header = isset($_POST['include_header']) ? (bool) $_POST['include_header'] : false;
        $blog_id_filter = isset($_POST['blog_id_filter']) && !empty($_POST['blog_id_filter']) ? intval($_POST['blog_id_filter']) : 0;

        // Get domain mapping data
        global $wpdb;
        $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;
        $sql = "SELECT blog_id, domain, active FROM {$table}";

        if ($blog_id_filter > 0) {
            $sql .= $wpdb->prepare(" WHERE blog_id = %d", $blog_id_filter);
        }

        $domains = $wpdb->get_results($sql, ARRAY_A);

        if (empty($domains)) {
            // No data
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-import-export', 'export' => 'empty'), network_admin_url('settings.php')));
            exit;
        }

        // Set up CSV output
        $filename = 'domain-mappings-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add header row
        if ($include_header) {
            fputcsv($output, array('blog_id', 'domain', 'active'));
        }

        // Add data rows
        foreach ($domains as $domain) {
            fputcsv($output, $domain);
        }

        fclose($output);
        exit;
    }
    
    public function ajax_import_csv() {
        // Check permissions
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('You do not have sufficient permissions to import data.', 'wp-domain-mapping'));
        }

        // Verify nonce
        if (!isset($_POST['domain_mapping_import_nonce']) || !wp_verify_nonce($_POST['domain_mapping_import_nonce'], 'domain_mapping_import')) {
            wp_send_json_error(__('Invalid security token. Please try again.', 'wp-domain-mapping'));
        }

        // Check file
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            wp_send_json_error(__('No file uploaded or upload error.', 'wp-domain-mapping'));
        }

        // Get options
        $has_header = isset($_POST['has_header']) ? (bool) $_POST['has_header'] : false;
        $update_existing = isset($_POST['update_existing']) ? (bool) $_POST['update_existing'] : false;
        $validate_sites = isset($_POST['validate_sites']) ? (bool) $_POST['validate_sites'] : true;

        // Open file
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$file) {
            wp_send_json_error(__('Could not open the uploaded file.', 'wp-domain-mapping'));
        }

        // Initialize counters and log
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $log = array();

        // Skip header row
        if ($has_header) {
            fgetcsv($file);
        }

        // Process each row
        $row_num = $has_header ? 2 : 1; // Account for header row

        while (($data = fgetcsv($file)) !== false) {
            // Check data format
            if (count($data) < 3) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid format. Expected at least 3 columns.', 'wp-domain-mapping'), $row_num)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Parse data
            $blog_id = intval($data[0]);
            $domain = $this->core->clean_domain(trim($data[1]));
            $active = intval($data[2]);

            // Validate blog_id
            if ($blog_id <= 0) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid blog ID: %d', 'wp-domain-mapping'), $row_num, $blog_id)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Validate site exists
            if ($validate_sites && !get_blog_details($blog_id)) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Site ID %d does not exist.', 'wp-domain-mapping'), $row_num, $blog_id)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Validate domain format
            if (!$this->core->validate_domain($domain)) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid domain format: %s', 'wp-domain-mapping'), $row_num, $domain)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // Check if domain already exists
            $existing = $this->db->get_domain_by_name($domain);

            if ($existing) {
                if ($existing->blog_id != $blog_id) {
                    $log[] = array(
                        'status' => 'error',
                        'message' => sprintf(__('Row %d: Domain %s is already mapped to blog ID %d.', 'wp-domain-mapping'),
                            $row_num, $domain, $existing->blog_id)
                    );
                    $errors++;
                } elseif (!$update_existing) {
                    $log[] = array(
                        'status' => 'warning',
                        'message' => sprintf(__('Row %d: Domain %s already exists for blog ID %d. Skipped.', 'wp-domain-mapping'),
                            $row_num, $domain, $blog_id)
                    );
                    $skipped++;
                } else {
                    // Update existing domain
                    $success = $this->db->update_domain($domain, $blog_id, $active);

                    if ($success) {
                        $log[] = array(
                            'status' => 'success',
                            'message' => sprintf(__('Row %d: Updated domain %s for blog ID %d.', 'wp-domain-mapping'),
                                $row_num, $domain, $blog_id)
                        );
                        $imported++;
                    } else {
                        $log[] = array(
                            'status' => 'error',
                            'message' => sprintf(__('Row %d: Failed to update domain %s for blog ID %d.', 'wp-domain-mapping'),
                                $row_num, $domain, $blog_id)
                        );
                        $errors++;
                    }
                }
            } else {
                // Add new domain
                $success = $this->db->add_domain($blog_id, $domain, $active);

                if ($success) {
                    $log[] = array(
                        'status' => 'success',
                        'message' => sprintf(__('Row %d: Added domain %s for blog ID %d.', 'wp-domain-mapping'),
                            $row_num, $domain, $blog_id)
                    );
                    $imported++;
                } else {
                    $log[] = array(
                        'status' => 'error',
                        'message' => sprintf(__('Row %d: Failed to add domain %s for blog ID %d.', 'wp-domain-mapping'),
                            $row_num, $domain, $blog_id)
                    );
                    $errors++;
                }
            }

            $row_num++;
        }

        fclose($file);

        // Build response
        $message = sprintf(
            __('Import completed: %d imported, %d skipped, %d errors.', 'wp-domain-mapping'),
            $imported, $skipped, $errors
        );

        wp_send_json_success(array(
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $log
        ));
    }
}
