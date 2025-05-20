<?php
/**
 * Admin UI functions for WP Domain Mapping plugin
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render domain edit form
 *
 * @param object|false $row Domain data if editing, false if adding new
 */
function dm_edit_domain( $row = false ) {
    $is_edit = is_object( $row );

    if ( ! $row ) {
        $row = new stdClass();
        $row->blog_id = '';
        $row->domain = '';
        $row->active = 1;
    }
    ?>
    <form id="edit-domain-form" method="POST">
        <input type="hidden" name="orig_domain" value="<?php echo esc_attr( $is_edit ? $row->domain : '' ); ?>" />
        <table class="form-table">
            <tr>
                <th><label for="blog_id"><?php esc_html_e( 'Site ID', 'wp-domain-mapping' ); ?></label></th>
                <td>
                    <input type="number" id="blog_id" name="blog_id" value="<?php echo esc_attr( $row->blog_id ); ?>" class="regular-text" required />
                    <?php if ( ! $is_edit ) : ?>
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
                    <input type="text" id="domain" name="domain" value="<?php echo esc_attr( $row->domain ); ?>" class="regular-text" required
                           placeholder="example.com" pattern="^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$" />
                    <p class="description">
                        <?php esc_html_e( 'Enter the domain without http:// or https:// (e.g., example.com)', 'wp-domain-mapping' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="active"><?php esc_html_e( 'Primary', 'wp-domain-mapping' ); ?></label></th>
                <td>
                    <input type="checkbox" id="active" name="active" value="1" <?php checked( $row->active, 1 ); ?> />
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
            <input type="submit" class="button button-primary" value="<?php echo $is_edit
                ? esc_attr__( 'Update Domain', 'wp-domain-mapping' )
                : esc_attr__( 'Add Domain', 'wp-domain-mapping' ); ?>" />

            <?php if ( $is_edit ) : ?>
                <a href="<?php echo esc_url( admin_url( 'network/sites.php?page=domains' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Cancel', 'wp-domain-mapping' ); ?>
                </a>
            <?php endif; ?>
        </p>
    </form>
    <?php
}

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

    <script>
    jQuery(document).ready(function($) {
        // Handle select all checkbox
        $('#select-all').on('change', function() {
            $('.domain-checkbox').prop('checked', this.checked);
        });

        // Handle individual checkboxes
        $('.domain-checkbox').on('change', function() {
            if (!this.checked) {
                $('#select-all').prop('checked', false);
            } else if ($('.domain-checkbox:checked').length === $('.domain-checkbox').length) {
                $('#select-all').prop('checked', true);
            }
        });

        // Handle delete button clicks
        $('.domain-delete-button').on('click', function(e) {
            e.preventDefault();

            if (confirm('<?php esc_html_e( 'Are you sure you want to delete this domain?', 'wp-domain-mapping' ); ?>')) {
                var domain = $(this).data('domain');
                var data = {
                    action: 'dm_handle_actions',
                    action_type: 'delete',
                    domain: domain,
                    nonce: '<?php echo wp_create_nonce( 'domain_mapping' ); ?>'
                };

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            }
        });
    });
    </script>
    <?php

    if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) {
        echo '<div class="notice notice-warning inline"><p>' .
             esc_html__( 'Warning! Primary domains are currently disabled in network settings.', 'wp-domain-mapping' ) .
             '</p></div>';
    }
}

/**
 * Ensure URL has a protocol
 *
 * @param string $domain Domain name
 * @return string Domain with protocol
 */
function dm_ensure_protocol( $domain ) {
    if ( preg_match( '#^https?://#', $domain ) ) {
        return $domain;
    }
    return 'http://' . $domain;
}

/**
 * Clean domain name (remove protocol and trailing slash)
 *
 * @param string $domain Domain name
 * @return string Cleaned domain
 */
function dm_clean_domain( $domain ) {
    // Remove protocol
    $domain = preg_replace( '#^https?://#', '', $domain );

    // Remove trailing slash
    $domain = rtrim( $domain, '/' );

    // Convert IDN to ASCII (Punycode)
    if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^a-z0-9\-\.]/i', $domain ) ) {
        if (defined('INTL_IDNA_VARIANT_UTS46')) {
            // PHP 7.2+
            $domain = idn_to_ascii( $domain, 0, INTL_IDNA_VARIANT_UTS46 );
        } else {
            // PHP < 7.2
            $domain = idn_to_ascii( $domain );
        }
    }

    return $domain;
}

/**
 * Display IDN warning message
 *
 * @return string Warning message
 */
function dm_idn_warning() {
    return sprintf(
        /* translators: %s: URL to punycode converter */
        wp_kses(
            __( 'International Domain Names should be in <a href="%s" target="_blank">punycode</a> format.', 'wp-domain-mapping' ),
            array( 'a' => array( 'href' => array(), 'target' => array() ) )
        ),
        'https://www.punycoder.com/'
    );
}

/**
 * Render domain logs table
 */
function dm_domain_logs() {
    global $wpdb;

    $table_logs = $wpdb->base_prefix . WP_DOMAIN_MAPPING_TABLE_LOGS;

    // Make sure the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") != $table_logs) {
        echo '<div class="notice notice-error"><p>' .
             esc_html__('Domain mapping logs table is missing. Please deactivate and reactivate the plugin.', 'wp-domain-mapping') .
             '</p></div>';
        return;
    }

    $logs = $wpdb->get_results( "SELECT * FROM {$table_logs} ORDER BY timestamp DESC LIMIT 50" );

    if ( ! $logs ) {
        echo '<div class="notice notice-info"><p>' . esc_html__( 'No logs available.', 'wp-domain-mapping' ) . '</p></div>';
        return;
    }
    ?>
    <table class="wp-list-table widefat striped logs-table">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'User', 'wp-domain-mapping' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Action', 'wp-domain-mapping' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Domain', 'wp-domain-mapping' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Site ID', 'wp-domain-mapping' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Timestamp', 'wp-domain-mapping' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $logs as $log ) :
                $user_data = get_userdata( $log->user_id );
                $username = $user_data ? $user_data->user_login : sprintf( __( 'User #%d', 'wp-domain-mapping' ), $log->user_id );

                // Format action for display
                switch ( $log->action ) {
                    case 'add':
                        $action_display = '<span class="log-action log-action-add">' . __( 'Added', 'wp-domain-mapping' ) . '</span>';
                        break;
                    case 'edit':
                        $action_display = '<span class="log-action log-action-edit">' . __( 'Updated', 'wp-domain-mapping' ) . '</span>';
                        break;
                    case 'delete':
                        $action_display = '<span class="log-action log-action-delete">' . __( 'Deleted', 'wp-domain-mapping' ) . '</span>';
                        break;
                    default:
                        $action_display = '<span class="log-action">' . esc_html( $log->action ) . '</span>';
                }

                // Format timestamp for display
                $timestamp = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->timestamp );
            ?>
                <tr>
                    <td><?php echo esc_html( $username ); ?></td>
                    <td><?php echo $action_display; ?></td>
                    <td><?php echo esc_html( $log->domain ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $log->blog_id ) ); ?>">
                            <?php echo esc_html( $log->blog_id ); ?>
                        </a>
                    </td>
                    <td title="<?php echo esc_attr( $log->timestamp ); ?>">
                        <?php echo esc_html( $timestamp ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <style>
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
    </style>
    <?php
}
