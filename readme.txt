=== WP Multi Push Syndicator ===
Contributors: yourname
Tags: syndication, rest-api, multisite, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Push posts from one WordPress site to multiple external WordPress targets with per-target scheduling and media transfer.

== Description ==

WP Multi Push Syndicator allows editors to select one or many remote WordPress targets directly in post edit screen. On save/publish, the plugin creates or updates remote posts and transfers media.

Features:

- multiple target endpoint profiles
- per-post target selection
- per-target create/update mapping
- featured image and inline media transfer
- scheduling strategies (`fixed_delay`, `random_delay`, `preferred_time`)
- database logs and push status tracking
- rewrite-ready extension interface
- GitHub release update integration

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-multi-push-syndicator`.
2. Activate through the `Plugins` screen.
3. Configure endpoints in `WP Multi Push` admin menu.

== Changelog ==

= 0.1.0 =
* Initial release with architecture, endpoint manager, scheduling strategies, push pipeline and GitHub updater scaffold.