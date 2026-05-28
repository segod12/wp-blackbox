<?php
/**
 * Event type and severity definitions.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Event_Types {
	/**
	 * Allowed severity values.
	 *
	 * @return array
	 */
	public static function severities() {
		return array( 'info', 'notice', 'warning', 'error', 'critical' );
	}

	/**
	 * Known event types for filters and labels.
	 *
	 * @return array
	 */
	public static function event_types() {
		return array(
			'manual_note',
			'plugin_activated',
			'plugin_deactivated',
			'plugin_updated',
			'theme_switched',
			'theme_updated',
			'core_updated',
			'option_changed',
			'admin_user_created',
			'user_role_changed',
			'admin_user_deleted',
			'user_email_changed',
			'fatal_error',
			'slow_request',
			'slow_ajax',
			'slow_rest',
			'http_500',
			'db_snapshot',
			'db_table_growth',
			'cron_disabled',
			'cron_overdue',
			'action_scheduler_failed',
			'action_scheduler_pending_spike',
		);
	}

	/**
	 * Important options worth recording.
	 *
	 * @return array
	 */
	public static function monitored_options() {
		return array(
			'siteurl',
			'home',
			'blog_public',
			'permalink_structure',
			'active_plugins',
			'template',
			'stylesheet',
			'users_can_register',
			'default_role',
			'admin_email',
			'timezone_string',
			'gmt_offset',
			'woocommerce_store_address',
			'woocommerce_default_country',
			'woocommerce_currency',
			'woocommerce_allowed_countries',
			'woocommerce_ship_to_countries',
			'woocommerce_enable_guest_checkout',
		);
	}
}
