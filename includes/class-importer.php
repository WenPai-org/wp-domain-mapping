<?php
/**
 * 域名映射导入/导出功能
 *
 * 处理域名映射的批量导入和导出
 *
 * @package WP Domain Mapping
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 域名映射导入/导出类
 */
class WP_Domain_Mapping_Importer {

    /**
     * 类实例
     *
     * @var WP_Domain_Mapping_Importer
     */
    protected static $instance;

    /**
     * 数据库实例
     */
    private $db;

    /**
     * 获取类实例
     *
     * @return WP_Domain_Mapping_Importer
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
        add_action('network_admin_menu', array($this, 'add_menu_page'), 20);

        // 处理表单提交
        add_action('admin_init', array($this, 'handle_form_submission'));

        // AJAX处理程序
        add_action('wp_ajax_dm_import_csv', array($this, 'ajax_import_csv'));
    }

    /**
     * 添加导入/导出菜单
     */
    public function add_menu_page() {
        add_submenu_page(
            'settings.php',
            __('Import/Export Domains', 'wp-domain-mapping'),
            __('Import/Export Domains', 'wp-domain-mapping'),
            'manage_network',
            'domain-mapping-import-export',
            array($this, 'render_page')
        );
    }

    /**
     * 渲染导入/导出页面
     */
    public function render_page() {
        // 检查权限
        if (!current_user_can('manage_network')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-domain-mapping'));
        }

        // 输出成功消息（如果有）
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

        // 显示页面
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card domain-mapping-card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
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

            <div class="card domain-mapping-card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
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

            <div class="card domain-mapping-card" style="max-width: 100%; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
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

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#domain-mapping-import-form').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('action', 'dm_import_csv');

                // 显示进度条
                $('#import-progress').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        // 隐藏进度条
                        $('#import-progress').hide();

                        // 显示结果
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
                        // 隐藏进度条
                        $('#import-progress').hide();

                        // 显示错误
                        $('#import-results').show();
                        $('.import-summary').html(
                            '<div class="notice notice-error"><p>' +
                            '<?php _e('An error occurred during import.', 'wp-domain-mapping'); ?>' +
                            '</p></div>'
                        );
                    },
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();

                        // 上传进度
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
     * 处理表单提交
     */
    public function handle_form_submission() {
        // 处理导出
        if (isset($_POST['domain_mapping_export']) && $_POST['domain_mapping_export']) {
            $this->handle_export();
        }
    }

    /**
     * 处理CSV导出
     */
    private function handle_export() {
        // 检查权限
        if (!current_user_can('manage_network')) {
            wp_die(__('You do not have sufficient permissions to export data.', 'wp-domain-mapping'));
        }

        // 验证nonce
        if (!isset($_POST['domain_mapping_export_nonce']) || !wp_verify_nonce($_POST['domain_mapping_export_nonce'], 'domain_mapping_export')) {
            wp_die(__('Invalid security token. Please try again.', 'wp-domain-mapping'));
        }

        // 获取选项
        $include_header = isset($_POST['include_header']) ? (bool) $_POST['include_header'] : false;
        $blog_id_filter = isset($_POST['blog_id_filter']) && !empty($_POST['blog_id_filter']) ? intval($_POST['blog_id_filter']) : 0;

        // 获取域名映射数据
        global $wpdb;
        $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;
        $sql = "SELECT blog_id, domain, active FROM {$table}";

        if ($blog_id_filter > 0) {
            $sql .= $wpdb->prepare(" WHERE blog_id = %d", $blog_id_filter);
        }

        $domains = $wpdb->get_results($sql, ARRAY_A);

        if (empty($domains)) {
            // 没有数据
            wp_redirect(add_query_arg(array('page' => 'domain-mapping-import-export', 'export' => 'empty'), network_admin_url('settings.php')));
            exit;
        }

        // 设置CSV输出
        $filename = 'domain-mappings-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // 添加标题行
        if ($include_header) {
            fputcsv($output, array('blog_id', 'domain', 'active'));
        }

        // 添加数据行
        foreach ($domains as $domain) {
            fputcsv($output, $domain);
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX处理CSV导入
     */
    public function ajax_import_csv() {
        // 检查权限
        if (!current_user_can('manage_network')) {
            wp_send_json_error(__('You do not have sufficient permissions to import data.', 'wp-domain-mapping'));
        }

        // 验证nonce
        if (!isset($_POST['domain_mapping_import_nonce']) || !wp_verify_nonce($_POST['domain_mapping_import_nonce'], 'domain_mapping_import')) {
            wp_send_json_error(__('Invalid security token. Please try again.', 'wp-domain-mapping'));
        }

        // 检查文件
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            wp_send_json_error(__('No file uploaded or upload error.', 'wp-domain-mapping'));
        }

        // 获取选项
        $has_header = isset($_POST['has_header']) ? (bool) $_POST['has_header'] : false;
        $update_existing = isset($_POST['update_existing']) ? (bool) $_POST['update_existing'] : false;
        $validate_sites = isset($_POST['validate_sites']) ? (bool) $_POST['validate_sites'] : true;

        // 打开文件
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$file) {
            wp_send_json_error(__('Could not open the uploaded file.', 'wp-domain-mapping'));
        }

        // 初始化计数器和日志
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $log = array();

        // 跳过标题行
        if ($has_header) {
            fgetcsv($file);
        }

        // 处理每一行
        $core = WP_Domain_Mapping_Core::get_instance();
        $row_num = $has_header ? 2 : 1; // 考虑标题行

        while (($data = fgetcsv($file)) !== false) {
            // 检查数据格式
            if (count($data) < 3) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid format. Expected at least 3 columns.', 'wp-domain-mapping'), $row_num)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // 解析数据
            $blog_id = intval($data[0]);
            $domain = $core->clean_domain(trim($data[1]));
            $active = intval($data[2]);

            // 验证blog_id
            if ($blog_id <= 0) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid blog ID: %d', 'wp-domain-mapping'), $row_num, $blog_id)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // 验证站点是否存在
            if ($validate_sites && !get_blog_details($blog_id)) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Site ID %d does not exist.', 'wp-domain-mapping'), $row_num, $blog_id)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // 验证域名格式
            if (!$core->validate_domain($domain)) {
                $log[] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Row %d: Invalid domain format: %s', 'wp-domain-mapping'), $row_num, $domain)
                );
                $errors++;
                $row_num++;
                continue;
            }

            // 检查域名是否已经存在于其他站点
            global $wpdb;
            $table = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_DOMAINS;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE domain = %s",
                $domain
            ));

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
                    // 更新现有域名
                    $result = $wpdb->update(
                        $table,
                        array('active' => $active),
                        array('domain' => $domain),
                        array('%d'),
                        array('%s')
                    );

                    if ($result !== false) {
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
                // 添加新域名
                $result = $wpdb->insert(
                    $table,
                    array(
                        'blog_id' => $blog_id,
                        'domain' => $domain,
                        'active' => $active
                    ),
                    array('%d', '%s', '%d')
                );

                if ($result) {
                    // 记录操作
                    $this->db->log_action('import', $domain, $blog_id);

                    // 清除缓存
                    $this->db->invalidate_domain_cache($blog_id);

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

        // 构建响应
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
