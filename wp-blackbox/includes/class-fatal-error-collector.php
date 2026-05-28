<?php
/**
 * Fatal PHP error collector.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Fatal_Error_Collector {
	/**
	 * Register shutdown handler.
	 *
	 * @return void
	 */
	public static function register() {
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Inspect the last PHP error at shutdown.
	 *
	 * @return void
	 */
	public static function handle_shutdown() {
		$error = error_get_last();

		if ( empty( $error ) || empty( $error['type'] ) ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

		if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}

		if ( self::is_duplicate( $error ) ) {
			return;
		}

		$source         = WP_Blackbox_Source_Resolver::resolve( $error['file'] ?? '' );
		$request_type   = WP_Blackbox_Slow_Request_Collector::request_type();
		$related_change = self::find_recent_related_change( $source );
		$error_name     = self::error_type_name( (int) $error['type'] );
		$summary        = sprintf( 'Fatal PHP error detected in %s', $source['source_name'] ? $source['source_name'] : 'unknown source' );
		$details        = sprintf(
			'%s: %s in %s on line %d',
			$error_name,
			$error['message'] ?? '',
			$error['file'] ?? '',
			absint( $error['line'] ?? 0 )
		);

		if ( $related_change ) {
			$details .= "\nPossible cause: " . $related_change['summary'];
		}

		WP_Blackbox_Logger::log(
			'fatal_error',
			$summary,
			array(
				'severity'       => 'critical',
				'details'        => $details,
				'object_type'    => $source['source_type'],
				'object_name'    => $source['source_name'],
				'object_version' => $source['source_version'],
				'source_file'    => $error['file'] ?? '',
				'source_line'    => absint( $error['line'] ?? 0 ),
				'context'        => array(
					'error_type'             => $error_name,
					'message'                => $error['message'] ?? '',
					'file'                   => $error['file'] ?? '',
					'line'                   => absint( $error['line'] ?? 0 ),
					'request_type'           => $request_type,
					'memory_peak'            => memory_get_peak_usage(),
					'active_theme'           => get_stylesheet(),
					'active_plugins_count'   => count( (array) get_option( 'active_plugins', array() ) ),
					'source'                 => $source,
					'recent_related_changes' => $related_change ? array( $related_change ) : array(),
				),
			)
		);
	}

	/**
	 * Avoid logging the same fatal error repeatedly.
	 *
	 * @param array $error PHP error array.
	 * @return bool
	 */
	private static function is_duplicate( $error ) {
		global $wpdb;

		$since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 10 * MINUTE_IN_SECONDS ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . WP_Blackbox_Logger::table_name() . ' WHERE event_type = %s AND source_file = %s AND source_line = %d AND created_at >= %s',
				'fatal_error',
				$error['file'] ?? '',
				absint( $error['line'] ?? 0 ),
				$since
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Find a recent change connected to the same source.
	 *
	 * @param array $source Source descriptor.
	 * @return array|null
	 */
	private static function find_recent_related_change( $source ) {
		global $wpdb;

		if ( empty( $source['source_type'] ) || empty( $source['source_slug'] ) ) {
			return null;
		}

		$event_types = array( 'plugin_activated', 'plugin_updated', 'theme_switched', 'theme_updated', 'option_changed' );
		$since       = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );
		$like        = '%' . $wpdb->esc_like( $source['source_slug'] ) . '%';

		$sql = 'SELECT event_type, summary, created_at FROM ' . WP_Blackbox_Logger::table_name() . '
			WHERE created_at >= %s
			AND event_type IN ("' . implode( '","', array_map( 'esc_sql', $event_types ) ) . '")
			AND (object_name LIKE %s OR summary LIKE %s OR context LIKE %s)
			ORDER BY created_at DESC
			LIMIT 1';

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $since, $like, $like, $like ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$minutes = max( 0, round( ( current_time( 'timestamp' ) - strtotime( $row['created_at'] ) ) / MINUTE_IN_SECONDS ) );

		return array(
			'event_type'     => $row['event_type'],
			'summary'        => sprintf( '%s occurred %d minutes before this fatal error.', $row['summary'], $minutes ),
			'minutes_before' => $minutes,
		);
	}

	/**
	 * Convert an error type integer to a readable name.
	 *
	 * @param int $type Error type.
	 * @return string
	 */
	private static function error_type_name( $type ) {
		$names = array(
			E_ERROR             => 'E_ERROR',
			E_PARSE             => 'E_PARSE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		);

		return $names[ $type ] ?? 'E_UNKNOWN';
	}
}
