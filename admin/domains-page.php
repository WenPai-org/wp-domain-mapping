<?php
/**
 * Domains administration page
 *
 * @package WP Domain Mapping
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php printf( esc_html__( 'Version: %s', 'wp-domain-mapping' ), esc_html( WP_DOMAIN_MAPPING_VERSION ) ); ?>
        </span>
        <a href="https://wpmultisite.com/document/wp-domain-mapping" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e( 'Documentation', 'wp-domain-mapping' ); ?>
        </a>
        <a href="https://wpmultisite.com/forums/" target="_blank" class="button button-secondary">
            <?php esc_html_e( 'Support', 'wp-domain-mapping' ); ?>
        </a>
    </h1>

    <div class="card domain-mapping-card">
        <h2><?php echo $edit_row ? esc_html__( 'Edit Domain', 'wp-domain-mapping' ) : esc_html__( 'Add New Domain', 'wp-domain-mapping' ); ?></h2>
        <div id="edit-domain-status" class="notice" style="display:none;"></div>
        <?php dm_edit_domain( $edit_row ); ?>
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

                    // Count total items for pagination
                    $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_DOMAINS . $where_sql );
                    $total_pages = ceil( $total_items / $per_page );

                    // Get the domains with pagination
                    $rows = $wpdb->get_results( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_DOMAINS . $where_sql . " ORDER BY id DESC LIMIT %d, %d",
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
                        $sites_with_domains = $wpdb->get_var( "SELECT COUNT(DISTINCT blog_id) FROM {$wpdb->base_prefix}" . WP_DOMAIN_MAPPING_TABLE_DOMAINS );
                        echo esc_html( $sites_with_domains );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.domain-mapping-tab').on('click', function() {
        $('.domain-mapping-tab').removeClass('active');
        $(this).addClass('active');

        var tab = $(this).data('tab');
        $('.domain-mapping-section').hide();
        $('.domain-mapping-section[data-section="' + tab + '"]').show();
    });

    // Helper function for showing notices
    function showNotice(selector, message, type) {
        $(selector)
            .removeClass('notice-success notice-error notice-warning notice-info')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>')
            .show()
            .delay(3000)
            .fadeOut();
    }

    // Handle domain edit/add form submission
    $('#edit-domain-form').on('submit', function(e) {
        e.preventDefault();

        // Validate form
        var blogId = $('#blog_id').val();
        var domain = $('#domain').val();

        if (!blogId || !domain) {
            showNotice('#edit-domain-status', '<?php esc_html_e( 'Please fill in all required fields.', 'wp-domain-mapping' ); ?>', 'error');
            return;
        }

        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'dm_handle_actions'});
        formData.push({name: 'action_type', value: 'save'});
        formData.push({name: 'nonce', value: '<?php echo wp_create_nonce( 'domain_mapping' ); ?>'});

        $('#edit-domain-status').html('<p><?php esc_html_e( 'Saving...', 'wp-domain-mapping' ); ?></p>').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('#edit-domain-status', response.data, 'success');
                    setTimeout(function() {
                        location.href = '<?php echo esc_js( admin_url( 'network/sites.php?page=domains' ) ); ?>';
                    }, 1000);
                } else {
                    showNotice('#edit-domain-status', response.data || '<?php esc_html_e( 'Failed to save domain.', 'wp-domain-mapping' ); ?>', 'error');
                }
            },
            error: function() {
                showNotice('#edit-domain-status', '<?php esc_html_e( 'Server error occurred.', 'wp-domain-mapping' ); ?>', 'error');
            }
        });
    });

    // Handle domain list bulk actions
    $('#domain-list-form').on('submit', function(e) {
        e.preventDefault();

        var selectedDomains = [];
        $('.domain-checkbox:checked').each(function() {
            selectedDomains.push($(this).val());
        });

        if (selectedDomains.length === 0) {
            showNotice('#domain-status', '<?php esc_html_e( 'Please select at least one domain.', 'wp-domain-mapping' ); ?>', 'error');
            return;
        }

        var action = $('#bulk-action-selector-top').val();
        if (action === '-1') return;

        if (!confirm('<?php esc_html_e( 'Are you sure you want to delete the selected domains?', 'wp-domain-mapping' ); ?>')) {
            return;
        }

        $('#domain-status').html('<p><?php esc_html_e( 'Processing...', 'wp-domain-mapping' ); ?></p>').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_handle_actions',
                action_type: 'delete',
                domains: selectedDomains,
                nonce: '<?php echo wp_create_nonce( 'domain_mapping' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('#domain-status', response.data, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('#domain-status', response.data || '<?php esc_html_e( 'Failed to delete domains.', 'wp-domain-mapping' ); ?>', 'error');
                }
            },
            error: function() {
                showNotice('#domain-status', '<?php esc_html_e( 'Server error occurred.', 'wp-domain-mapping' ); ?>', 'error');
            }
        });
    });
});
</script>

<style>
/* Main cards */
.domain-mapping-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-width: unset;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

/* Tabs */
.domain-mapping-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 20px;
}

.domain-mapping-tab {
    padding: 8px 16px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 2px solid transparent;
}

.domain-mapping-tab.active {
    border-bottom: 2px solid #007cba;
    font-weight: 600;
    background: #f0f0f1;
}

.domain-mapping-tab:hover:not(.active) {
    background: #f0f0f1;
    border-bottom-color: #dcdcde;
}

.domain-mapping-content {
    flex: 1;
}

/* Search form */
.search-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 15px;
    margin-bottom: 15px;
}

.search-form-field {
    display: flex;
    flex-direction: column;
    min-width: 180px;
}

.search-form-field label {
    margin-bottom: 5px;
    font-weight: 500;
}

.search-form-submit {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Tables */
.tablenav {
    margin: 10px 0;
    display: flex;
    align-items: center;
}

.tablenav-pages {
    margin-left: auto;
}

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

.domains-table th,
.logs-table th {
    font-weight: 600;
}

.column-site-id {
    width: 80px;
}

.column-site-name {
    width: 20%;
}

.column-primary {
    width: 80px;
    text-align: center;
}

.column-actions {
    width: 120px;
}

/* Form elements */
.form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.form-table td {
    padding: 15px 0;
}

.description {
    color: #666;
    font-size: 13px;
    margin-top: 4px;
}

/* Notices */
.notice {
    padding: 8px 12px;
    border-radius: 3px;
    margin: 5px 0 15px;
}

.notice p {
    margin: 0.5em 0;
    padding: 2px;
}

.notice-success {
    background-color: #f0f9eb;
    border-left: 4px solid #46b450;
}

.notice-error {
    background-color: #fef0f0;
    border-left: 4px solid #dc3232;
}

.notice-warning {
    background-color: #fff8e5;
    border-left: 4px solid #ffb900;
}

.notice-info {
    background-color: #f0f6fa;
    border-left: 4px solid #00a0d2;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .search-form {
        flex-direction: column;
        align-items: stretch;
    }

    .search-form-field {
        min-width: 100%;
    }

    .form-table th {
        width: 100%;
        display: block;
    }

    .form-table td {
        display: block;
        padding: 0 0 15px;
    }
}
</style>
