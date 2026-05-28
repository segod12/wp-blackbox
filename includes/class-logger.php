<?php
/**
 * Event logger service.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Logger {
	/**
	 * Get the event table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'blackbox_events';
	}

	/**
	 * Write an event. This must never break the site.
	 *
	 * @param string $event_type Event type.
	 * @param string $summary Human-readable summary.
	 * @param array  $args Optional event fields.
	 * @return int|false
	 */
	public static function log( $event_type, $summary, $args = array() ) {
		global $wpdb;

		try {
			$table_name = self::table_name();
			$event_type = sanitize_key( $event_type );
			$summary    = sanitize_textarea_field( wp_unslash( $summary ) );

			if ( '' === $event_type || '' === $summary ) {
				return false;
			}

			$severity = isset( $args['severity'] ) ? sanitize_key( $args['severity'] ) : 'info';
			if ( ! in_array( $severity, WP_Blackbox_Event_Types::severities(), true ) ) {
				$severity = 'info';
			}

			$user = wp_get_current_user();

			$data = array(
				'event_uuid'     => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : md5( uniqid( $event_type, true ) ),
				'event_type'     => $event_type,
				'severity'       => $severity,
				'summary'        => $summary,
				'details'        => self::prepare_long_value( $args['details'] ?? null ),
				'context'        => self::prepare_long_value( $args['context'] ?? null ),
				'object_type'    => self::prepare_short_value( $args['object_type'] ?? null, 100 ),
				'object_name'    => self::prepare_short_value( $args['object_name'] ?? null, 255 ),
				'object_version' => self::prepare_short_value( $args['object_version'] ?? null, 100 ),
				'previous_value' => self::prepare_long_value( $args['previous_value'] ?? null ),
				'new_value'      => self::prepare_long_value( $args['new_value'] ?? null ),
				'user_id'        => isset( $args['user_id'] ) ? absint( $args['user_id'] ) : ( $user && $user->exists() ? absint( $user->ID ) : null ),
				'user_login'     => isset( $args['user_login'] ) ? self::prepare_short_value( $args['user_login'], 191 ) : ( $user && $user->exists() ? self::prepare_short_value( $user->user_login, 191 ) : null ),
				'ip_address'     => self::get_ip_address(),
				'request_uri'    => self::get_request_uri(),
				'source_file'    => self::prepare_long_value( $args['source_file'] ?? null ),
				'source_line'    => isset( $args['source_line'] ) ? absint( $args['source_line'] ) : null,
				'created_at'     => current_time( 'mysql' ),
			);

			$inserted = $wpdb->insert(
				$table_name,
				$data,
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
				)
			);

			if ( false === $inserted ) {
				return false;
			}

			return (int) $wpdb->insert_id;
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	/**
	 * Fetch recent events for the admin table.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_events( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'event_type' => '',
			'severity'   => '',
			'date_from'  => '',
			'date_from_full' => '',
			'date_to'    => '',
			'date_to_full' => '',
			'search'     => '',
			'limit'      => 100,
			'offset'     => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $args['event_type'] ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_key( $args['event_type'] );
		}

		if ( '' !== $args['severity'] ) {
			$where[]  = 'severity = %s';
			$values[] = sanitize_key( $args['severity'] );
		}

		if ( '' !== $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( '' !== $args['date_from_full'] ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from_full'] );
		}

		if ( '' !== $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		if ( '' !== $args['date_to_full'] ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to_full'] );
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(summary LIKE %s OR object_name LIKE %s OR user_login LIKE %s OR details LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$limit    = min( 200, max( 1, absint( $args['limit'] ) ) );
		$offset   = max( 0, absint( $args['offset'] ) );
		$values[] = $limit;
		$values[] = $offset;

		$sql = 'SELECT * FROM ' . self::table_name() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
	}

	/**
	 * Clean up old events based on retention settings.
	 *
	 * @return void
	 */
	public static function cleanup_retention() {
		global $wpdb;

		$settings = WP_Blackbox_Activator::get_settings();
		$days     = max( 1, absint( $settings['event_retention_days'] ?? 30 ) );
		$cutoff   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s',
				$cutoff
			)
		);
	}

	/**
	 * Prepare a short scalar string.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max_length Max length.
	 * @return string|null
	 */
	private static function prepare_short_value( $value, $max_length ) {
		if ( null === $value ) {
			return null;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( self::sanitize_deep( $value ) );
		}

		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		return substr( $value, 0, $max_length );
	}

	/**
	 * Prepare a value for LONGTEXT columns.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function prepare_long_value( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( self::sanitize_deep( $value ) );
		}

		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize nested data before JSON storage.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();

			foreach ( $value as $key => $item ) {
				$clean[ sanitize_key( (string) $key ) ] = self::sanitize_deep( $item );
			}

			return $clean;
		}

		if ( is_object( $value ) ) {
			return self::sanitize_deep( get_object_vars( $value ) );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Get a safe request URI.
	 *
	 * @return string|null
	 */
	private static function get_request_uri() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Get the remote IP without trusting proxy headers.
	 *
	 * @return string|null
	 */
	private static function get_ip_address() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
