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

        // 添加仪表盘小工具
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

        // 添加仪表盘小工具的AJAX处理
        add_action( 'wp_ajax_dm_refresh_widget_status', array( $this, 'ajax_refresh_widget_status' ) );
        add_action( 'wp_ajax_dm_quick_health_check', array( $this, 'ajax_quick_health_check' ) );

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
            wp_die( esc_html__( "The domain mapping plugin only works if WordPress is installed in the root directory of your webserver. It is currently installed in '%s'.", 'wp-domain-mapping' ) );
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
     * 添加仪表盘小工具
     */
    public function add_dashboard_widget() {
        // 只在子站显示，不在主站显示
        if ( is_main_site() ) {
            return;
        }

        // 检查用户权限和设置
        if ( ! current_user_can( 'manage_options' ) || ! get_site_option( 'dm_user_settings' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'dm_domain_status_widget',
            __( 'Domain Mapping Status', 'wp-domain-mapping' ),
            array( $this, 'render_dashboard_widget' ),
            array( $this, 'render_dashboard_widget_config' )
        );
    }

    /**
     * 渲染仪表盘小工具
     */
     public function render_dashboard_widget() {
         global $wpdb;
         $blog_id = get_current_blog_id();
         $domains = dm_get_domains_by_blog_id( $blog_id );
         $show_health_status = get_user_meta( get_current_user_id(), 'dm_widget_show_health', true );
         $show_health_status = ( $show_health_status === '' ) ? '1' : $show_health_status;
         ?>
         <style>
         .dm-dashboard-widget{padding:0}
         .dm-dashboard-widget>div{margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid #eee}
         .dm-dashboard-widget>div:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
         .dm-dashboard-widget h4{margin:0 0 8px 0;font-size:13px;font-weight:600;color:#1d2327;display:flex;align-items:center;justify-content:space-between}
         .dm-domain-primary{font-size:14px;display:flex;align-items:center;gap:8px}
         .dm-domain-primary a{text-decoration:none;color:#2271b1}
         .dm-domain-primary a:hover{color:#135e96;text-decoration:underline}
         .dm-no-primary{color:#666;font-style:italic;display:flex;align-items:center;gap:8px}
         .dm-stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center}
         .dm-stat{background:#f0f0f1;padding:10px;border-radius:4px}
         .dm-stat-value{display:block;font-size:24px;font-weight:600;color:#2271b1;line-height:1}
         .dm-stat-label{display:block;font-size:11px;color:#666;margin-top:5px}
         .dm-domains-list ul{margin:0;padding:0;list-style:none}
         .dm-domains-list li{padding:5px 0;display:flex;align-items:center;gap:5px}
         .dm-domains-list a{text-decoration:none;color:#50575e}
         .dm-domains-list a:hover{color:#2271b1}
         .dm-badge-primary{background:#2271b1;color:#fff;font-size:11px;padding:2px 6px;border-radius:3px;margin-left:auto}
         .dm-health-status{position:relative}
         .dm-refresh-health{padding:2px 4px!important;min-height:24px!important;line-height:1!important}
         .dm-refresh-health .dashicons{font-size:16px;width:16px;height:16px}
         .dm-health-good{color:#46b450;display:flex;align-items:center;gap:5px;margin:0}
         .dm-health-issues{background:#fcf0f1;border:1px solid #f0c4c6;border-radius:4px;padding:10px}
         .dm-issue-item{margin-bottom:5px}
         .dm-issue-item:last-child{margin-bottom:0}
         .dm-issue-list{color:#d63638;font-size:12px}
         .dm-last-check{margin:8px 0 0;font-size:12px;color:#666;font-style:italic}
         .dm-dns-info code{background:#f0f0f1;padding:3px 6px;border-radius:3px;font-family:Consolas,Monaco,monospace;font-size:12px}
         .dm-activity-list{margin:0;padding:0;list-style:none}
         .dm-activity-list li{padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:12px}
         .dm-activity-list li:last-child{border-bottom:none;padding-bottom:0}
         .dm-activity-action{display:inline-block;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:600;margin-right:5px}
         .dm-action-add{background-color:#dff0d8;color:#3c763d}
         .dm-action-edit{background-color:#d9edf7;color:#31708f}
         .dm-action-delete{background-color:#f2dede;color:#a94442}
         .dm-activity-domain{font-weight:500;color:#1d2327}
         .dm-activity-meta{display:block;color:#666;margin-top:2px}
         .dm-quick-actions{display:flex;gap:10px}
         .dm-quick-actions .button{flex:1;display:flex;align-items:center;justify-content:center;gap:5px}
         .dm-quick-actions .dashicons{font-size:16px;width:16px;height:16px;line-height:1}
         .dm-loading{opacity:.6;pointer-events:none}
         .dm-loading::after{content:'';position:absolute;top:50%;left:50%;width:20px;height:20px;margin:-10px 0 0 -10px;border:2px solid #f0f0f1;border-top-color:#2271b1;border-radius:50%;animation:dm-spin 1s linear infinite}
         @keyframes dm-spin{to{transform:rotate(360deg)}
         }
         @media screen and (max-width:782px){.dm-stats-grid{grid-template-columns:repeat(3,1fr);gap:5px}
         .dm-stat{padding:8px 5px}
         .dm-stat-value{font-size:20px}
         .dm-quick-actions{flex-direction:column}
         .dm-quick-actions .button{width:100%}
         }
         #dm_domain_status_widget .postbox-header{cursor:move}
         #dm_domain_status_widget.closed .inside{display:none}
         </style>

         <div class="dm-dashboard-widget">
             <!-- 1. 主域名状态 -->
             <div class="dm-primary-domain">
                 <h4><?php _e( 'Primary Domain', 'wp-domain-mapping' ); ?></h4>
                 <?php
                 $primary = wp_list_filter( $domains, array( 'active' => 1 ) );
                 if ( ! empty( $primary ) ) {
                     $primary_domain = reset( $primary );
                     $ssl_status = $this->check_domain_ssl( $primary_domain->domain );
                     ?>
                     <p class="dm-domain-primary">
                         <span class="dashicons dashicons-admin-site-alt3"></span>
                         <strong>
                             <a href="<?php echo esc_url( ( $ssl_status ? 'https://' : 'http://' ) . $primary_domain->domain ); ?>" target="_blank">
                                 <?php echo esc_html( $primary_domain->domain ); ?>
                             </a>
                         </strong>
                         <?php if ( $ssl_status ): ?>
                             <span class="dashicons dashicons-lock" style="color: #46b450;" title="<?php esc_attr_e( 'SSL Active', 'wp-domain-mapping' ); ?>"></span>
                         <?php else: ?>
                             <span class="dashicons dashicons-unlock" style="color: #d63638;" title="<?php esc_attr_e( 'No SSL', 'wp-domain-mapping' ); ?>"></span>
                         <?php endif; ?>
                     </p>
                     <?php
                 } else {
                     $orig_url = WP_Domain_Mapping_Core::get_instance()->get_original_url( 'siteurl' );
                     $orig_domain = parse_url( $orig_url, PHP_URL_HOST );
                     ?>
                     <p class="dm-no-primary">
                         <span class="dashicons dashicons-admin-site"></span>
                         <?php _e( 'Using original domain:', 'wp-domain-mapping' ); ?>
                         <strong><?php echo esc_html( $orig_domain ); ?></strong>
                     </p>
                     <?php
                 }
                 ?>
             </div>

             <!-- 2. 映射域名统计 -->
             <div class="dm-domains-stats">
                 <h4><?php _e( 'Domain Statistics', 'wp-domain-mapping' ); ?></h4>
                 <div class="dm-stats-grid">
                     <div class="dm-stat">
                         <span class="dm-stat-value"><?php echo count( $domains ); ?></span>
                         <span class="dm-stat-label"><?php _e( 'Total Domains', 'wp-domain-mapping' ); ?></span>
                     </div>
                     <div class="dm-stat">
                         <span class="dm-stat-value"><?php echo count( wp_list_filter( $domains, array( 'active' => 1 ) ) ); ?></span>
                         <span class="dm-stat-label"><?php _e( 'Primary', 'wp-domain-mapping' ); ?></span>
                     </div>
                     <div class="dm-stat">
                         <span class="dm-stat-value"><?php echo count( wp_list_filter( $domains, array( 'active' => 0 ) ) ); ?></span>
                         <span class="dm-stat-label"><?php _e( 'Secondary', 'wp-domain-mapping' ); ?></span>
                     </div>
                 </div>
             </div>

             <!-- 3. 映射域名列表 -->
             <?php if ( ! empty( $domains ) && count( $domains ) > 1 ): ?>
             <div class="dm-domains-list">
                 <h4><?php _e( 'All Mapped Domains', 'wp-domain-mapping' ); ?></h4>
                 <ul>
                     <?php foreach ( $domains as $domain ): ?>
                         <li>
                             <span class="dashicons dashicons-admin-links"></span>
                             <a href="<?php echo esc_url( 'http://' . $domain->domain ); ?>" target="_blank">
                                 <?php echo esc_html( $domain->domain ); ?>
                             </a>
                             <?php if ( $domain->active ): ?>
                                 <span class="dm-badge-primary"><?php _e( 'Primary', 'wp-domain-mapping' ); ?></span>
                             <?php endif; ?>
                         </li>
                     <?php endforeach; ?>
                 </ul>
             </div>
             <?php endif; ?>

             <!-- 4. 健康状态（可选） -->
             <?php if ( $show_health_status == '1' ): ?>
             <div class="dm-health-status" id="dm-widget-health-status">
                 <h4>
                     <?php _e( 'Health Status', 'wp-domain-mapping' ); ?>
                     <button type="button" class="button-link dm-refresh-health" data-blog-id="<?php echo $blog_id; ?>">
                         <span class="dashicons dashicons-update"></span>
                         <span class="screen-reader-text"><?php _e( 'Refresh', 'wp-domain-mapping' ); ?></span>
                     </button>
                 </h4>
                 <div class="dm-health-content">
                     <?php $this->render_widget_health_status( $domains ); ?>
                 </div>
             </div>
             <?php endif; ?>

             <!-- 5. DNS 配置提示 -->
             <?php
             $ipaddress = get_site_option( 'dm_ipaddress' );
             $cname = get_site_option( 'dm_cname' );
             if ( ( $ipaddress || $cname ) && empty( $domains ) ):
             ?>
             <div class="dm-dns-info">
                 <h4><?php _e( 'DNS Configuration', 'wp-domain-mapping' ); ?></h4>
                 <p class="description">
                     <?php if ( $cname ): ?>
                         <?php printf( __( 'Point your domain CNAME to: %s', 'wp-domain-mapping' ), '<code>' . esc_html( $cname ) . '</code>' ); ?>
                     <?php elseif ( $ipaddress ): ?>
                         <?php printf( __( 'Point your domain A record to: %s', 'wp-domain-mapping' ), '<code>' . esc_html( $ipaddress ) . '</code>' ); ?>
                     <?php endif; ?>
                 </p>
             </div>
             <?php endif; ?>

             <!-- 6. 最近活动 -->
             <?php
             $recent_logs = $wpdb->get_results( $wpdb->prepare(
                 "SELECT * FROM {$this->tables['logs']} WHERE blog_id = %d ORDER BY timestamp DESC LIMIT 3",
                 $blog_id
             ));
             if ( ! empty( $recent_logs ) ):
             ?>
             <div class="dm-recent-activity">
                 <h4><?php _e( 'Recent Activity', 'wp-domain-mapping' ); ?></h4>
                 <ul class="dm-activity-list">
                     <?php foreach ( $recent_logs as $log ):
                         $user = get_userdata( $log->user_id );
                         $username = $user ? $user->display_name : __( 'Unknown', 'wp-domain-mapping' );
                     ?>
                         <li>
                             <span class="dm-activity-action dm-action-<?php echo esc_attr( $log->action ); ?>">
                                 <?php echo esc_html( dm_format_action_name( $log->action ) ); ?>
                             </span>
                             <span class="dm-activity-domain"><?php echo esc_html( $log->domain ); ?></span>
                             <span class="dm-activity-meta">
                                 <?php
                                 printf(
                                     __( 'by %s %s ago', 'wp-domain-mapping' ),
                                     esc_html( $username ),
                                     human_time_diff( strtotime( $log->timestamp ), current_time( 'timestamp' ) )
                                 );
                                 ?>
                             </span>
                         </li>
                     <?php endforeach; ?>
                 </ul>
             </div>
             <?php endif; ?>

             <!-- 7. 快速操作 -->
             <div class="dm-quick-actions">
                 <a href="<?php echo admin_url( 'tools.php?page=domainmapping' ); ?>" class="button button-primary">
                     <?php _e( 'Manage Domains', 'wp-domain-mapping' ); ?>
                 </a>
                 <?php if ( ! empty( $domains ) ): ?>
                 <button type="button" class="button dm-check-all-health" data-blog-id="<?php echo $blog_id; ?>">
                     <?php _e( 'Check Health', 'wp-domain-mapping' ); ?>
                 </button>
                 <?php endif; ?>
             </div>
         </div>

         <script type="text/javascript">
         (function($) {
             'use strict';

             // 等待小工具完全渲染
             $(document).ready(function() {
                 console.log('DM Widget: Document ready');

                 // 初始化变量
                 var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
                 var nonce = '<?php echo esc_js( wp_create_nonce( 'domain_mapping' ) ); ?>';

                 // 检查元素是否存在
                 console.log('Refresh button count:', $('.dm-refresh-health').length);
                 console.log('Check all button count:', $('.dm-check-all-health').length);

                 // 刷新健康状态
                 $('#dm_domain_status_widget').on('click', '.dm-refresh-health', function(e) {
                     e.preventDefault();
                     e.stopPropagation();

                     console.log('Refresh health clicked');

                     var $button = $(this);
                     var $healthContent = $('.dm-health-content');
                     var blogId = $button.data('blog-id');

                     // 禁用按钮
                     $button.prop('disabled', true).blur();
                     $button.find('.dashicons').addClass('dashicons-update-spin');
                     $healthContent.addClass('dm-loading');

                     $.ajax({
                         url: ajaxUrl,
                         type: 'POST',
                         dataType: 'json',
                         data: {
                             action: 'dm_quick_health_check',
                             blog_id: blogId,
                             nonce: nonce
                         },
                         success: function(response) {
                             console.log('Health check response:', response);

                             if (response.success) {
                                 $healthContent.html(response.data.html);

                                 // 显示成功消息
                                 var $widget = $('#dm_domain_status_widget');
                                 var $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                               response.data.message + '</p></div>');

                                 $widget.find('.inside').prepend($notice);

                                 setTimeout(function() {
                                     $notice.fadeOut(function() {
                                         $(this).remove();
                                     });
                                 }, 3000);
                             } else {
                                 alert(response.data || 'An error occurred');
                             }
                         },
                         error: function(xhr, status, error) {
                             console.error('AJAX error:', status, error);
                             alert('An error occurred during the health check');
                         },
                         complete: function() {
                             $button.prop('disabled', false);
                             $button.find('.dashicons').removeClass('dashicons-update-spin');
                             $healthContent.removeClass('dm-loading');
                         }
                     });
                 });

                 // 检查所有域名健康状态
                 $('#dm_domain_status_widget').on('click', '.dm-check-all-health', function(e) {
                     e.preventDefault();
                     e.stopPropagation();

                     console.log('Check all health clicked');

                     var $button = $(this);
                     var blogId = $button.data('blog-id');
                     var originalHtml = $button.html();

                     // 禁用按钮
                     $button.prop('disabled', true).blur();
                     $button.html('<span class="dashicons dashicons-update dashicons-update-spin"></span> Processing...');

                     $.ajax({
                         url: ajaxUrl,
                         type: 'POST',
                         dataType: 'json',
                         data: {
                             action: 'dm_quick_health_check',
                             blog_id: blogId,
                             nonce: nonce
                         },
                         success: function(response) {
                             console.log('Check all response:', response);

                             if (response.success) {
                                 // 更新健康状态区域
                                 var $healthSection = $('#dm-widget-health-status');
                                 if ($healthSection.length) {
                                     $healthSection.find('.dm-health-content').html(response.data.html);

                                     if (!$healthSection.is(':visible')) {
                                         $healthSection.slideDown();
                                     }
                                 }

                                 // 显示成功消息
                                 var $widget = $('#dm_domain_status_widget');
                                 var $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                               response.data.message + '</p></div>');

                                 $widget.find('.inside').prepend($notice);

                                 setTimeout(function() {
                                     $notice.fadeOut(function() {
                                         $(this).remove();
                                     });
                                 }, 3000);
                             } else {
                                 alert(response.data || wpDomainMapping.messages.error);
                             }
                         },
                         error: function(xhr, status, error) {
                             console.error('AJAX error:', status, error);
                             alert(wpDomainMapping.messages.error);
                         },
                         complete: function() {
                             $button.prop('disabled', false);
                             $button.html(originalHtml);
                         }
                     });
                 });
             });
         })(jQuery);
         </script>
         <?php
    }

    /**
     * 渲染小工具配置
     */
    public function render_dashboard_widget_config() {
        // 处理表单提交 - WordPress 会在保存前调用这个方法
        if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget_id'] ) && $_POST['widget_id'] == 'dm_domain_status_widget' ) {
            // 重要：checkbox 未勾选时不会在 $_POST 中出现
            $show_health = isset( $_POST['dm_widget_show_health'] ) ? '1' : '0';
            update_user_meta( get_current_user_id(), 'dm_widget_show_health', $show_health );
        }

        // 获取当前设置
        $show_health = get_user_meta( get_current_user_id(), 'dm_widget_show_health', true );
        $show_health = ( $show_health === '' ) ? '1' : $show_health; // 默认显示
        ?>
        <p>
            <label for="dm_widget_show_health">
                <input type="checkbox" id="dm_widget_show_health" name="dm_widget_show_health" value="1" <?php checked( $show_health, '1' ); ?> />
                <?php _e( 'Show health status', 'wp-domain-mapping' ); ?>
            </label>
        </p>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 提供实时预览
            $('#dm_widget_show_health').on('change', function() {
                var $healthSection = $('#dm-widget-health-status');
                if ($(this).is(':checked')) {
                    $healthSection.slideDown();
                } else {
                    $healthSection.slideUp();
                }
            });
        });
        </script>
        <?php
    }
    /**
     * 渲染健康状态内容
     */
    private function render_widget_health_status( $domains ) {
        if ( empty( $domains ) ) {
            echo '<p class="dm-no-domains">' . __( 'No domains to check.', 'wp-domain-mapping' ) . '</p>';
            return;
        }

        $all_healthy = true;
        $issues = array();

        foreach ( $domains as $domain ) {
            $health = dm_get_health_result( $domain->domain );
            if ( $health ) {
                $domain_issues = array();

                if ( isset( $health['accessible'] ) && ! $health['accessible'] ) {
                    $domain_issues[] = __( 'Not accessible', 'wp-domain-mapping' );
                    $all_healthy = false;
                }

                if ( isset( $health['ssl_valid'] ) && ! $health['ssl_valid'] ) {
                    $domain_issues[] = __( 'SSL issue', 'wp-domain-mapping' );
                    $all_healthy = false;
                }

                if ( isset( $health['dns_status'] ) && $health['dns_status'] !== 'success' ) {
                    $domain_issues[] = __( 'DNS issue', 'wp-domain-mapping' );
                    $all_healthy = false;
                }

                if ( ! empty( $domain_issues ) ) {
                    $issues[$domain->domain] = $domain_issues;
                }
            }
        }

        if ( $all_healthy ) {
            echo '<p class="dm-health-good"><span class="dashicons dashicons-yes-alt"></span> ' .
                 __( 'All domains are healthy!', 'wp-domain-mapping' ) . '</p>';
        } else {
            echo '<div class="dm-health-issues">';
            foreach ( $issues as $domain => $domain_issues ) {
                echo '<div class="dm-issue-item">';
                echo '<strong>' . esc_html( $domain ) . ':</strong> ';
                echo '<span class="dm-issue-list">' . implode( ', ', $domain_issues ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }

        // 显示最后检查时间
        $last_check = get_site_option( 'dm_widget_last_health_check_' . get_current_blog_id() );
        if ( $last_check ) {
            echo '<p class="dm-last-check">' .
                 sprintf(
                     __( 'Last checked: %s ago', 'wp-domain-mapping' ),
                     human_time_diff( $last_check, current_time( 'timestamp' ) )
                 ) . '</p>';
        }
    }

    /**
     * AJAX刷新小工具状态
     */
    public function ajax_refresh_widget_status() {
        check_ajax_referer( 'domain_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-domain-mapping' ) );
        }

        ob_start();
        $this->render_dashboard_widget();
        $html = ob_get_clean();

        wp_send_json_success( $html );
    }

    /**
     * AJAX快速健康检查
     */
    public function ajax_quick_health_check() {
        check_ajax_referer( 'domain_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-domain-mapping' ) );
        }

        $blog_id = isset( $_POST['blog_id'] ) ? intval( $_POST['blog_id'] ) : get_current_blog_id();
        $domains = dm_get_domains_by_blog_id( $blog_id );

        if ( empty( $domains ) ) {
            wp_send_json_error( __( 'No domains found.', 'wp-domain-mapping' ) );
        }

        // 执行健康检查
        $tools = WP_Domain_Mapping_Tools::get_instance();
        foreach ( $domains as $domain ) {
            $result = $tools->check_domain_health( $domain->domain );
            dm_save_health_result( $domain->domain, $result );
        }

        // 更新最后检查时间
        update_site_option( 'dm_widget_last_health_check_' . $blog_id, current_time( 'timestamp' ) );

        // 返回更新后的健康状态HTML
        ob_start();
        $this->render_widget_health_status( $domains );
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html' => $html,
            'message' => __( 'Health check completed.', 'wp-domain-mapping' )
        ));
    }

    /**
     * 检查域名SSL状态
     */
    private function check_domain_ssl( $domain ) {
        $health_result = dm_get_health_result( $domain );
        return $health_result && isset( $health_result['ssl_valid'] ) && $health_result['ssl_valid'];
    }

    /**
     * 获取域名健康问题
     */
    private function get_domain_health_issues( $blog_id ) {
        $domains = dm_get_domains_by_blog_id( $blog_id );
        $issues = array();

        foreach ( $domains as $domain ) {
            $health_result = dm_get_health_result( $domain->domain );
            if ( $health_result ) {
                if ( ! $health_result['accessible'] || ! $health_result['ssl_valid'] || $health_result['dns_status'] !== 'success' ) {
                    $issues[] = $domain->domain;
                }
            }
        }

        return $issues;
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
                'invalidDomain' => __( 'Invalid domain format.', 'wp-domain-mapping' ),
                'checking' => __( 'Checking...', 'wp-domain-mapping' ), // 新增
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
     * AJAX handler for domain actions - IMPROVED SECURITY
     */
    public function ajax_handle_actions() {
        // Verify nonce first
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'domain_mapping' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'wp-domain-mapping' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-domain-mapping' ) );
        }

        global $wpdb;

        // Sanitize and validate inputs
        $action = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : '';
        $domain = isset( $_POST['domain'] ) ? dm_clean_domain( sanitize_text_field( $_POST['domain'] ) ) : '';
        $blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
        $active = isset( $_POST['active'] ) ? absint( $_POST['active'] ) : 0;
        $orig_domain = isset( $_POST['orig_domain'] ) ? dm_clean_domain( sanitize_text_field( $_POST['orig_domain'] ) ) : '';
        $current_user_id = get_current_user_id();

        // Validate action type
        $allowed_actions = array( 'save', 'delete' );
        if ( ! in_array( $action, $allowed_actions ) ) {
            wp_send_json_error( __( 'Invalid action.', 'wp-domain-mapping' ) );
        }

        switch ( $action ) {
            case 'save':
                // Enhanced validation for save action
                if ( $blog_id <= 0 || $blog_id === 1 ) {
                    wp_send_json_error( __( 'Invalid site ID.', 'wp-domain-mapping' ) );
                }

                // Validate domain format
                if ( empty( $domain ) || ! dm_validate_domain( $domain ) ) {
                    wp_send_json_error( __( 'Invalid domain format.', 'wp-domain-mapping' ) );
                }

                // Check if blog exists
                if ( ! get_blog_details( $blog_id ) ) {
                    wp_send_json_error( __( 'Site does not exist.', 'wp-domain-mapping' ) );
                }

                // For editing, check if domain changed
                $domain_changed = ! empty( $orig_domain ) && $orig_domain !== $domain;

                if ( $domain_changed || empty( $orig_domain ) ) {
                    // Check if domain exists for another blog
                    $exists = dm_domain_exists_for_another_blog( $domain, $blog_id );

                    if ( $exists ) {
                        wp_send_json_error( sprintf(
                            __( 'Domain %s is already mapped to site ID %d.', 'wp-domain-mapping' ),
                            esc_html( $domain ),
                            intval( $exists->blog_id )
                        ));
                    }
                }

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
                            // If setting as primary, reset other domains
                            if ( $active ) {
                                $wpdb->update(
                                    $this->tables['domains'],
                                    array( 'active' => 0 ),
                                    array(
                                        'blog_id' => $blog_id,
                                        'domain' => array( 'NOT LIKE', $domain )
                                    ),
                                    array( '%d' ),
                                    array( '%d' )
                                );
                            }

                            // Log the action
                            dm_log_action( 'add', $domain, $blog_id, $current_user_id );

                            $wpdb->query( 'COMMIT' );
                            wp_send_json_success( __( 'Domain added successfully.', 'wp-domain-mapping' ) );
                        } else {
                            $wpdb->query( 'ROLLBACK' );
                            wp_send_json_error( __( 'Failed to add domain.', 'wp-domain-mapping' ) );
                        }
                    } else {
                        // Validate original domain exists
                        $orig_exists = dm_get_domain_by_name( $orig_domain );
                        if ( ! $orig_exists || $orig_exists->blog_id != $blog_id ) {
                            $wpdb->query( 'ROLLBACK' );
                            wp_send_json_error( __( 'Original domain not found or access denied.', 'wp-domain-mapping' ) );
                        }

                        // Update existing domain
                        $update_data = array(
                            'blog_id' => $blog_id,
                            'active' => $active
                        );
                        $update_format = array( '%d', '%d' );

                        // If domain changed, update it
                        if ( $domain_changed ) {
                            $update_data['domain'] = $domain;
                            $update_format[] = '%s';
                        }

                        // If setting as primary, reset other domains first
                        if ( $active ) {
                            $wpdb->update(
                                $this->tables['domains'],
                                array( 'active' => 0 ),
                                array( 'blog_id' => $blog_id ),
                                array( '%d' ),
                                array( '%d' )
                            );
                        }

                        $success = $wpdb->update(
                            $this->tables['domains'],
                            $update_data,
                            array( 'domain' => $orig_domain ),
                            $update_format,
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
                    error_log( 'Domain mapping error: ' . $e->getMessage() );
                    wp_send_json_error( __( 'An error occurred while saving domain.', 'wp-domain-mapping' ) );
                }
                break;

            case 'delete':
                $domains = isset( $_POST['domains'] ) ? array_map( 'sanitize_text_field', (array) $_POST['domains'] ) : array( $domain );

                // Validate domains array
                $domains = array_filter( $domains, function( $d ) {
                    return ! empty( $d ) && dm_validate_domain( $d );
                });

                if ( empty( $domains ) ) {
                    wp_send_json_error( __( 'No valid domains provided for deletion.', 'wp-domain-mapping' ) );
                }

                $wpdb->query( 'START TRANSACTION' );
                $deleted = 0;

                try {
                    foreach ( $domains as $del_domain ) {
                        // Get domain info before deletion for logging and validation
                        $domain_info = dm_get_domain_by_name( $del_domain );

                        if ( ! $domain_info ) {
                            continue; // Skip non-existent domains
                        }

                        // Check if user has permission to delete this domain
                        if ( ! current_user_can( 'manage_network' ) ) {
                            continue; // Skip if no permission
                        }

                        // Delete the domain
                        $result = $wpdb->delete(
                            $this->tables['domains'],
                            array( 'domain' => $del_domain ),
                            array( '%s' )
                        );

                        if ( $result ) {
                            $deleted++;
                            // Log the action
                            dm_log_action( 'delete', $del_domain, $domain_info->blog_id, $current_user_id );
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
                    error_log( 'Domain mapping deletion error: ' . $e->getMessage() );
                    wp_send_json_error( __( 'An error occurred while deleting domains.', 'wp-domain-mapping' ) );
                }
                break;

            default:
                wp_send_json_error( __( 'Invalid action.', 'wp-domain-mapping' ) );
        }
    }

    /**
     * Handle user domain mapping actions
     * UPDATED: Support www prefix and better domain validation
     */
    public function handle_user_domain_actions() {
        global $wpdb, $parent_file;

        $url = add_query_arg( array( 'page' => 'domainmapping' ), admin_url( $parent_file ) );

        // 处理 POST 请求（添加域名）
        if ( ! empty( $_POST['action'] ) ) {
            $domain = isset( $_POST['domain'] ) ? dm_clean_domain( sanitize_text_field( $_POST['domain'] ) ) : '';

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

                    // Check if domain already exists for any blog
                    $domain_exists = dm_get_domain_by_name( $domain );

                    // Also check if it exists in the blogs table
                    $blog_exists = $wpdb->get_row( $wpdb->prepare(
                        "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s",
                        $domain
                    ));

                    if ( $domain_exists || $blog_exists ) {
                        // Check if it's for the current blog
                        if ( $domain_exists && $domain_exists->blog_id == $wpdb->blogid ) {
                            wp_redirect( add_query_arg( array( 'updated' => 'exists' ), $url ) );
                            exit;
                        } else {
                            wp_die( sprintf(
                                esc_html__( "Domain '%s' is already mapped to another site.", 'wp-domain-mapping' ),
                                esc_html( $domain )
                            ));
                        }
                    }

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

                    // 记录日志
                    dm_log_action( 'add', $domain, $wpdb->blogid );

                    // 清除缓存
                    dm_clear_domain_cache( $wpdb->blogid );

                    wp_redirect( add_query_arg( array( 'updated' => 'add' ), $url ) );
                    exit;
                    break;
            }
        }
        // 处理 GET 请求（删除和设为主域名）
        elseif ( isset( $_GET['action'] ) ) {
            $action = sanitize_text_field( $_GET['action'] );
            $domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

            if ( empty( $domain ) ) {
                wp_die( esc_html__( "You must enter a domain", 'wp-domain-mapping' ) );
            }

            switch ( $action ) {
                case 'primary':
                    check_admin_referer( 'domain_mapping' );

                    do_action( 'dm_handle_actions_primary', $domain );

                    // Verify domain belongs to current blog
                    $domain_info = dm_get_domain_by_name( $domain );
                    if ( ! $domain_info || $domain_info->blog_id != $wpdb->blogid ) {
                        wp_die( esc_html__( "Domain not found or does not belong to this site.", 'wp-domain-mapping' ) );
                    }

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

                    // 记录日志
                    dm_log_action( 'edit', $domain, $wpdb->blogid );

                    // 清除缓存
                    dm_clear_domain_cache( $wpdb->blogid );

                    wp_redirect( add_query_arg( array( 'updated' => 'primary' ), $url ) );
                    exit;
                    break;

                case 'delete':
                    check_admin_referer( "delete" . $domain );

                    do_action( 'dm_handle_actions_del', $domain );

                    // Verify domain belongs to current blog
                    $domain_info = dm_get_domain_by_name( $domain );
                    if ( ! $domain_info || $domain_info->blog_id != $wpdb->blogid ) {
                        wp_die( esc_html__( "Domain not found or does not belong to this site.", 'wp-domain-mapping' ) );
                    }

                    // Don't allow deleting primary domain
                    if ( $domain_info->active == 1 ) {
                        wp_die( esc_html__( "Cannot delete the primary domain. Please set another domain as primary first.", 'wp-domain-mapping' ) );
                    }

                    // Delete the domain
                    $wpdb->delete(
                        $this->tables['domains'],
                        array(
                            'domain' => $domain,
                            'blog_id' => $wpdb->blogid
                        ),
                        array( '%s', '%d' )
                    );

                    // 记录日志
                    dm_log_action( 'delete', $domain, $wpdb->blogid );

                    // 清除缓存
                    dm_clear_domain_cache( $wpdb->blogid );

                    wp_redirect( add_query_arg( array( 'updated' => 'del' ), $url ) );
                    exit;
                    break;
            }
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
                    __( "Please set the IP address or CNAME of your server in the <a href='wp-admin/network/settings.php?page=domain-mapping'>site admin page</a>.", 'wp-domain-mapping' ),
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
        $columns['map'] = __( 'Mapping', 'wp-domain-mapping' );
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
    public static function echo_default_updated_msg() {
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
