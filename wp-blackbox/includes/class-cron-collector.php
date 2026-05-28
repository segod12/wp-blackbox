<?php
/**
 * Cron and Action Scheduler diagnostics.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Cron_Collector {
	/**
	 * Register scheduled diagnostics.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_blackbox_hourly_cron_diagnostics', array( __CLASS__, 'run_diagnostics' ) );
	}

	/**
	 * Run diagnostics and log warning/error events.
	 *
	 * @return array
	 */
	public static function run_diagnostics() {
		$health = self::health();

		if ( $health['wp_cron']['disabled'] ) {
			WP_Blackbox_Logger::log(
				'cron_disabled',
				'WP-Cron is disabled by DISABLE_WP_CRON.',
				array(
					'severity'    => 'notice',
					'object_type' => 'cron',
					'object_name' => 'wp-cron',
					'details'     => 'This is fine when a real server cron runs wp-cron.php. If no server cron exists, scheduled tasks may stop.',
					'context'     => $health['wp_cron'],
				)
			);
		}

		if ( $health['wp_cron']['overdue_count'] > 10 ) {
			WP_Blackbox_Logger::log(
				'cron_overdue',
				sprintf( 'WP-Cron has %d overdue scheduled events.', $health['wp_cron']['overdue_count'] ),
				array(
					'severity'    => 'warning',
					'object_type' => 'cron',
					'object_name' => 'wp-cron',
					'context'     => $health['wp_cron'],
				)
			);
		}

		if ( $health['action_scheduler']['available'] ) {
			if ( $health['action_scheduler']['pending'] > 1000 ) {
				WP_Blackbox_Logger::log(
					'action_scheduler_pending_spike',
					sprintf( 'Action Scheduler has %d pending actions.', $health['action_scheduler']['pending'] ),
					array(
						'severity'    => 'warning',
						'object_type' => 'queue',
						'object_name' => 'Action Scheduler',
						'context'     => $health['action_scheduler'],
					)
				);
			}

			if ( $health['action_scheduler']['failed'] > 50 ) {
				WP_Blackbox_Logger::log(
					'action_scheduler_failed',
					sprintf( 'Action Scheduler has %d failed actions.', $health['action_scheduler']['failed'] ),
					array(
						'severity'    => 'error',
						'object_type' => 'queue',
						'object_name' => 'Action Scheduler',
						'context'     => $health['action_scheduler'],
					)
				);
			}
		}

		return $health;
	}

	/**
	 * Build current cron/action scheduler status.
	 *
	 * @return array
	 */
	public static function health() {
		return array(
			'wp_cron'          => self::wp_cron_health(),
			'action_scheduler' => self::action_scheduler_health(),
		);
	}

	/**
	 * Get WP-Cron health.
	 *
	 * @return array
	 */
	private static function wp_cron_health() {
		$events        = _get_cron_array();
		$now           = time();
		$total         = 0;
		$overdue       = 0;
		$oldest        = 0;
		$overdue_hooks = array();

		if ( is_array( $events ) ) {
			foreach ( $events as $timestamp => $hooks ) {
				foreach ( (array) $hooks as $hook => $entries ) {
					$total += count( (array) $entries );

					if ( (int) $timestamp < $now - ( 5 * MINUTE_IN_SECONDS ) ) {
						$overdue += count( (array) $entries );
						$oldest   = 0 === $oldest ? (int) $timestamp : min( $oldest, (int) $timestamp );

						if ( count( $overdue_hooks ) < 10 ) {
							$overdue_hooks[] = $hook;
						}
					}
				}
			}
		}

		return array(
			'disabled'      => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'total_events'  => $total,
			'overdue_count' => $overdue,
			'oldest_due'    => $oldest ? wp_date( 'Y-m-d H:i:s', $oldest ) : '',
			'overdue_hooks' => array_values( array_unique( $overdue_hooks ) ),
		);
	}

	/**
	 * Get Action Scheduler health if tables are present.
	 *
	 * @return array
	 */
	private static function action_scheduler_health() {
		global $wpdb;

		$table = $wpdb->prefix . 'actionscheduler_actions';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $found !== $table ) {
			return array(
				'available' => false,
				'pending'   => 0,
				'failed'    => 0,
			);
		}

		$statuses = $wpdb->get_results(
			'SELECT status, COUNT(*) AS total FROM ' . $table . ' GROUP BY status',
			ARRAY_A
		);

		$count_by_status = array();
		foreach ( (array) $statuses as $status ) {
			$count_by_status[ $status['status'] ] = (int) $status['total'];
		}

		$failed_hooks = $wpdb->get_results(
			'SELECT hook, COUNT(*) AS total FROM ' . $table . ' WHERE status = "failed" GROUP BY hook ORDER BY total DESC LIMIT 10',
			ARRAY_A
		);

		$pending_hooks = $wpdb->get_results(
			'SELECT hook, COUNT(*) AS total FROM ' . $table . ' WHERE status = "pending" GROUP BY hook ORDER BY total DESC LIMIT 10',
			ARRAY_A
		);

		$oldest_pending = $wpdb->get_var( 'SELECT MIN(scheduled_date_gmt) FROM ' . $table . ' WHERE status = "pending"' );

		return array(
			'available'      => true,
			'pending'        => $count_by_status['pending'] ?? 0,
			'failed'         => $count_by_status['failed'] ?? 0,
			'complete'       => $count_by_status['complete'] ?? 0,
			'in_progress'    => $count_by_status['in-progress'] ?? 0,
			'oldest_pending' => $oldest_pending,
			'failed_hooks'   => $failed_hooks,
			'pending_hooks'  => $pending_hooks,
		);
	}
}
