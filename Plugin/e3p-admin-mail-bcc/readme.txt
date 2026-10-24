=== Admin Mail Bcc ===
Contributors:      e3pr0m
Tags:              email, bcc, admin, notifications, updates
Requires at least: 5.2
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Send a hidden Bcc copy of selected WordPress system emails to an extra address.

== Description ==

Admin Mail Bcc adds a second recipient, in Bcc, to the system emails that WordPress sends to the site administrator: critical errors, automatic updates, new user registrations and comments awaiting moderation.

You choose the address and which emails to copy from **Settings → Admin Mail Bcc**.

The plugin does not hook the generic `wp_mail` function. It only uses the filters of the individual system notifications, so other emails of the site (WooCommerce orders, contact forms, newsletters) are never copied.

= Features =

* Hidden Bcc copy of the "Your Site is Experiencing a Technical Issue" email
* Bcc copy of automatic update results for core, plugins and themes, and of the updates debug email
* Optional copy of the new user registration notice
* Optional copy of the "comment awaiting moderation" notice
* The address can also be fixed in `wp-config.php` with the `E3P_AMB_EXTRA_EMAIL` constant, so the same setup works on many sites

= What this plugin does not do =

* It does not copy WooCommerce emails or any other generic email of the site
* It does not replace the original recipient: the administrator still receives every email
* It does not fix delivery problems: if the site cannot send email (missing or wrong SMTP), neither the original email nor the copy is sent

= Privacy =

The plugin does not collect or send data to any external service. The emails you select are copied to the address you set, so make sure the person who receives them is authorized to read that content.

== Installation ==

1. Upload the `e3p-admin-mail-bcc` folder to `wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings → Admin Mail Bcc**, enter the address that will receive the copy and choose the emails.

Optional: to set the address in code, add this to `wp-config.php` before the line "That's all, stop editing!":

`define( 'E3P_AMB_EXTRA_EMAIL', 'address@example.com' );`

The constant takes precedence over the address saved in Settings.

== Frequently Asked Questions ==

= Nothing happens after activation =

The plugin stays idle until a valid address is set in Settings or in `wp-config.php`.

= Are WooCommerce emails copied? =

No. Only the WordPress system notifications listed in Settings can be copied.

= How do I remove all plugin data? =

Deleting the plugin from the Plugins screen removes its only option (`e3p_amb_settings`).

== Changelog ==

= 1.0.0 =
* First release.
* Settings page to set the Bcc address and choose which system emails to copy.
* Supported notifications: critical error, automatic updates (core, plugins, themes, debug), new user registration, comment awaiting moderation.
