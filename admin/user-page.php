<?php
/**
 * User domain mapping management page
 *
 * @package WP Domain Mapping
 */

defined('ABSPATH') || exit;

$protocol = isset($protocol) ? $protocol : (is_ssl() ? 'https://' : 'http://');
$domains = isset($domains) ? $domains : array();
?>

<div class="wrap">
    <h1><?php _e('Domain Mapping', 'wp-domain-mapping'); ?></h1>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success">
            <p>
                <?php
                switch ($_GET['updated']) {
                    case 'add':
                        _e('Domain added successfully.', 'wp-domain-mapping');
                        break;
                    case 'exists':
                        _e('This domain is already mapped to a site.', 'wp-domain-mapping');
                        break;
                    case 'primary':
                        _e('Primary domain updated.', 'wp-domain-mapping');
                        break;
                    case 'del':
                        _e('Domain deleted successfully.', 'wp-domain-mapping');
                        break;
                    default:
                        break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card domain-mapping-card">
        <h2><?php _e('Add New Domain', 'wp-domain-mapping'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('tools.php?page=domainmapping')); ?>">
            <?php wp_nonce_field('domain_mapping'); ?>
            <input type="hidden" name="action" value="add" />

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="domain"><?php _e('Domain', 'wp-domain-mapping'); ?></label></th>
                    <td>
                        <input name="domain" id="domain" type="text" value="" class="regular-text"
                               placeholder="example.com" pattern="^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$" required />
                        <p class="description">
                            <?php _e('Enter the domain without http:// or https:// (e.g., example.com)', 'wp-domain-mapping'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Primary Domain', 'wp-domain-mapping'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Primary Domain', 'wp-domain-mapping'); ?></legend>
                            <label for="primary">
                                <input name="primary" type="checkbox" id="primary" value="1" />
                                <?php _e('Set this domain as the primary domain for this site', 'wp-domain-mapping'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Add Domain', 'wp-domain-mapping'); ?>" />
            </p>
        </form>
    </div>

    <?php if (!empty($domains)): ?>
    <div class="card domain-mapping-card">
        <h2><?php _e('Your Mapped Domains', 'wp-domain-mapping'); ?></h2>

        <table class="wp-list-table widefat fixed striped domains-table">
            <thead>
                <tr>
                    <th><?php _e('Domain', 'wp-domain-mapping'); ?></th>
                    <th><?php _e('Primary', 'wp-domain-mapping'); ?></th>
                    <th><?php _e('Actions', 'wp-domain-mapping'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domains as $domain): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($protocol . $domain->domain); ?>" target="_blank">
                            <?php echo esc_html($domain->domain); ?>
                            <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.3; opacity: 0.7;"></span>
                        </a>
                    </td>
                    <td>
                        <?php if ($domain->active == 1): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <span class="screen-reader-text"><?php _e('Yes', 'wp-domain-mapping'); ?></span>
                        <?php else: ?>
                            <a href="<?php echo wp_nonce_url(add_query_arg(
                                array('page' => 'domainmapping', 'action' => 'primary', 'domain' => $domain->domain),
                                admin_url('tools.php')
                            ), 'domain_mapping'); ?>" class="button button-small">
                                <?php _e('Make Primary', 'wp-domain-mapping'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($domain->active != 1): ?>
                            <a href="<?php echo wp_nonce_url(add_query_arg(
                                array('page' => 'domainmapping', 'action' => 'delete', 'domain' => $domain->domain),
                                admin_url('tools.php')
                            ), 'delete' . $domain->domain); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this domain?', 'wp-domain-mapping'); ?>');">
                                <?php _e('Delete', 'wp-domain-mapping'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="card domain-mapping-card">
        <h2><?php _e('DNS Configuration Instructions', 'wp-domain-mapping'); ?></h2>

        <?php
        $ipaddress = get_site_option('dm_ipaddress');
        $cname = get_site_option('dm_cname');

        if (!$ipaddress && !$cname): ?>
            <div class="notice notice-error">
                <p><?php _e('The site administrator has not configured the DNS settings. Please contact them for assistance.', 'wp-domain-mapping'); ?></p>
            </div>
        <?php else: ?>
            <p><?php _e('To map your domain to this site, you need to update your DNS records with your domain registrar.', 'wp-domain-mapping'); ?></p>

            <?php if ($cname): ?>
                <h3><?php _e('CNAME Method (Recommended)', 'wp-domain-mapping'); ?></h3>
                <p><?php printf(__('Create a CNAME record for your domain pointing to: <code>%s</code>', 'wp-domain-mapping'), esc_html($cname)); ?></p>
            <?php endif; ?>

            <?php if ($ipaddress): ?>
                <h3><?php _e('A Record Method', 'wp-domain-mapping'); ?></h3>
                <p><?php printf(__('Create an A record for your domain pointing to: <code>%s</code>', 'wp-domain-mapping'), esc_html($ipaddress)); ?></p>
            <?php endif; ?>

            <p class="description"><?php _e('DNS changes may take 24-48 hours to fully propagate across the internet.', 'wp-domain-mapping'); ?></p>
        <?php endif; ?>
    </div>
</div>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Make Primary 按钮 AJAX 处理
    $('.button[href*="action=primary"]').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var href = $button.attr('href');
        var domain = new URLSearchParams(href.split('?')[1]).get('domain');

        $button.prop('disabled', true).text('<?php _e('Processing...', 'wp-domain-mapping'); ?>');

        // 直接访问链接
        window.location.href = href;
    });
});
</script>
