=== WP Domain Mapping ===
Contributors: WenPai
Tags: domain mapping, multisite, WordPress network, custom domains, domain management
Requires at least: 6.7.2
Tested up to: 6.7.2
Stable tag: 1.3.3
Requires PHP: 7.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Map any site on a WordPress multisite network to another domain with enhanced management features.

== Description ==

**WP Domain Mapping** is a powerful plugin designed for WordPress multisite networks. It allows network administrators to map custom domains to individual sub-sites, enhancing flexibility and branding. With features like domain logging, remote login support, and primary domain settings, this plugin provides a robust solution for managing multiple domains in a single WordPress installation.

Key features include:
- Map unlimited custom domains to any sub-site.
- Set primary domains for seamless redirection.
- Log domain management actions for auditing.
- Support for CNAME or IP-based domain mapping.
- User-friendly interface for network and site admins.

Ideal for agencies, developers, and businesses running WordPress multisite networks.

== Installation ==

1. **Upload the Plugin**:
   - Download the plugin ZIP file from [https://wpdomain.com/plugins/wp-domain-mapping/](https://wpdomain.com/plugins/wp-domain-mapping/).
   - In your WordPress admin, go to **Network Admin > Plugins > Add New**.
   - Click **Upload Plugin**, choose the ZIP file, and click **Install Now**.

2. **Activate the Plugin**:
   - After installation, go to **Network Admin > Plugins**.
   - Find "WP Domain Mapping" and click **Network Activate**.

3. **Configure Sunrise**:
   - Copy the `sunrise.php` file from the plugin folder to `wp-content/sunrise.php`.
   - Edit your `wp-config.php` file and add `define('SUNRISE', 'on');` above the line `/* That's all, stop editing! Happy publishing. */`.

4. **Set Up Domain Mapping**:
   - Go to **Network Admin > Settings > Domain Mapping**.
   - Enter your server’s IP address or CNAME for domain mapping.
   - Save the settings.

5. **Map Domains**:
   - Go to **Network Admin > Sites > Domains**.
   - Add a domain, assign it to a site ID, and set it as primary if desired.

== Frequently Asked Questions ==

= Does this plugin work with single-site WordPress installations? =
No, this plugin is designed for WordPress multisite networks only. You must have a network setup to use it.

= What is Punycode, and why do I need it? =
Punycode is a way to represent international domain names (e.g., `例子.com`) using ASCII characters (e.g., `xn--fsq.com`). Enter domains in Punycode format, as the plugin doesn’t convert them automatically. Use a tool like [Verisign’s IDN Converter](https://www.verisign.com/en_US/idn-conversion-tool/index.xhtml) to convert your domains.

= Why do I need sunrise.php? =
The `sunrise.php` file enables domain mapping by loading the plugin’s logic before WordPress processes the request. Without it, custom domains won’t work.

= Can I map multiple domains to one sub-site? =
Yes, you can map multiple domains to a single sub-site and choose one as the primary domain for redirection.

= What happens if I delete a sub-site? =
When a sub-site is deleted with the "drop" option, all associated domain mappings are automatically removed.

== Screenshots ==

1. **Domain Mapping Configuration**: Set up your server IP or CNAME in the network admin settings.
2. **Domains Management**: Add, edit, or delete domain mappings for sub-sites.
3. **Domain Logs**: View a history of domain management actions.

== Changelog ==

= 1.3.3 =
* Fixed sub-site database table creation in `maybe_create_db()` function.
* Updated Punycode link to Verisign’s IDN Conversion Tool.

= 1.3.2 =
* Added domain logging feature to track changes.
* Improved AJAX handling for domain actions.

= 1.0.0 =
* Initial release with core domain mapping functionality.

== Upgrade Notice ==

= 1.3.3 =
This update fixes a critical issue with database table creation for sub-sites and updates the Punycode resource link. Network admins should update to ensure proper functionality.

== Additional Information ==

- **Plugin URI**: [https://wpdomain.com/plugins/wp-domain-mapping/](https://wpdomain.com/plugins/wp-domain-mapping/)
- **Author**: WPDomain.com
- **Author URI**: [https://wpdomain.com/](https://wpdomain.com/)
- **Support**: Visit [https://wpdomain.com/support/](https://wpdomain.com/support/) for help.

This plugin is licensed under the GPL v2 or later, giving you the freedom to use, modify, and distribute it as needed.