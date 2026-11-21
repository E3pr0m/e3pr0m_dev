=== ErrorLight ===
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

ErrorLight is made for people who have never opened a log file. It shows the end of your WordPress `debug.log` and your PHP error log, sorts the messages by how serious they are, explains each one in a plain sentence and says whether it comes from a plugin, a theme or WordPress itself. It also lets an administrator turn debug logging on and off from a panel, without editing any file by hand.

You find it in **Tools → ErrorLight**. Only administrators can use it. On a multisite network only the super administrator can, and the button that edits `wp-config.php` is not offered there: the file is shared by every site, so the panel shows the lines to set by hand.

**Logs are private data, so the button is careful.** ErrorLight turns logging on from the panel only if it can confirm that the log folder cannot be opened from a web address. On Apache, LiteSpeed and IIS the protection rules it writes do that. Nginx ignores those rules: on Nginx the button turns logging on only if its test confirms the folder is private; otherwise it changes nothing, explains why, and shows the lines you can add to `wp-config.php` by hand. See the FAQ.

= What you can do =

* **Turn debug logging on and off** with one button and a confirmation. ErrorLight edits only the three debug constants of `wp-config.php`, checks the result and restores the original automatically if anything goes wrong.
* **Turn it off automatically**, after 1 hour, 6 hours, 24 hours, 3 days or 7 days, or get a reminder by e-mail every day, 3 days or 7 days. A forgotten debug log is a privacy risk, so you decide what happens before you switch it on.
* **Keep the log out of reach.** When logging is turned on from the panel, the log is saved in a folder with a name nobody can guess, inside your uploads folder, with rules that block access from the web. Before turning logging on, and whenever you press the check button, ErrorLight tests that the log cannot be downloaded.
* **Read the log in plain language.** Each message gets a level (serious problem, warning, minor note), a simple sentence, a readable time and the plugin, theme or WordPress part it comes from.
* **Choose what to look at:** the most recent entries, the last day in the log, the last 7 days in the log or your own dates.
* **Find what you need:** identical messages are shown once with how many times they appear, buttons for the most repeated messages and for common problems, a filter by exact type, and two search fields (what happened, and where it was reported).
* **Download a log**, or **e-mail an excerpt** of it (the last 20, 50 or 100 lines) to an address you choose, with an option to replace file paths, web addresses, e-mail addresses and IP addresses with placeholders.
* **Copy a summary for support** when you ask someone for help, in a safe version with no personal data and a complete version with a warning.
* **Spot the old `wp-content/debug.log`**, the file name everyone tries first. ErrorLight shows where it is, tells you whether it can be downloaded and suggests how to remove it (it never deletes files outside its own folder).

= What this plugin does not do =

* It does not fix errors: it shows them and says where they come from.
* It cannot tell you whether your site is healthy or safe, and it is not a security plugin or a malware scanner.
* It cannot read server logs that PHP has no access to, and it cannot turn on the PHP error log: that setting belongs to your server. ErrorLight shows it when it exists.
* It does not use any external service and does not send data to anyone, except the e-mails you ask for (see Privacy).
* It does not change any other setting of `wp-config.php`. `WP_DEBUG_DISPLAY` is always kept off, so errors are never shown to your visitors.
* It does not turn logging on from the button when it cannot confirm that the log folder is private (for example on Nginx without a rule that blocks the folder).
* It does not create temporary or backup copies of `wp-config.php`.

= Privacy =

ErrorLight does not collect data and does not contact any external service. The only request it makes goes to your own site (a loopback request), to check that the log folder cannot be downloaded from the web.

A log can contain file paths, user names, e-mail addresses and other personal data, and the panel says so wherever you can download, copy or send one. The e-mails ErrorLight sends are only its own, sent with `wp_mail()`:

* the excerpt you ask for, to the address you type (it is not saved);
* the reminder, if you choose to be reminded, to the address you type;
* a single notice to the site administrator address if an automatic turn-off fails. It contains no part of the log.

It does not hook into other e-mails of your site.

= What ErrorLight writes =

* **Database:** one option, `e3p_errorlight_settings`, with your turn-off or reminder choice. It is created when you turn logging on from the panel and removed when you turn it off. Up to two scheduled WP-Cron events go with it.
* **Files:** `wp-config.php` (only when you press the button, with a confirmation: the three debug constants are written directly into the file, with no temporary or backup copy), and a folder named `errorlight` inside your uploads folder (found with `wp_upload_dir()`), with the log folder and its protection files, a lock file and short-lived test files that are deleted right away. ErrorLight never writes to or deletes any other file: the old `wp-content/debug.log` is only read, and you remove it yourself.

Everything ErrorLight creates is removed when you delete the plugin, except the lines it added to `wp-config.php`. Download your logs and turn logging off before you delete the plugin. The `errorlight` folder in uploads is deleted with it, and the lines ErrorLight added to `wp-config.php` stay: if logging was still on, WordPress keeps debugging without a log file until you set `WP_DEBUG` and `WP_DEBUG_LOG` to `false` there.

== Installation ==

1. Upload the `errorlight` folder to `wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Open **Tools → ErrorLight**.

== Frequently Asked Questions ==

= Why can't I turn logging on with the button? =

There are three possible reasons, and the panel tells you which one applies:

1. The debug lines in `wp-config.php` are not written in the standard way (for example, they are inside an `if`) or the file cannot be written. ErrorLight changes nothing and shows how the lines should be written, so you can add them by hand.
2. You are on a multisite network, where `wp-config.php` belongs to every site.
3. ErrorLight cannot confirm that the log folder is private (see the next question).

= Why does the button refuse to turn logging on (Nginx)? =

Logs can contain sensitive data, so ErrorLight turns logging on only if the log folder cannot be opened from a web address. It writes protection rules for Apache, LiteSpeed and IIS, but Nginx ignores them. Before changing anything, ErrorLight requests a test file from that folder:

* if the server hands the file out, ErrorLight changes nothing and warns you;
* if the server ignores the rules (Nginx) and the test cannot confirm that the folder is private, ErrorLight changes nothing and explains why;
* if the server reads the rules (Apache, LiteSpeed, IIS) and the test could not run, logging is turned on and the panel says the folder was not verified.

To solve it, ask your hosting provider to block web access to the `uploads/errorlight` folder (on Nginx, a `location` rule that denies it), then press the button again. You can also add the debug lines to `wp-config.php` by hand: the panel shows them.

= Does it change my wp-config.php safely? =

It checks that the new version is valid PHP before writing it, writes it directly into `wp-config.php` (no temporary or backup copy is created anywhere), then reads the file back and restores the original automatically if anything is wrong, and checks that the restore worked. Two requests at the same time cannot overwrite each other. Only the three debug constants are touched, and every line ErrorLight changes or adds is marked with an `ErrorLight` comment.

= Does it work on a multisite network? =

Yes, in a limited and safe way. On a network only the super administrator can open ErrorLight (from any site of the network), and reading, downloading and e-mailing the log work as usual. The button that turns logging on and off is not offered: `wp-config.php` is shared by every site in the network, so a change made from one site would affect all of them. The panel shows the lines to set by hand instead, and the automatic turn-off is not available. ErrorLight has no network admin screen.

= What happens when I turn logging off? =

`WP_DEBUG` and `WP_DEBUG_LOG` are set to false and the lines ErrorLight added are removed. ErrorLight does not restore the value they had before. If debug logging was already on before you used ErrorLight, the panel tells you.

= Why does the automatic turn-off not run at the exact time? =

It uses WP-Cron, which runs when someone visits the site. ErrorLight also checks every time someone opens an admin screen. If the turn-off fails, logging stays on, the panel says so and the administrator receives one e-mail.

= Is my log really protected? =

The log folder is inside your uploads folder, with a secret name and rules for Apache and IIS that block access from the web. Nginx ignores those rules: there ErrorLight will not turn logging on from the button unless its test confirms the folder is private (see the Nginx question above). You can run the same test at any time with the check button. The old `wp-content/debug.log` is checked too: if it can be downloaded, the panel says so and explains how to remove it.

= Why does it show only part of a large log? =

A very large log would make the page slow or run out of memory, so ErrorLight reads the end of the file, up to 2 MB at a time (less when PHP has little free memory). Pick a period to look at older days. To get the whole file, download it (up to 50 MB; for larger logs you get the latest part).

= Why are some times in UTC? =

ErrorLight shows the dates written in the log, which PHP usually writes in UTC. It converts to your site time only where the log says it uses UTC.

== Changelog ==

= 1.0.0 =
* First release.
