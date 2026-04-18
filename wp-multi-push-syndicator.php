<?php
/**
 * Plugin Name: WP Multi Push Syndicator
 * Plugin URI: https://github.com/example/wp-multi-push-syndicator
 * Description: Push posts to multiple external WordPress websites with per-target scheduling strategies.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://github.com/example
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-multi-push-syndicator
 * Domain Path: /languages
 * Update URI: https://github.com/example/wp-multi-push-syndicator
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WMPS_VERSION', '0.1.0');
define('WMPS_PLUGIN_FILE', __FILE__);
define('WMPS_PLUGIN_DIR', __DIR__);
define('WMPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WMPS_DB_VERSION', '1');

require_once WMPS_PLUGIN_DIR . '/includes/Core/Autoloader.php';

WMPS\Core\Autoloader::register();

register_activation_hook(WMPS_PLUGIN_FILE, ['WMPS\\Core\\Activator', 'activate']);
register_deactivation_hook(WMPS_PLUGIN_FILE, ['WMPS\\Core\\Deactivator', 'deactivate']);

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('wp-multi-push-syndicator', false, dirname(plugin_basename(WMPS_PLUGIN_FILE)) . '/languages');

    $plugin = new WMPS\Core\Plugin();
    $plugin->boot();
});
