=== WordPress Migration Plugin DB & Files - WP Synchro ===
Contributors: wpsynchro
Donate link: https://wpsynchro.com/?utm_source=wordpress.org&utm_medium=referral&utm_campaign=donate
Tags: migrate,database,files,media,migration
Requires at least: 4.9
Tested up to: 5.6
Stable tag: 1.6.2
Requires PHP: 5.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0

WordPress migration plugin, that migrate database tables, media, plugins, themes and whatever you want. Fully customizable. Setup once, run many times

== Description ==

**Complete Migration Plugin for WP developers, by WP developers**

The only migration tool you will ever need as a professional WordPress developer.
WP Synchro was created to be the migration plugin for developers, with a need to do customized migrations.
You need it done in a fast and easy way, that can be re-run very quickly without any further manual steps.
You can fully customize which database tables you want to move and in PRO version, which files/dirs you want to migrate.

A classic task that WP Synchro handles, is keeping a local development site synchronized with a production site or a staging site in sync with a production site.
You can also push data from your staging or local development enviornment to your production site.

**WP Synchro FREE gives you:**

*   Pull/push database from one site to another site
*   Search/replace in database data (supports serialized data)
*   Handles migration of database table prefixes between sites
*   Select the specific database tables you want to move or just move all
*   Automatic clear cache after migration for popular cache plugins
*   High security - No other sites and servers are involved and all data is encrypted on transfer
*   Setup once - Run multiple times - Perfect for development/staging/production environments

**In addition to this, the PRO version gives you:**

*   File synchronization (such as media, plugins, themes or custom files/dirs)
*   Only synchronize the difference in files, making it super fast
*   Customize the exact synchronization you need - Down to a single file
*   Support for basic authentication (.htaccess username/password)
*   Notification email on success or failure to a list of emails
*   Database backup before migration
*   WP CLI command to schedule synchronizations via cron or other trigger
*   Pretty much the ultimate tool for doing WordPress migrations
*   14 day trial is waiting for you to get started at [WPSynchro.com](https://wpsynchro.com/ "WP Synchro PRO")

**Typical use for WP Synchro:**

 *  Developing websites on local server and wanting to push a website to a live server or staging server
 *  Get a copy of a working production site, with both database and files, to a staging or local site for debugging or development with real data
 *  Generally moving WordPress sites from one place to another, even on a firewalled local network

**WP Synchro PRO version:**

Pro version gives you more features, such as synchronizing files, database backup, notifications, support for basic authentication, WP CLI command and much faster support.
Check out how to get PRO version at [WPSynchro.com](https://wpsynchro.com/ "WP Synchro PRO")
We have a 14 day trial waiting for you and 30 day money back guarantee. So why not try the PRO version?

== Installation ==

**Here is how you get started:**

1. Upload the plugin files to the `/wp-content/plugins/wpsynchro` directory, or install the plugin through the WordPress plugins screen directly
1. Make sure to install the plugin on all the WordPress installations (it is needed on both ends of the synchronizing)
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Choose if data can be overwritten or be downloaded from installation in menu WP Synchro->Setup
1. Add your first installation from WP Synchro overview page and configure it
1. Run the synchronization
1. Enjoy
1. Rerun the same migration again next time it is needed and enjoy how easy that was

== Frequently Asked Questions ==

= Do you offer support? =

Yes we do, for both free and PRO version. But PRO version users always get priority support, so support requests for the free version will normally take some time.
Check out how to get PRO version at [WPSynchro.com](https://wpsynchro.com/ "WP Synchro site")

You can contact us at <support@wpsynchro.com> for support. Also check out the "Support" menu in WP Synchro, that provides information needed for the support request.

= Does WP Synchro do database merge? =

No. We do not merge data in database. We only migrate the data and overwrite the current.

= Where can i contact you with new ideas and bugs? =

If you have an idea for improving WP Synchro or found a bug in WP Synchro, we would love to hear from you on:
<support@wpsynchro.com>

= What is WP Synchro tested on? (WP Version, PHP, Databases)=

Currently we do automated testing on 216 different hosting environments with combinations of WordPress/PHP/Database versions.

WP Synchro is tested on :
 * MySQL 5.5 up to MySQL 8.0 and MariaDB from 5.5 to 10.4.
 * PHP 5.6 up to latest version
 * WordPress from 4.9 to latest version.

= Do you support multisite? =

No, not at the moment.
We have not done testing on multisite yet, so use it is at own risk.
It is currently planned for one of the next releases to support it.

== Screenshots ==

1. Shows the overview of plugin, where you start and delete the synchronization jobs
2. Shows the add/edit screen, where you setup a synchronization job
3. Shows the setup of the plugin
4. WP Synchro doing a database migration

== Changelog ==

= 1.6.2 =
 * Bugfix: Updating REST service definition that is causing PHP notice on WP 5.5

= 1.6.1 =
 * Improvement: Added better logging to file population REST service, to better support cases when it is hanging or not progressing
 * Bugfix: Success notification email were sent many times, when using it with WP CLI runs
 * Bugfix: Handle edge case when doing data compression, where some hosting adds header to compressed data, causing error on decompression

= 1.6.0 =
 * Improvement: Better support HTTPS to HTTP migrations, where it previously were dependent on browser. It no longer is
 * Improvement: Remove IP validation, as it was causing too much troubles with only minimal value
 * Improvement: Better support for slow hosting or slow connection speed. Can be enabled in "Setup"
 * Improvement: Better uninstall hook that cleans up the database and files like a good little plugin
 * Improvement: Only keep 20 database backups and logs at any point, to prevent it taking up space
 * Improvement: Support for basic authentication (.htaccess protected sites) on both ends (PRO version)
 * Improvement: Add hooks for successful and failed synchronizations, for devs to hook into (wpsynchro_synchronization_completed / wpsynchro_synchronization_failure)
 * Improvement: Tries to clean cache for popular cache solutions after a successful synchronization (WP Rocket/WP Super Cache/W3 Total cache/Comet Cache)
 * Improvement: Better handles charset and unknown collations - Will now change charset and collation to recommended for WP (utf8mb4)
 * Improvement: Configure email to send email to on successful/failed synchronization (PRO version)
 * Improvement: Remove option to set debug logging and just make it the default
 * Improvement: Add logo to all headers on all pages
 * Improvement: Massively improved database finalize, doing it chunked instead of in one go, that could create problems on sites with many tables
 * Improvement: Rewrote the file population algorithm, to make it much faster and safer to run (PRO version)
 * Improvement: Added new default database search/replace with urlencoded urls, that is used by some page builder plugins
 * Bugfix: Fix problem when it sometimes try to load a file that does not exist in the free version
 * Other: Bump minimum supported WP to 4.9 from 4.7

= 1.5.2 =
 * Bugfix: File population can in certain cases generate REST errors

= 1.5.1 =
 * Bugfix: Make multisite error a warning instead, to prevent blocking users that want to use it anyway
 * Bugfix: Make "WP in own dir" error a warning instead of error, improve the path detection and give a more detailed warning message
 * Improvement: Change JSON debug information to also include file paths and remove the general debug data

= 1.5.0 =
 * General: Overall improvement of stability and many big and small improvements
 * Improvement: Support for replacing url's inside json data, such as Elementor templates (wp.org issue)
 * Improvement: Finally full support for all MySQL datatypes
 * Improvement: Support for subdirectory sites
 * Improvement: Search/replaces are now editable and removable, even the default ones
 * Improvement: General improvement of error messages given to the user
 * Improvement: Support for WordPress 5.3, which is just around the corner
 * Improvement: Proper message when doing synchronization from HTTPS site to HTTP site, which Chrome no longer allows
 * Improvement: Added option in "setup" menu to disable IP security check - Can be needed if requests pass multiple server
 * Improvement: Proper warning to user on installation creation, if one of the two sites have overlapping paths
 * Improvement: Add check to make sure database user can create tables in the database
 * Improvement: Implement PSR4 autoloading with composer
 * Improvement: More intelligent support for WordPress in its own directory - Such as Roots Bedrock
 * Improvement: Logs are now rotated and will have max 20 logs at any point - older will be deleted
 * Improvement: Added a button on "Logs" page to remove all the logs
 * Improvement: Making it more clear when a "Installation" is new or being edited
 * Improvement: Better deactivation, that now removes the database tables
 * Bugfix: Try to prevent REST service call periodic timeouts, which we have seen some users having trouble with

** Only showing the last few releases - See rest of changelog in changelog.txt **