<?php
/**
 * 站点ID显示功能
 *
 * 显示当前WordPress多站点的站点ID
 *
 * @package WP Domain Mapping
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress站点ID显示类
 *
 * 基于WP Show Site ID插件 (by Emanuela Castorina)
 */
class WP_Domain_Mapping_Site_ID {

    /**
     * 类实例
     *
     * @var WP_Domain_Mapping_Site_ID
     */
    protected static $instance;

    /**
     * 获取类实例
     *
     * @return WP_Domain_Mapping_Site_ID
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
        // 只在多站点环境中工作
        if (!is_multisite()) {
            return;
        }

        // 添加站点ID到WordPress工具栏
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);

        // 添加站点ID列到网络管理员的站点列表页面
        add_filter('manage_sites-network_columns', array($this, 'manage_sites_columns'), 20);
        add_action('manage_sites_custom_column', array($this, 'show_site_id'), 10, 2);
        add_action('admin_print_styles', array($this, 'custom_style'));
    }

    /**
     * 添加自定义样式
     */
    public function custom_style() {
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

    /**
     * 向WordPress工具栏添加站点ID
     *
     * @param WP_Admin_Bar $admin_bar WordPress工具栏对象
     */
    public function add_toolbar_items($admin_bar) {
        // 仅向管理员或网络管理员显示
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

    /**
     * 向站点列表添加ID列
     *
     * @param array $columns 当前列
     * @return array 修改后的列
     */
    public function manage_sites_columns($columns) {
        // 在第一列后添加站点ID列
        $columns = array_slice($columns, 0, 1, true) +
                  array('dm_site_id' => __('Site ID', 'wp-domain-mapping')) +
                  array_slice($columns, 1, count($columns) - 1, true);

        return $columns;
    }

    /**
     * 显示站点ID列的内容
     *
     * @param string $column 列名
     * @param int $blog_id 站点ID
     */
    public function show_site_id($column, $blog_id) {
        if ('dm_site_id' == $column) {
            echo '<span class="dm-site-id-badge">' . esc_html($blog_id) . '</span>';
        }
    }
}
