<?php
/**
 * Domains administration page template
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// This file is included from the render_domains_admin method
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

    <div class="card domain-mapping-card">
        <h2><?php echo $edit_row ? esc_html__( 'Edit Domain', 'wp-domain-mapping' ) : esc_html__( 'Add New Domain', 'wp-domain-mapping' ); ?></h2>
        <div id="edit-domain-status" class="notice" style="display:none;"></div>

        <form id="edit-domain-form" method="POST">
            <input type="hidden" name="orig_domain" value="<?php echo esc_attr( $edit_row ? $edit_row->domain : '' ); ?>" />
            <table class="form-table">
                <tr>
                    <th><label for="blog_id"><?php esc_html_e( 'Site ID', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input type="number" id="blog_id" name="blog_id" value="<?php echo esc_attr( $edit_row ? $edit_row->blog_id : '' ); ?>" class="regular-text" required />
                        <?php if ( ! $edit_row ) : ?>
                            <p class="description">
                                <?php
                                $site_list_url = network_admin_url( 'sites.php' );
                                printf(
                                    /* translators: %s: URL to sites list */
                                    wp_kses(
                                        __( 'Not sure about Site ID? <a href="%s" target="_blank">View all sites</a> to find the ID.', 'wp-domain-mapping' ),
                                        array( 'a' => array( 'href' => array(), 'target' => array() ) )
                                    ),
                                    esc_url( $site_list_url )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="domain"><?php esc_html_e( 'Domain', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input type="text" id="domain" name="domain" value="<?php echo esc_attr( $edit_row ? $edit_row->domain : '' ); ?>" class="regular-text" required
                               placeholder="example.com" pattern="^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$" />
                        <p class="description">
                            <?php esc_html_e( 'Enter the domain without http:// or https:// (e.g., example.com)', 'wp-domain-mapping' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="active"><?php esc_html_e( 'Primary', 'wp-domain-mapping' ); ?></label></th>
                    <td>
                        <input type="checkbox" id="active" name="active" value="1" <?php checked( $edit_row ? $edit_row->active : 0, 1 ); ?> />
                        <span class="description">
                            <?php esc_html_e( 'Set as the primary domain for this site', 'wp-domain-mapping' ); ?>
                        </span>
                    </td>
                </tr>
                <?php if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) : ?>
                    <tr>
                        <td colspan="2" class="notice notice-warning">
                            <p><?php esc_html_e( 'Warning! Primary domains are currently disabled in network settings.', 'wp-domain-mapping' ); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?php echo $edit_row
                    ? esc_attr__( 'Update Domain', 'wp-domain-mapping' )
                    : esc_attr__( 'Add Domain', 'wp-domain-mapping' ); ?>" />

                <?php if ( $edit_row ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'network/sites.php?page=domains' ) ); ?>" class="button button-secondary">
                        <?php esc_html_e( 'Cancel', 'wp-domain-mapping' ); ?>
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <div class="card domain-mapping-card">
        <h2><?php esc_html_e( 'Search & Filter Domains', 'wp-domain-mapping' ); ?></h2>
        <form method="GET" id="domain-filter-form">
            <input type="hidden" name="page" value="domains" />
            <div class="search-form">
                <div class="search-form-field">
                    <label for="domain"><?php esc_html_e( 'Domain:', 'wp-domain-mapping' ); ?></label>
                    <input type="text" id="domain" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" class="regular-text" placeholder="example.com" />
                </div>

                <div class="search-form-field">
                    <label for="blog_id"><?php esc_html_e( 'Site ID:', 'wp-domain-mapping' ); ?></label>
                    <input type="number" id="blog_id" name="blog_id" value="<?php echo esc_attr( isset( $_GET['blog_id'] ) ? $_GET['blog_id'] : '' ); ?>" class="small-text" min="1" />
                </div>

                <div class="search-form-field">
                    <label for="active"><?php esc_html_e( 'Primary:', 'wp-domain-mapping' ); ?></label>
                    <select id="active" name="active">
                        <option value=""><?php esc_html_e( 'All', 'wp-domain-mapping' ); ?></option>
                        <option value="1" <?php selected( isset( $_GET['active'] ) && $_GET['active'] == '1' ); ?>>
                            <?php esc_html_e( 'Yes', 'wp-domain-mapping' ); ?>
                        </option>
                        <option value="0" <?php selected( isset( $_GET['active'] ) && $_GET['active'] == '0' ); ?>>
                            <?php esc_html_e( 'No', 'wp-domain-mapping' ); ?>
                        </option>
                    </select>
                </div>

                <div class="search-form-submit">
                    <input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Filter', 'wp-domain-mapping' ); ?>" />
                    <?php if ( isset( $_GET['s'] ) || isset( $_GET['blog_id'] ) || isset( $_GET['active'] ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'network/sites.php?page=domains' ) ); ?>" class="button button-link">
                            <?php esc_html_e( 'Clear', 'wp-domain-mapping' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="card domain-mapping-card">
        <div class="domain-mapping-tabs">
            <button type="button" class="domain-mapping-tab active" data-tab="manage-domains">
                <?php esc_html_e( 'Manage Domains', 'wp-domain-mapping' ); ?>
            </button>
            <button type="button" class="domain-mapping-tab" data-tab="domain-logs">
                <?php esc_html_e( 'Domain Logs', 'wp-domain-mapping' ); ?>
            </button>
        </div>

        <div class="domain-mapping-content">
            <div class="domain-mapping-section" data-section="manage-domains">
                <div id="domain-status" class="notice" style="display:none;"></div>
                <form id="domain-list-form" method="POST">
                    <?php
                    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
                    $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
                    $offset = ( $paged - 1 ) * $per_page;

                    // Build the WHERE clause for filtering
                    $where = array();
                    if ( ! empty( $_GET['s'] ) ) {
                        $where[] = $wpdb->prepare(
                            "domain LIKE %s",
                            '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%'
                        );
                    }

                    if ( ! empty( $_GET['blog_id'] ) ) {
                        $where[] = $wpdb->prepare(
                            "blog_id = %d",
                            absint( $_GET['blog_id'] )
                        );
                    }

                    if ( isset( $_GET['active'] ) && $_GET['active'] !== '' ) {
                        $where[] = $wpdb->prepare(
                            "active = %d",
                            absint( $_GET['active'] )
                        );
                    }

                    $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';
                    $tables = dm_get_table_names();

                    // Count total items for pagination
                    $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['domains']}" . $where_sql );
                    $total_pages = ceil( $total_items / $per_page );

                    // Get the domains with pagination
                    $rows = $wpdb->get_results( $wpdb->prepare(
                        "SELECT * FROM {$tables['domains']}" . $where_sql . " ORDER BY id DESC LIMIT %d, %d",
                        $offset,
                        $per_page
                    ));

                    // Display the domains table
                    dm_domain_listing( $rows );

                    // Pagination
                    if ( $total_pages > 1 ) :
                    ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => __( '&laquo;' ),
                                'next_text' => __( '&raquo;' ),
                                'total'     => $total_pages,
                                'current'   => $paged,
                                'mid_size'  => 2,
                                'end_size'  => 1,
                            ) );
                            ?>
                            <span class="displaying-num">
                                <?php
                                printf(
                                    /* translators: %s: number of items */
                                    _n( '%s item', '%s items', $total_items, 'wp-domain-mapping' ),
                                    number_format_i18n( $total_items )
                                );
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: IDN warning message */
                            __( '<strong>Note:</strong> %s', 'wp-domain-mapping' ),
                            dm_idn_warning()
                        );
                        ?>
                    </p>
                </form>
            </div>

            <div class="domain-mapping-section" data-section="domain-logs" style="display:none;">
                <?php dm_domain_logs(); ?>
            </div>
        </div>
    </div>

    <div class="card domain-mapping-card">
        <h2><?php esc_html_e( 'Domain Statistics', 'wp-domain-mapping' ); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Total Domains', 'wp-domain-mapping' ); ?></th>
                    <td><?php echo esc_html( $total_domains ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Primary Domains', 'wp-domain-mapping' ); ?></th>
                    <td><?php echo esc_html( $primary_domains ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Sites with Mapped Domains', 'wp-domain-mapping' ); ?></th>
                    <td>
                        <?php
                        $sites_with_domains = $wpdb->get_var( "SELECT COUNT(DISTINCT blog_id) FROM {$tables['domains']}" );
                        echo esc_html( $sites_with_domains );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
/**
 * Render domain listing table
 *
 * @param array $rows Domain data rows
 */
function dm_domain_listing( $rows ) {
    global $wpdb;

    if ( ! $rows ) {
        echo '<div class="notice notice-info"><p>' . esc_html__( 'No domains found.', 'wp-domain-mapping' ) . '</p></div>';
        return;
    }

    $edit_url = network_admin_url(
        file_exists( ABSPATH . 'wp-admin/network/site-info.php' )
            ? 'site-info.php'
            : ( file_exists( ABSPATH . 'wp-admin/ms-sites.php' ) ? 'ms-sites.php' : 'wpmu-blogs.php' )
    );
    ?>
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-domain-mapping' ); ?></label>
            <select id="bulk-action-selector-top" name="action">
                <option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp-domain-mapping' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete', 'wp-domain-mapping' ); ?></option>
            </select>
            <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'wp-domain-mapping' ); ?>" />
        </div>
    </div>

    <table class="wp-list-table widefat striped domains-table">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input id="select-all" type="checkbox" />
                </td>
                <th scope="col" class="column-site-id"><?php esc_html_e( 'Site ID', 'wp-domain-mapping' ); ?></th>
                <th scope="col" class="column-site-name"><?php esc_html_e( 'Site Name', 'wp-domain-mapping' ); ?></th>
                <th scope="col" class="column-domain"><?php esc_html_e( 'Domain', 'wp-domain-mapping' ); ?></th>
                <th scope="col" class="column-primary"><?php esc_html_e( 'Primary', 'wp-domain-mapping' ); ?></th>
                <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wp-domain-mapping' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) :
                $site_name = get_blog_option( $row->blog_id, 'blogname', esc_html__( 'Unknown', 'wp-domain-mapping' ) );
            ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" class="domain-checkbox" value="<?php echo esc_attr( $row->domain ); ?>" />
                    </th>
                    <td class="column-site-id">
                        <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'editblog', 'id' => $row->blog_id ), $edit_url ) ); ?>">
                            <?php echo esc_html( $row->blog_id ); ?>
                        </a>
                    </td>
                    <td class="column-site-name">
                        <?php echo esc_html( $site_name ); ?>
                    </td>
                    <td class="column-domain">
                        <a href="<?php echo esc_url( dm_ensure_protocol( $row->domain ) ); ?>" target="_blank">
                            <?php echo esc_html( $row->domain ); ?>
                            <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.3; opacity: 0.7;"></span>
                        </a>
                    </td>
                    <td class="column-primary">
                        <?php if ( $row->active == 1 ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Yes', 'wp-domain-mapping' ); ?></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'No', 'wp-domain-mapping' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-actions">
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo esc_url( add_query_arg( array( 'edit_domain' => $row->domain ), admin_url( 'network/sites.php?page=domains' ) ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'wp-domain-mapping' ); ?>
                                </a>
                            </span>
                            <?php if ( $row->active != 1 ) : ?>
                                <span class="delete">
                                    <a href="#" class="button button-small domain-delete-button" data-domain="<?php echo esc_attr( $row->domain ); ?>">
                                        <?php esc_html_e( 'Delete', 'wp-domain-mapping' ); ?>
                                    </a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) : ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e( 'Warning! Primary domains are currently disabled in network settings.', 'wp-domain-mapping' ); ?></p>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Render domain logs table
 */
function dm_domain_logs() {
    global $wpdb;

    $tables = dm_get_table_names();

    // Make sure the table exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tables['logs']}'" ) != $tables['logs'] ) {
        echo '<div class="notice notice-error"><p>' .
             esc_html__('Domain mapping logs table is missing. Please deactivate and reactivate the plugin.', 'wp-domain-mapping') .
             '</p></div>';
        return;
    }

    // Get pagination parameters
    $per_page = isset( $_GET['logs_per_page'] ) ? absint( $_GET['logs_per_page'] ) : 50;
    $paged = isset( $_GET['logs_paged'] ) ? absint( $_GET['logs_paged'] ) : 1;
    $offset = ( $paged - 1 ) * $per_page;

    // Get action filter
    $action_filter = isset( $_GET['log_action'] ) ? sanitize_text_field( $_GET['log_action'] ) : '';

    // Build WHERE clause for filtering
    $where = array();
    if ( ! empty( $action_filter ) ) {
        $where[] = $wpdb->prepare( "action = %s", $action_filter );
    }

    $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

    // Count total logs for pagination
    $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['logs']}" . $where_sql );
    $total_pages = ceil( $total_logs / $per_page );

    // Get logs with pagination
    $logs = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$tables['logs']}" . $where_sql . " ORDER BY timestamp DESC LIMIT %d, %d",
        $offset,
        $per_page
    ));

    // Get available actions for filter
    $actions = $wpdb->get_col( "SELECT DISTINCT action FROM {$tables['logs']}" );

    if ( ! $logs ) {
        echo '<div class="notice notice-info"><p>' . esc_html__( 'No domain mapping logs available.', 'wp-domain-mapping' ) . '</p></div>';
        return;
    }

    // Include the logs table template
    include WP_DOMAIN_MAPPING_DIR_PATH . 'admin/logs-table.php';
}
?>
