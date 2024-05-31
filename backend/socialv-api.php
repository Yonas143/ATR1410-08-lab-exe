<?php

/**
 * @wordpress-plugin
 * Plugin Name:       socialv-api
 * Plugin URI:        https://iqonic.design
 * Description:       Socialv api mobile plugin
 * Version:           7.3.0
 * Author:            Iqonic Design
 * Author URI:        https://iqonic.design
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       socialv-api
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use Includes\baseClasses\SVActivate;
use Includes\baseClasses\SVDeactivate;

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */

define('SOCIALV_API_TEXT_DOMAIN', 'socialv-api');

defined('ABSPATH') or die('Something went wrong');

// Require once the Composer Autoload
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php'))
	require_once dirname(__FILE__) . '/vendor/autoload.php';
else
	die('Something went wrong');


if (!function_exists('get_plugins'))
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin_data = get_plugin_data(__FILE__);
define('SOCIALV_API_VERSION', $plugin_data['Version']);


if (file_exists(ABSPATH . 'wp-admin/includes/media.php'))
	require_once(ABSPATH . 'wp-admin/includes/media.php');

if (file_exists(ABSPATH . 'wp-admin/includes/image.php'))
	require_once ABSPATH . 'wp-admin/includes/image.php';

if (!defined('SOCIALV_API_DIR'))
	define('SOCIALV_API_DIR', plugin_dir_path(__FILE__));

if (!defined('SOCIALV_API_DIR_URI'))
	define('SOCIALV_API_DIR_URI', plugin_dir_url(__FILE__));

if (!defined('SOCIALV_API_NAMESPACE'))
	define('SOCIALV_API_NAMESPACE', "socialv-api");

if (!defined('SOCIALV_API_PREFIX'))
	define('SOCIALV_API_PREFIX', "iq_");

if (!defined('JWT_AUTH_SECRET_KEY'))
	define('JWT_AUTH_SECRET_KEY', 'your-top-secrect-key');


/**
 * The code that runs during plugin activation
 */
register_activation_hook(__FILE__, [SVActivate::class, 'activate']);

/**
 * The code that runs during plugin deactivation
 */
register_deactivation_hook(__FILE__, [SVDeactivate::class, 'init']);


(new SVActivate)->init();
