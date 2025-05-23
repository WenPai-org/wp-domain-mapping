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

// This file is included from dm_domain_logs() function
?>

<div class="tablenav top">
    <form method="GET" id="logs-filter-form" class="logs-filter">
        <input type="hidden" name="page" value="domains" />

        <div class="alignleft actions">
            <label for="log_action" class="screen-reader-text"><?php esc_html_e( 'Filter by action', 'wp-domain-mapping' ); ?></label>
            <select id="log_action" name="log_action">
                <option value=""><?php esc_html_e( 'All actions', 'wp-domain-mapping' ); ?></option>
                <?php foreach ( $actions as $action ) : ?>
                    <option value="<?php echo esc_attr( $action ); ?>" <?php selected( $action_filter, $action ); ?>>
                        <?php echo esc_html( dm_format_action_name( $action ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wp-domain-mapping' ); ?>" />

            <?php if ( ! empty( $action_filter ) ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'domains' ), admin_url( 'network/sites.php' ) ) ); ?>" class="button">
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
            $action_display = dm_format_action_name( $log->action );
            $action_class = 'log-action-' . $log->action;

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
                <td class="column-action">
                    <span class="log-action <?php echo esc_attr( $action_class ); ?>">
                        <?php echo esc_html( $action_display ); ?>
                    </span>
                </td>
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
