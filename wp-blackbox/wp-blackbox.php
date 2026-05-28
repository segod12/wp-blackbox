<?php
/**
 * Plugin Name: WP Blackbox
 * Plugin URI: https://example.com/wp-blackbox
 * Description: Records important WordPress changes, errors, and slow requests in an incident timeline.
 * Version: 0.8.1
 * Author: WP Blackbox
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wp-blackbox
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_BLACKBOX_REQUEST_START' ) ) {
	define( 'WP_BLACKBOX_REQUEST_START', microtime( true ) );
}

if ( ! defined( 'WP_BLACKBOX_MEMORY_START' ) ) {
	define( 'WP_BLACKBOX_MEMORY_START', memory_get_usage() );
}

define( 'WP_BLACKBOX_VERSION', '0.8.1' );
define( 'WP_BLACKBOX_FILE', __FILE__ );
define( 'WP_BLACKBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_BLACKBOX_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_BLACKBOX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WP_BLACKBOX_DIR . 'includes/class-event-types.php';
require_once WP_BLACKBOX_DIR . 'includes/class-logger.php';
require_once WP_BLACKBOX_DIR . 'includes/class-source-resolver.php';
require_once WP_BLACKBOX_DIR . 'includes/class-table-owner-map.php';
require_once WP_BLACKBOX_DIR . 'includes/class-database-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-cron-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-advisor.php';
require_once WP_BLACKBOX_DIR . 'includes/class-report-generator.php';
require_once WP_BLACKBOX_DIR . 'includes/class-activator.php';
require_once WP_BLACKBOX_DIR . 'includes/class-admin.php';
require_once WP_BLACKBOX_DIR . 'includes/class-change-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-option-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-user-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-fatal-error-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-slow-request-collector.php';
require_once WP_BLACKBOX_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WP_Blackbox_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Blackbox_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WP_Blackbox_Plugin', 'instance' ) );
