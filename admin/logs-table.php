<?php
/**
 * Logs table display for domain mapping
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_LOGS . $where_sql );
$total_pages = ceil( $total_logs / $per_page );

// Get logs with pagination
$logs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_LOGS . $where_sql . " ORDER BY timestamp DESC LIMIT %d, %d",
    $offset,
    $per_page
));

// Get available actions for filter
$actions = $wpdb->get_col( "SELECT DISTINCT action FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_LOGS );

if ( ! $logs ) {
    echo '<div class="notice notice-info"><p>' . esc_html__( 'No domain mapping logs available.', 'wp-domain-mapping' ) . '</p></div>';
    return;
}
?>

<div class="tablenav top">
    <form method="GET" id="logs-filter-form" class="logs-filter">
        <input type="hidden" name="page" value="domains" />
        <input type="hidden" name="tab" value="domain-logs" />

        <div class="alignleft actions">
            <label for="log_action" class="screen-reader-text"><?php esc_html_e( 'Filter by action', 'wp-domain-mapping' ); ?></label>
            <select id="log_action" name="log_action">
                <option value=""><?php esc_html_e( 'All actions', 'wp-domain-mapping' ); ?></option>
                <?php foreach ( $actions as $action ) : ?>
                    <option value="<?php echo esc_attr( $action ); ?>" <?php selected( $action_filter, $action ); ?>>
                        <?php
                        switch ( $action ) {
                            case 'add':
                                esc_html_e( 'Added', 'wp-domain-mapping' );
                                break;
                            case 'edit':
                                esc_html_e( 'Updated', 'wp-domain-mapping' );
                                break;
                            case 'delete':
                                esc_html_e( 'Deleted', 'wp-domain-mapping' );
                                break;
                            default:
                                echo esc_html( ucfirst( $action ) );
                        }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wp-domain-mapping' ); ?>" />

            <?php if ( ! empty( $action_filter ) ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'domains', 'tab' => 'domain-logs' ), admin_url( 'network/sites.php' ) ) ); ?>" class="button">
                    <?php esc_html_e( 'Clear', 'wp-domain-mapping' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav-pages">
        <?php
        echo paginate_links( array(
            'base'      => add_query_arg( 'logs_paged', '%#%' ),
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
                _n( '%s log entry', '%s log entries', $total_logs, 'wp-domain-mapping' ),
                number_format_i18n( $total_logs )
            );
            ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<table class="wp-list-table widefat striped logs-table">
    <thead>
        <tr>
            <th scope="col" class="column-user"><?php esc_html_e( 'User', 'wp-domain-mapping' ); ?></th>
            <th scope="col" class="column-action"><?php esc_html_e( 'Action', 'wp-domain-mapping' ); ?></th>
            <th scope="col" class="column-domain"><?php esc_html_e( 'Domain', 'wp-domain-mapping' ); ?></th>
            <th scope="col" class="column-site"><?php esc_html_e( 'Site', 'wp-domain-mapping' ); ?></th>
            <th scope="col" class="column-date"><?php esc_html_e( 'Date', 'wp-domain-mapping' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $logs as $log ) :
            // Get user data
            $user_data = get_userdata( $log->user_id );
            $username = $user_data
                ? sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( network_admin_url( 'user-edit.php?user_id=' . $log->user_id ) ),
                    esc_html( $user_data->user_login )
                )
                : sprintf( esc_html__( 'User #%d', 'wp-domain-mapping' ), $log->user_id );

            // Format action for display
            switch ( $log->action ) {
                case 'add':
                    $action_display = '<span class="log-action log-action-add">' . esc_html__( 'Added', 'wp-domain-mapping' ) . '</span>';
                    break;
                case 'edit':
                    $action_display = '<span class="log-action log-action-edit">' . esc_html__( 'Updated', 'wp-domain-mapping' ) . '</span>';
                    break;
                case 'delete':
                    $action_display = '<span class="log-action log-action-delete">' . esc_html__( 'Deleted', 'wp-domain-mapping' ) . '</span>';
                    break;
                default:
                    $action_display = '<span class="log-action">' . esc_html( ucfirst( $log->action ) ) . '</span>';
            }

            // Get site name
            $site_name = get_blog_option( $log->blog_id, 'blogname', '' );
            $site_link = ! empty( $site_name )
                ? sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( network_admin_url( 'site-info.php?id=' . $log->blog_id ) ),
                    esc_html( $site_name )
                )
                : sprintf( esc_html__( 'Site #%d', 'wp-domain-mapping' ), $log->blog_id );

            // Format timestamp for display
            $timestamp = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->timestamp );
            $time_diff = human_time_diff( strtotime( $log->timestamp ), current_time( 'timestamp' ) );
        ?>
            <tr>
                <td class="column-user"><?php echo $username; ?></td>
                <td class="column-action"><?php echo $action_display; ?></td>
                <td class="column-domain">
                    <a href="<?php echo esc_url( dm_ensure_protocol( $log->domain ) ); ?>" target="_blank">
                        <?php echo esc_html( $log->domain ); ?>
                        <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.3; opacity: 0.7;"></span>
                    </a>
                </td>
                <td class="column-site">
                    <?php echo $site_link; ?>
                </td>
                <td class="column-date">
                    <abbr title="<?php echo esc_attr( $timestamp ); ?>">
                        <?php
                        /* translators: %s: time ago */
                        printf( esc_html__( '%s ago', 'wp-domain-mapping' ), $time_diff );
                        ?>
                    </abbr>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ( $total_pages > 1 ) : ?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php
        echo paginate_links( array(
            'base'      => add_query_arg( 'logs_paged', '%#%' ),
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
                _n( '%s log entry', '%s log entries', $total_logs, 'wp-domain-mapping' ),
                number_format_i18n( $total_logs )
            );
            ?>
        </span>
    </div>
</div>
<?php endif; ?>

<style>
/* Logs table styles */
.logs-filter {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.logs-table .column-user,
.logs-table .column-site {
    width: 15%;
}

.logs-table .column-action {
    width: 10%;
}

.logs-table .column-date {
    width: 15%;
}

.log-action {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.log-action-add {
    background-color: #dff0d8;
    color: #3c763d;
}

.log-action-edit {
    background-color: #d9edf7;
    color: #31708f;
}

.log-action-delete {
    background-color: #f2dede;
    color: #a94442;
}

/* Pagination */
.tablenav-pages a,
.tablenav-pages span.current {
    display: inline-block;
    min-width: 17px;
    padding: 3px 5px 7px;
    background: #f0f0f1;
    font-size: 16px;
    line-height: 1;
    font-weight: 400;
    text-align: center;
    text-decoration: none;
}

.tablenav-pages span.current {
    background: #007cba;
    color: #fff;
    border-color: #007cba;
}

.displaying-num {
    margin-left: 10px;
    color: #555;
}
</style>
