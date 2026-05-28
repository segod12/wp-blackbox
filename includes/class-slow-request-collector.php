<?php
/**
 * Slow request collector.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Slow_Request_Collector {
	/**
	 * Register shutdown handler.
	 *
	 * @return void
	 */
	public static function register() {
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Log slow requests above threshold.
	 *
	 * @return void
	 */
	public static function handle_shutdown() {
		if ( ! defined( 'WP_BLACKBOX_REQUEST_START' ) ) {
			return;
		}

		$duration     = microtime( true ) - WP_BLACKBOX_REQUEST_START;
		$request_type = self::request_type();
		$threshold    = self::threshold_for( $request_type );
		$status_code  = function_exists( 'http_response_code' ) ? (int) http_response_code() : 0;

		if ( $status_code >= 500 ) {
			WP_Blackbox_Logger::log(
				'http_500',
				sprintf( 'HTTP %d response detected on %s request', $status_code, $request_type ),
				array(
					'severity'    => 'error',
					'object_type' => 'request',
					'object_name' => $request_type,
					'context'     => self::request_context( $duration, $request_type, $status_code ),
				)
			);
		}

		if ( $duration < $threshold ) {
			return;
		}

		$event_type = 'slow_request';
		if ( 'ajax' === $request_type ) {
			$event_type = 'slow_ajax';
		} elseif ( 'rest' === $request_type ) {
			$event_type = 'slow_rest';
		}

		WP_Blackbox_Logger::log(
			$event_type,
			sprintf( 'Slow %s request detected: %.2fs', $request_type, $duration ),
			array(
				'severity'    => 'warning',
				'object_type' => 'request',
				'object_name' => $request_type,
				'context'     => self::request_context( $duration, $request_type, $status_code ),
			)
		);
	}

	/**
	 * Determine the current request type.
	 *
	 * @return string
	 */
	public static function request_type() {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return 'ajax';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}

		if ( false !== strpos( $_SERVER['REQUEST_URI'] ?? '', 'wp-login.php' ) ) {
			return 'login';
		}

		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}

	/**
	 * Resolve threshold for a request type.
	 *
	 * @param string $request_type Request type.
	 * @return float
	 */
	private static function threshold_for( $request_type ) {
		$settings = WP_Blackbox_Activator::get_settings();
		$map      = array(
			'frontend' => (float) $settings['slow_frontend_threshold'],
			'admin'    => (float) $settings['slow_admin_threshold'],
			'ajax'     => (float) $settings['slow_ajax_threshold'],
			'rest'     => (float) $settings['slow_rest_threshold'],
			'cron'     => (float) $settings['slow_cron_threshold'],
			'login'    => (float) $settings['slow_admin_threshold'],
		);

		$threshold = $map[ $request_type ] ?? 5.0;

		return (float) apply_filters( 'wp_blackbox_slow_request_threshold', $threshold, $request_type );
	}

	/**
	 * Build request context without storing sensitive payloads.
	 *
	 * @param float  $duration Request duration.
	 * @param string $request_type Request type.
	 * @param int    $status_code HTTP status code.
	 * @return array
	 */
	private static function request_context( $duration, $request_type, $status_code ) {
		$context = array(
			'request_type' => $request_type,
			'duration'     => round( $duration, 4 ),
			'memory_peak'  => memory_get_peak_usage(),
			'status_code'  => $status_code,
			'method'       => sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ),
			'request_uri'  => esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
		);

		if ( 'ajax' === $request_type ) {
			$context['ajax_action'] = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
			$context['post_size']   = isset( $_SERVER['CONTENT_LENGTH'] ) ? absint( $_SERVER['CONTENT_LENGTH'] ) : 0;
		}

		if ( 'rest' === $request_type && function_exists( 'rest_get_url_prefix' ) ) {
			$context['rest_route'] = sanitize_text_field( wp_unslash( $_GET['rest_route'] ?? '' ) );
		}

		return $context;
	}
}
