<?php
/**
 * Activation and setup routines.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Activator {
	/**
	 * Create database tables and default settings.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_events_table();
		WP_Blackbox_Database_Collector::create_table();
		self::ensure_default_settings();

		if ( ! wp_next_scheduled( 'wp_blackbox_daily_retention_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wp_blackbox_daily_retention_cleanup' );
		}

		if ( ! wp_next_scheduled( 'wp_blackbox_daily_db_snapshot' ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'wp_blackbox_daily_db_snapshot' );
		}

		if ( ! wp_next_scheduled( 'wp_blackbox_hourly_cron_diagnostics' ) ) {
			wp_schedule_event( time() + ( 15 * MINUTE_IN_SECONDS ), 'hourly', 'wp_blackbox_hourly_cron_diagnostics' );
		}
	}

	/**
	 * Remove scheduled events. Data is kept by default.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wp_blackbox_daily_retention_cleanup' );
		wp_clear_scheduled_hook( 'wp_blackbox_daily_db_snapshot' );
		wp_clear_scheduled_hook( 'wp_blackbox_hourly_cron_diagnostics' );
	}

	/**
	 * Build the events table.
	 *
	 * @return void
	 */
	public static function create_events_table() {
		global $wpdb;

		$table_name      = WP_Blackbox_Logger::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_uuid VARCHAR(64) NOT NULL,
			event_type VARCHAR(100) NOT NULL,
			severity VARCHAR(20) NOT NULL DEFAULT 'info',
			summary TEXT NOT NULL,
			details LONGTEXT NULL,
			context LONGTEXT NULL,
			object_type VARCHAR(100) NULL,
			object_name VARCHAR(255) NULL,
			object_version VARCHAR(100) NULL,
			previous_value LONGTEXT NULL,
			new_value LONGTEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			user_login VARCHAR(191) NULL,
			ip_address VARCHAR(100) NULL,
			request_uri TEXT NULL,
			source_file TEXT NULL,
			source_line INT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at),
			KEY object_type (object_type),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Return plugin settings with defaults applied.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( 'wp_blackbox_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Ensure the settings option exists.
	 *
	 * @return void
	 */
	private static function ensure_default_settings() {
		if ( false === get_option( 'wp_blackbox_settings', false ) ) {
			add_option( 'wp_blackbox_settings', self::default_settings(), '', false );
		}
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'enable_change_tracking'       => 1,
			'enable_fatal_error_tracking' => 1,
			'enable_slow_request_tracking' => 1,
			'enable_database_growth_tracking' => 1,
			'enable_cron_tracking'        => 1,
			'event_retention_days'        => 30,
			'slow_frontend_threshold'     => 3,
			'slow_admin_threshold'        => 5,
			'slow_ajax_threshold'         => 2,
			'slow_rest_threshold'         => 2,
			'slow_cron_threshold'         => 10,
			'delete_data_on_uninstall'    => 0,
		);
	}
}
