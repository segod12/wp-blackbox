<?php
/**
 * Main plugin coordinator.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var WP_Blackbox_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the plugin instance.
	 *
	 * @return WP_Blackbox_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}

		return self::$instance;
	}

	/**
	 * Register runtime services.
	 *
	 * @return void
	 */
	private function register() {
		$settings = WP_Blackbox_Activator::get_settings();

		WP_Blackbox_Admin::register();

		if ( ! empty( $settings['enable_change_tracking'] ) ) {
			WP_Blackbox_Change_Collector::register();
			WP_Blackbox_Option_Collector::register();
			WP_Blackbox_User_Collector::register();
		}

		if ( ! empty( $settings['enable_fatal_error_tracking'] ) ) {
			WP_Blackbox_Fatal_Error_Collector::register();
		}

		if ( ! empty( $settings['enable_slow_request_tracking'] ) ) {
			WP_Blackbox_Slow_Request_Collector::register();
		}

		if ( ! empty( $settings['enable_database_growth_tracking'] ) ) {
			WP_Blackbox_Database_Collector::register();
		}

		if ( ! empty( $settings['enable_cron_tracking'] ) ) {
			WP_Blackbox_Cron_Collector::register();
		}

		add_action( 'wp_blackbox_daily_retention_cleanup', array( 'WP_Blackbox_Logger', 'cleanup_retention' ) );
	}
}
