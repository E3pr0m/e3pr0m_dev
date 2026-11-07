=== LogPilot ===
Contributors:      e3pr0m
Tags:              debug, error log, debug.log, php errors, troubleshooting
Requires at least: 5.3
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Read your debug.log and PHP error log in plain language, turn WordPress debug logging on and off from a panel, and download or e-mail an excerpt.

== Description ==

LogPilot is made for people who have never opened a log file. It shows the end of your WordPress `debug.log` and your PHP error log, sorts the messages by how serious they are, explains each one in a plain sentence and says whether it comes from a plugin, a theme or WordPress itself. It also lets an administrator turn debug logging on and off from a panel, without editing any file.

You find it in **Tools → LogPilot**. Only administrators can use it.

= What you can do =

* **Turn debug logging on and off** with one button and a confirmation. LogPilot edits `wp-config.php` for you, keeping a safety copy.
* **Turn it off automatically**, after 1 hour, 6 hours, 24 hours, 3 days or 7 days, or get a reminder by e-mail every day, 3 days or 7 days. A forgotten debug log is a privacy risk, so you decide what happens before you switch it on.
* **Keep the log out of reach.** When logging is turned on from the panel, the log is saved in a folder with a name nobody can guess, outside your website folder when the server allows it. A button checks that the log cannot be downloaded from the web.
* **Read the log in plain language.** Each message gets a level (serious problem, warning, minor note), a simple sentence, a readable time and the plugin, theme or WordPress part it comes from.
* **Choose what to look at:** the most recent entries, the last day, the last 7 days or your own dates.
* **Find what you need:** identical messages are shown once with how many times they appear, buttons for the most repeated messages and for common problems, a filter by exact type, and two search fields (what happened, and where it was reported).
* **Download a log**, or **e-mail an excerpt** of it (the last 20, 50 or 100 lines) to an address you choose, with an option to replace file paths, web addresses, e-mail addresses and IP addresses with placeholders.
* **Copy a summary for support** when you ask someone for help, in a safe version with no personal data and a complete version with a warning.
* **Delete the old `wp-content/debug.log`**, the file name everyone tries first, when you no longer need it.

= What this plugin does not do =

* It does not fix errors: it shows them and says where they come from.
* It cannot tell you whether your site is healthy or safe, and it is not a security plugin or a malware scanner.
* It cannot read server logs that PHP has no access to, and it cannot turn on the PHP error log: that setting belongs to your server. LogPilot shows it when it exists.
* It does not use any external service and does not send data to anyone, except the e-mails you ask for (see Privacy).
* It does not change any other setting of `wp-config.php`. `WP_DEBUG_DISPLAY` is always kept off, so errors are never shown to your visitors.

= Privacy =

LogPilot does not collect data and does not contact any external service. The only request it makes goes to your own site, to check that the log folder cannot be downloaded from the web.

A log can contain file paths, user names, e-mail addresses and other personal data, and the panel says so wherever you can download, copy or send one. The e-mails LogPilot sends are only its own, sent with `wp_mail()`:

* the excerpt you ask for, to the address you type (it is not saved);
* the reminder, if you choose to be reminded, to the address you type;
* a single notice to the site administrator address if an automatic turn-off fails. It contains no part of the log.

It does not hook into other e-mails of your site.

= What LogPilot writes =

* **Database:** one option, `e3p_logpilot_settings`, with your turn-off or reminder choice. It is created when you turn logging on from the panel and removed when you turn it off. Up to two scheduled WP-Cron events go with it.
* **Files:** `wp-config.php` (only when you press the button, with a confirmation), a safety copy `wp-config-logpilot-backup.php` next to it, and the folder of the log. If you delete the old `wp-content/debug.log` from the panel, that file is removed.

Everything LogPilot creates is removed when you delete the plugin, except the lines it added to `wp-config.php`. Download your logs and turn logging off before you delete the plugin. The log folder is deleted with it, and the lines LogPilot added to `wp-config.php` stay: if logging was still on, WordPress keeps debugging without a log file until you set `WP_DEBUG` and `WP_DEBUG_LOG` to `false` there.

== Installation ==

1. Upload the `logpilot` folder to `wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Open **Tools → LogPilot**.

== Frequently Asked Questions ==

= Why can't I turn logging on with the button? =

LogPilot only edits `wp-config.php` when the debug lines are written in the standard way and the file can be written. If something is different (for example, the lines are inside an `if`), it changes nothing and shows you how the lines should be written, so you can add them by hand.

= Does it change my wp-config.php safely? =

Before each change it keeps a copy of the file, writes the new version in one step, checks that the result is valid PHP and restores the original automatically if anything is wrong. Only the three debug constants are touched, and every line LogPilot changes or adds is marked with a `LogPilot` comment.

= What happens when I turn logging off? =

`WP_DEBUG` and `WP_DEBUG_LOG` are set to false and the lines LogPilot added are removed. LogPilot does not restore the value they had before. If debug logging was already on before you used LogPilot, the panel tells you.

= Why does the automatic turn-off not run at the exact time? =

It uses WP-Cron, which runs when someone visits the site. LogPilot also checks every time someone opens an admin screen. If the turn-off fails, logging stays on, the panel says so and the administrator receives one e-mail.

= Is my log really protected? =

When the server lets LogPilot save the log outside the website folder, nobody can reach it from the web. Otherwise the log folder stays in `wp-content` with rules for Apache and IIS that block access. Nginx ignores those rules, so on Nginx use the button that checks the log, and keep the folder outside the website folder if you can.

= Why does it show only part of a large log? =

A very large log would make the page slow or run out of memory, so LogPilot reads the end of the file, up to 2 MB at a time (less when PHP has little free memory). Pick a period to look at older days. To get the whole file, download it (up to 50 MB; for larger logs you get the latest part).

= Why are some times in UTC? =

LogPilot shows the dates written in the log, which PHP usually writes in UTC. It converts to your site time only where the log says it uses UTC.

== Changelog ==

= 1.0.0 =
* First release.
