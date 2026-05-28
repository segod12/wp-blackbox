<?php
/**
 * Important option change collector.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Option_Collector {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'updated_option', array( __CLASS__, 'updated_option' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'added_option' ), 10, 2 );
		add_action( 'deleted_option', array( __CLASS__, 'deleted_option' ), 10, 1 );
	}

	/**
	 * Log monitored option updates.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public static function updated_option( $option, $old_value, $new_value ) {
		if ( ! self::should_log( $option ) || $old_value === $new_value ) {
			return;
		}

		self::log_option_change( 'updated', $option, $old_value, $new_value );
	}

	/**
	 * Log monitored option additions.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public static function added_option( $option, $value ) {
		if ( ! self::should_log( $option ) ) {
			return;
		}

		self::log_option_change( 'added', $option, null, $value );
	}

	/**
	 * Log monitored option deletions.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function deleted_option( $option ) {
		if ( ! self::should_log( $option ) ) {
			return;
		}

		self::log_option_change( 'deleted', $option, null, null );
	}

	/**
	 * Determine whether an option should be logged.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function should_log( $option ) {
		return in_array( $option, WP_Blackbox_Event_Types::monitored_options(), true );
	}

	/**
	 * Write an option change event.
	 *
	 * @param string $action Action label.
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	private static function log_option_change( $action, $option, $old_value, $new_value ) {
		$warning_options = array( 'siteurl', 'home', 'permalink_structure', 'default_role', 'users_can_register', 'admin_email' );
		$severity        = in_array( $option, $warning_options, true ) ? 'warning' : 'notice';

		WP_Blackbox_Logger::log(
			'option_changed',
			sprintf( 'Important option %s: %s', $action, $option ),
			array(
				'severity'       => $severity,
				'object_type'    => 'option',
				'object_name'    => $option,
				'previous_value' => self::format_value( $old_value ),
				'new_value'      => self::format_value( $new_value ),
				'context'        => array(
					'action'      => $action,
					'option_name' => $option,
				),
			)
		);
	}

	/**
	 * Prepare an option value for storage.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function format_value( $value ) {
		if ( null === $value ) {
			return '';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$value = (string) $value;

		if ( strlen( $value ) > 5000 ) {
			$value = substr( $value, 0, 5000 ) . '...';
		}

		return $value;
	}
}
