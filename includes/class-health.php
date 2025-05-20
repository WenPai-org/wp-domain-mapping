<?php
/**
 * 域名健康监控功能
 *
 * 处理域名健康状态检查和通知
 *
 * @package WP Domain Mapping
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 域名健康监控类
 */
class WP_Domain_Mapping_Health {

    /**
     * 类实例
     *
     * @var WP_Domain_Mapping_Health
     */
    protected static $instance;

    /**
     * 数据库实例
     */
    private $db;

    /**
     * 获取类实例
     *
     * @return WP_Domain_Mapping_Health
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->db = WP_Domain_Mapping_DB::get_instance();

        // 添加菜单页面
        add_action('network_admin_menu', array($this, 'add_menu_page'), 30);

        // 添加AJAX处理程序
        add_action('wp_ajax_dm_check_domain_health', array($this, 'ajax_check_domain_health'));

        // 添加计划任务钩子
        add_action('dm_domain_health_check', array($this, 'scheduled_health_check'));

        // 初始化计划任务
        $this->initialize_cron();

        // 处理手动健康检查
        add_action('admin_init', array($this, 'handle_manual_check'));

        // 处理设置保存
        add_action('admin_init', array($this, 'handle_settings_save'));
    }

    /**
     * 初始化定时健康检查
     */
    private function initialize_cron() {
        // 注册激活时的钩子
        register_activation_hook(WP_DOMAIN_MAPPING_BASENAME, array($this, 'schedule_health_check'));

        // 注册停用时的钩子
        register_deactivation_hook(WP_DOMAIN_MAPPING_BASENAME, array($this, 'unschedule_health_check'));
    }

    /**
     * 添加健康监控菜单
     */
    public function add_menu_page() {
        add_submenu_page(
            'settings.php',
            __('Domain Health', 'wp-domain-mapping'),
            __('Domain Health', 'wp-domain-mapping'),
            'manage_network',
            'domain-mapping-health',
            array($this, 'render_page')
        );
    }

    /**
     * 计划健康检查任务
     */
    public function schedule_health_check() {
        if (!wp_next_scheduled('dm_domain_health_check')) {
            wp_schedule_event(time(), 'daily', 'dm_domain_health_check');
        }
    }

    /**
     * 取消健康检查任务
     */
    public function unschedule_health_check() {
        $timestamp = wp_next_scheduled('dm_domain_health_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dm_domain_health_check');
        }
    }

    /**
     * 处理手动健康检查
     */
    public function handle_manual_check() {
        if (isset($_POST['dm_manual_health_check']) && $_POST['dm_manual_health_check']) {
            // 验证nonce
            if (!isset($_POST['dm_manual_health_check_nonce']) || !wp_verify_nonce($_POST['dm_manual_health_check_nonce'], 'dm_manual_health_check')) {
                wp_die(__('Security check failed.', 'wp-domain-mapping'));
            }

            // 检查权限
            if (!current_user_can('manage_network')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
            }

            // 执行健康检查
            $this->run_health_check_for_all_domains();

            // 重定向回健康页面
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-health', 'checked' => 1), network_admin_url('settings.php')));
            exit;
        }
    }

    /**
     * 处理设置保存
     */
    public function handle_settings_save() {
        if (isset($_POST['dm_health_settings']) && $_POST['dm_health_settings']) {
            // 验证nonce
            if (!isset($_POST['dm_health_settings_nonce']) || !wp_verify_nonce($_POST['dm_health_settings_nonce'], 'dm_health_settings')) {
                wp_die(__('Security check failed.', 'wp-domain-mapping'));
            }

            // 检查权限
            if (!current_user_can('manage_network')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
            }

            // 保存设置
            $health_check_enabled = isset($_POST['health_check_enabled']) ? (bool) $_POST['health_check_enabled'] : false;
            $health_notifications_enabled = isset($_POST['health_notifications_enabled']) ? (bool) $_POST['health_notifications_enabled'] : false;
            $notification_email = isset($_POST['notification_email']) ? sanitize_email($_POST['notification_email']) : '';
            $ssl_expiry_threshold = isset($_POST['ssl_expiry_threshold']) ? intval($_POST['ssl_expiry_threshold']) : 14;

            update_site_option('dm_health_check_enabled', $health_check_enabled);
            update_site_option('dm_health_notifications_enabled', $health_notifications_enabled);
            update_site_option('dm_notification_email', $notification_email);
            update_site_option('dm_ssl_expiry_threshold', $ssl_expiry_threshold);

            // 如果启用了自动检查，确保计划任务已设置
            if ($health_check_enabled) {
                $this->schedule_health_check();
            } else {
                $this->unschedule_health_check();
            }

            // 重定向回健康页面
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-health', 'settings-updated' => 1), network_admin_url('settings.php')));
            exit;
        }
    }

    /**
     * 渲染健康监控页面
     */
    public function render_page() {
        // 检查权限
        if (!current_user_can('manage_network')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-domain-mapping'));
        }

        // 获取所有域名
        global $wpdb;
        $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;
        $domains = $wpdb->get_results("
            SELECT d.*, b.domain as original_domain, b.path
            FROM {$table} d
            JOIN {$wpdb->blogs} b ON d.blog_id = b.blog_id
            ORDER BY d.blog_id ASC, d.active DESC
        ");

        // 获取健康检查结果（如果有）
        $health_results = get_site_option('dm_domain_health_results', array());

        // 渲染页面
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php
            // 显示成功消息（如果有）
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

            <div class="card domain-mapping-card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
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

            <div class="card domain-mapping-card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
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

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 单个域名健康检查
            $('.check-domain-health').on('click', function() {
                var $button = $(this);
                var domain = $button.data('domain');
                var $row = $button.closest('tr');

                $button.prop('disabled', true).text('<?php esc_html_e('Checking...', 'wp-domain-mapping'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dm_check_domain_health',
                        domain: domain,
                        nonce: '<?php echo wp_create_nonce('dm_check_domain_health'); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // 刷新页面以显示更新的结果
                            location.reload();
                        } else {
                            alert(response.data || '<?php esc_html_e('An error occurred during the health check.', 'wp-domain-mapping'); ?>');
                            $button.prop('disabled', false).text('<?php esc_html_e('Check Now', 'wp-domain-mapping'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('An error occurred during the health check.', 'wp-domain-mapping'); ?>');
                        $button.prop('disabled', false).text('<?php esc_html_e('Check Now', 'wp-domain-mapping'); ?>');
                    }
                });
            });

            // 显示/隐藏健康详情
            $('.show-health-details').on('click', function() {
                var domain = $(this).data('domain');
                var domainKey = md5(domain);
                $('#health-details-' + domainKey).toggle();
            });

            // 简单MD5函数 (用于生成域名的唯一ID)
            function md5(string) {
                function md5cycle(x, k) {
                    var a = x[0], b = x[1], c = x[2], d = x[3];

                    a = ff(a, b, c, d, k[0], 7, -680876936);
                    d = ff(d, a, b, c, k[1], 12, -389564586);
                    c = ff(c, d, a, b, k[2], 17, 606105819);
                    b = ff(b, c, d, a, k[3], 22, -1044525330);
                    // ...其余MD5算法...

                    x[0] = add32(a, x[0]);
                    x[1] = add32(b, x[1]);
                    x[2] = add32(c, x[2]);
                    x[3] = add32(d, x[3]);
                }

                function cmn(q, a, b, x, s, t) {
                    a = add32(add32(a, q), add32(x, t));
                    return add32((a << s) | (a >>> (32 - s)), b);
                }

                function ff(a, b, c, d, x, s, t) {
                    return cmn((b & c) | ((~b) & d), a, b, x, s, t);
                }

                function add32(a, b) {
                    return (a + b) & 0xFFFFFFFF;
                }

                // 简化版本，仅用于客户端显示目的
                var hash = 0;
                if (string.length === 0) return hash;

                for (var i = 0; i < string.length; i++) {
                    var char = string.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash; // 转换为32位整数
                }

                return Math.abs(hash).toString(16);
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX处理域名健康检查
     */
    public function ajax_check_domain_health() {
        // 检查权限
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'wp-domain-mapping'));
        }

        // 验证nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dm_check_domain_health')) {
            wp_send_json_error(__('Security check failed.', 'wp-domain-mapping'));
        }

        // 获取域名
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        if (empty($domain)) {
            wp_send_json_error(__('No domain specified.', 'wp-domain-mapping'));
        }

        // 执行健康检查
        $result = $this->check_domain_health($domain);

        // 保存结果
        $this->save_health_check_result($domain, $result);

        // 返回结果
        wp_send_json_success($result);
    }

    /**
     * 计划任务：执行所有域名健康检查
     */
    public function scheduled_health_check() {
        // 检查是否启用了自动健康检查
        if (!get_site_option('dm_health_check_enabled', true)) {
            return;
        }

        $this->run_health_check_for_all_domains();
    }

    /**
     * 对所有域名执行健康检查
     */
    private function run_health_check_for_all_domains() {
        global $wpdb;
        $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;

        // 获取所有域名
        $domains = $wpdb->get_col("SELECT domain FROM {$table}");

        // 初始化通知信息
        $issues = array();

        // 检查每个域名
        foreach ($domains as $domain) {
            $result = $this->check_domain_health($domain);
            $this->save_health_check_result($domain, $result);

            // 检查是否有问题
            if ($this->has_health_issues($result)) {
                $issues[$domain] = $result;
            }
        }

        // 如果启用了通知并且有问题，发送通知
        if (!empty($issues) && get_site_option('dm_health_notifications_enabled', true)) {
            $this->send_health_notification($issues);
        }

        return true;
    }

    /**
     * 检查域名健康状态
     *
     * @param string $domain 要检查的域名
     * @return array 健康检查结果
     */
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

        // 获取服务器IP或CNAME
        $server_ip = get_site_option('dm_ipaddress', '');
        $server_cname = get_site_option('dm_cname', '');

        // 检查DNS设置
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

        // 检查网站可访问性和SSL
        $response = $this->test_domain_connection($domain);

        if ($response) {
            $result['accessible'] = $response['accessible'];
            $result['response_code'] = $response['response_code'];
            $result['ssl_valid'] = $response['ssl_valid'];
            $result['ssl_expiry'] = $response['ssl_expiry'];
        }

        return $result;
    }

    /**
     * 测试域名连接
     *
     * @param string $domain 域名
     * @return array|false 连接测试结果
     */
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

        // 测试HTTPS连接
        $ch = curl_init('https://' . $domain);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['response_code'] = $response_code;

        if ($response !== false && $response_code > 0) {
            $result['accessible'] = ($response_code >= 200 && $response_code < 400);
            $result['ssl_valid'] = ($response !== false);

            // 获取SSL证书信息
            $ssl_info = curl_getinfo($ch, CURLINFO_CERTINFO);
            if (!empty($ssl_info) && isset($ssl_info[0]['Expire date'])) {
                $result['ssl_expiry'] = $ssl_info[0]['Expire date'];
            }
        }

        curl_close($ch);

        // 如果HTTPS失败，尝试HTTP
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

    /**
     * 保存健康检查结果
     *
     * @param string $domain 域名
     * @param array $result 健康检查结果
     */
    private function save_health_check_result($domain, $result) {
        $health_results = get_site_option('dm_domain_health_results', array());
        $domain_key = md5($domain);

        $health_results[$domain_key] = $result;
        update_site_option('dm_domain_health_results', $health_results);
    }

    /**
     * 检查结果是否有健康问题
     *
     * @param array $result 健康检查结果
     * @return bool 是否有问题
     */
    private function has_health_issues($result) {
        // 检查DNS问题
        if ($result['dns_status'] !== 'success') {
            return true;
        }

        // 检查SSL问题
        if (!$result['ssl_valid']) {
            return true;
        }

        // 检查SSL即将到期
        if (!empty($result['ssl_expiry'])) {
            $expiry_date = strtotime($result['ssl_expiry']);
            $threshold = get_site_option('dm_ssl_expiry_threshold', 14);
            $threshold_date = strtotime('+' . $threshold . ' days');

            if ($expiry_date <= $threshold_date) {
                return true;
            }
        }

        // 检查可访问性问题
        if (!$result['accessible']) {
            return true;
        }

        return false;
    }

    /**
     * 发送健康问题通知
     *
     * @param array $issues 有问题的域名及其健康检查结果
     */
    private function send_health_notification($issues) {
        $notification_email = get_site_option('dm_notification_email', get_option('admin_email'));

        if (empty($notification_email)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Domain Mapping Health Alert', 'wp-domain-mapping'), $site_name);

        // 构建邮件内容
        $message = sprintf(__('Domain health issues were detected on %s.', 'wp-domain-mapping'), $site_name) . "\n\n";
        $message .= __('The following domains have issues:', 'wp-domain-mapping') . "\n\n";

        foreach ($issues as $domain => $result) {
            $message .= sprintf(__('Domain: %s', 'wp-domain-mapping'), $domain) . "\n";

            // 添加DNS状态
            if ($result['dns_status'] !== 'success') {
                $message .= "  - " . sprintf(__('DNS Issue: %s', 'wp-domain-mapping'), $result['dns_message']) . "\n";
            }

            // 添加SSL状态
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

            // 添加可访问性状态
            if (!$result['accessible']) {
                $message .= "  - " . __('Site is not accessible.', 'wp-domain-mapping') . "\n";
                if ($result['response_code'] > 0) {
                    $message .= "    " . sprintf(__('HTTP Response Code: %d', 'wp-domain-mapping'), $result['response_code']) . "\n";
                }
            }

            $message .= "\n";
        }

        // 添加解决方案链接
        $message .= sprintf(
            __('To view and manage these issues, please visit: %s', 'wp-domain-mapping'),
            network_admin_url('settings.php?page=domain-mapping-health')
        ) . "\n";

        // 发送邮件
        wp_mail($notification_email, $subject, $message);
    }
}
