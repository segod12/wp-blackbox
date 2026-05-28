<?php
/**
 * Evidence-based incident advisor.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Advisor {
	/**
	 * Issue event types.
	 *
	 * @return array
	 */
	public static function issue_types() {
		return array( 'fatal_error', 'slow_request', 'slow_ajax', 'slow_rest', 'http_500', 'db_table_growth', 'cron_overdue', 'action_scheduler_failed', 'action_scheduler_pending_spike' );
	}

	/**
	 * Change event types.
	 *
	 * @return array
	 */
	public static function change_types() {
		return array( 'plugin_activated', 'plugin_deactivated', 'plugin_updated', 'theme_switched', 'theme_updated', 'core_updated', 'option_changed', 'admin_user_created', 'user_role_changed' );
	}

	/**
	 * Analyze a single event.
	 *
	 * @param array $event Event row.
	 * @return array
	 */
	public static function analyze_event( $event ) {
		$event_type = $event['event_type'] ?? '';
		$analysis   = array(
			'is_issue'          => in_array( $event_type, self::issue_types(), true ),
			'confidence'        => 0,
			'likely_cause'      => '',
			'summary'           => '',
			'evidence'          => array(),
			'suggested_actions' => self::suggested_actions( $event ),
			'related_events'    => array(),
		);

		if ( ! $analysis['is_issue'] ) {
			$analysis['summary'] = 'This is a recorded change or informational event. Watch for errors, slow requests, or queue issues after it.';
			return $analysis;
		}

		$analysis['confidence']   = self::base_score( $event );
		$analysis['likely_cause'] = self::cause_name( $event );
		$analysis['summary']      = self::issue_summary( $event );
		$analysis['evidence'][]   = $event['summary'] ?? '';

		$related = self::related_changes_before( $event );

		foreach ( $related as $related_event ) {
			$score = self::relationship_score( $related_event, $event );

			if ( $score <= 0 ) {
				continue;
			}

			$analysis['confidence'] += $score;
			$analysis['related_events'][] = $related_event;
			$analysis['evidence'][] = sprintf(
				'%s happened %d minutes before this issue.',
				$related_event['summary'],
				self::minutes_between( $related_event['created_at'], $event['created_at'] )
			);
		}

		$analysis['confidence'] = min( 95, max( 10, (int) $analysis['confidence'] ) );
		$analysis['evidence']   = array_values( array_filter( array_unique( $analysis['evidence'] ) ) );

		return $analysis;
	}

	/**
	 * Analyze a set of events and return ranked root-cause candidates.
	 *
	 * @param array $events Events ordered oldest to newest.
	 * @return array
	 */
	public static function analyze_events( $events ) {
		$candidates = array();

		foreach ( $events as $event ) {
			if ( ! in_array( $event['event_type'] ?? '', self::issue_types(), true ) ) {
				continue;
			}

			$analysis = self::analyze_event( $event );
			$key      = self::candidate_key( $event, $analysis );

			if ( ! isset( $candidates[ $key ] ) ) {
				$candidates[ $key ] = array(
					'key'               => $key,
					'name'              => $analysis['likely_cause'],
					'score'             => 0,
					'evidence'          => array(),
					'suggested_actions' => array(),
					'events'            => array(),
				);
			}

			$candidates[ $key ]['score'] += $analysis['confidence'];
			$candidates[ $key ]['evidence'] = array_merge( $candidates[ $key ]['evidence'], $analysis['evidence'] );
			$candidates[ $key ]['suggested_actions'] = array_merge( $candidates[ $key ]['suggested_actions'], $analysis['suggested_actions'] );
			$candidates[ $key ]['events'][] = $event;
		}

		foreach ( $candidates as &$candidate ) {
			$issue_count = max( 1, count( $candidate['events'] ) );
			$candidate['score'] = min( 95, (int) round( $candidate['score'] / $issue_count ) );
			$candidate['evidence'] = array_slice( array_values( array_unique( array_filter( $candidate['evidence'] ) ) ), 0, 6 );
			$candidate['suggested_actions'] = array_slice( array_values( array_unique( array_filter( $candidate['suggested_actions'] ) ) ), 0, 6 );
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $candidates, 0, 5 );
	}

	/**
	 * Get related changes before an issue.
	 *
	 * @param array $event Issue event.
	 * @return array
	 */
	public static function related_changes_before( $event ) {
		global $wpdb;

		if ( empty( $event['created_at'] ) ) {
			return array();
		}

		$event_time = strtotime( $event['created_at'] );
		$since      = date( 'Y-m-d H:i:s', $event_time - DAY_IN_SECONDS );
		$types      = self::change_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$values     = array_merge( $types, array( $since, $event['created_at'] ) );

		$sql = 'SELECT * FROM ' . WP_Blackbox_Logger::table_name() . "
			WHERE event_type IN ({$placeholders})
			AND created_at >= %s
			AND created_at <= %s
			ORDER BY created_at DESC
			LIMIT 20";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		return array_values(
			array_filter(
				(array) $rows,
				static function ( $row ) use ( $event ) {
					return WP_Blackbox_Advisor::relationship_score( $row, $event ) > 0;
				}
			)
		);
	}

	/**
	 * Suggested next actions for an event.
	 *
	 * @param array $event Event row.
	 * @return array
	 */
	public static function suggested_actions( $event ) {
		$type = $event['event_type'] ?? '';

		switch ( $type ) {
			case 'fatal_error':
				return array(
					'Open the fatal file and line shown in the event details.',
					'Check whether the related plugin/theme was updated or activated in the previous 24 hours.',
					'Test disabling or rolling back the suspected plugin/theme on staging first.',
					'Check PHP and WordPress version compatibility for the suspected component.',
					'Send the copied incident report to the plugin/theme vendor if the error is inside their code.',
				);
			case 'slow_ajax':
				return array(
					'Review the AJAX action name in the event context.',
					'Find which plugin registers that wp_ajax action.',
					'Check whether the action started after a recent plugin/theme update.',
					'Inspect server CPU, object cache, and database query load during the request.',
				);
			case 'slow_request':
			case 'slow_rest':
				return array(
					'Check the request URI and request type in the event context.',
					'Compare the slow request time with recent plugin/theme/core changes.',
					'Use Query Monitor or server logs on staging to inspect slow queries and hooks.',
					'Temporarily disable the suspected component on staging to confirm impact.',
				);
			case 'http_500':
				return array(
					'Check PHP error logs around the same timestamp.',
					'Look for a fatal_error event close to this HTTP 500 event.',
					'Review recent plugin/theme updates and option changes before the 500 response.',
				);
			case 'db_table_growth':
				return array(
					'Identify the likely owner shown in event details.',
					'Check failed jobs, logs, sessions, transients, or plugin-specific cleanup tools.',
					'Do not delete table data until a backup exists and the table owner is confirmed.',
				);
			case 'cron_overdue':
				return array(
					'Check whether DISABLE_WP_CRON is enabled.',
					'Confirm a real server cron is calling wp-cron.php if WP-Cron is disabled.',
					'Review overdue hook names and map them to the responsible plugin.',
				);
			case 'action_scheduler_failed':
			case 'action_scheduler_pending_spike':
				return array(
					'Open WooCommerce Status > Scheduled Actions if WooCommerce is installed.',
					'Review the failed or pending hook names and their plugin owner.',
					'Check whether the queue spike started after a WooCommerce or extension update.',
					'Do not bulk-delete actions until the failing hook is understood.',
				);
			default:
				return array(
					'Review related events before and after this timestamp.',
					'Use this event as evidence, not as guaranteed proof of cause.',
				);
		}
	}

	/**
	 * Relationship score between a prior change and an issue.
	 *
	 * @param array $change Change event.
	 * @param array $issue Issue event.
	 * @return int
	 */
	public static function relationship_score( $change, $issue ) {
		$change_type = $change['event_type'] ?? '';
		$issue_type  = $issue['event_type'] ?? '';

		if ( ! in_array( $change_type, self::change_types(), true ) || ! in_array( $issue_type, self::issue_types(), true ) ) {
			return 0;
		}

		if ( empty( $change['created_at'] ) || empty( $issue['created_at'] ) || strtotime( $change['created_at'] ) > strtotime( $issue['created_at'] ) ) {
			return 0;
		}

		$score = 0;

		if ( self::objects_match( $change, $issue ) ) {
			$score += 35;
		}

		if ( in_array( $change_type, array( 'plugin_updated', 'plugin_activated', 'theme_updated', 'theme_switched' ), true ) && in_array( $issue_type, array( 'fatal_error', 'http_500', 'slow_ajax', 'slow_request' ), true ) ) {
			$score += 20;
		}

		if ( 'option_changed' === $change_type && in_array( $issue_type, array( 'fatal_error', 'http_500', 'slow_request', 'slow_rest' ), true ) ) {
			$score += 12;
		}

		$minutes = self::minutes_between( $change['created_at'], $issue['created_at'] );
		if ( $minutes <= 15 ) {
			$score += 20;
		} elseif ( $minutes <= 60 ) {
			$score += 15;
		} elseif ( $minutes <= 360 ) {
			$score += 10;
		} elseif ( $minutes <= 1440 ) {
			$score += 5;
		}

		return min( 60, $score );
	}

	/**
	 * Base confidence by issue type.
	 *
	 * @param array $event Event.
	 * @return int
	 */
	private static function base_score( $event ) {
		switch ( $event['event_type'] ?? '' ) {
			case 'fatal_error':
				return 45;
			case 'http_500':
				return 32;
			case 'action_scheduler_failed':
			case 'action_scheduler_pending_spike':
				return 30;
			case 'slow_ajax':
				return 28;
			case 'slow_request':
			case 'slow_rest':
				return 22;
			case 'db_table_growth':
			case 'cron_overdue':
				return 20;
			default:
				return 10;
		}
	}

	/**
	 * Build cause name.
	 *
	 * @param array $event Event.
	 * @return string
	 */
	private static function cause_name( $event ) {
		if ( ! empty( $event['object_name'] ) ) {
			return $event['object_name'];
		}

		if ( ! empty( $event['object_type'] ) ) {
			return ucfirst( str_replace( '_', ' ', $event['object_type'] ) );
		}

		return str_replace( '_', ' ', $event['event_type'] ?? 'Unknown issue' );
	}

	/**
	 * Issue summary.
	 *
	 * @param array $event Event.
	 * @return string
	 */
	private static function issue_summary( $event ) {
		$type = $event['event_type'] ?? '';

		if ( 'fatal_error' === $type ) {
			return 'A fatal PHP error is usually strong evidence because it contains a source file and line.';
		}

		if ( in_array( $type, array( 'slow_request', 'slow_ajax', 'slow_rest' ), true ) ) {
			return 'A slow request is a performance symptom. Correlation with a recent change raises confidence.';
		}

		if ( 'db_table_growth' === $type ) {
			return 'Database growth can point to logs, queues, sessions, or plugin data bloat.';
		}

		if ( in_array( $type, array( 'cron_overdue', 'action_scheduler_failed', 'action_scheduler_pending_spike' ), true ) ) {
			return 'Background job issues often affect WooCommerce, emails, subscriptions, and imports.';
		}

		return 'This issue should be reviewed with related changes before and after the timestamp.';
	}

	/**
	 * Candidate key.
	 *
	 * @param array $event Event.
	 * @param array $analysis Analysis.
	 * @return string
	 */
	private static function candidate_key( $event, $analysis ) {
		if ( ! empty( $event['object_type'] ) && ! empty( $event['object_name'] ) ) {
			return sanitize_key( $event['object_type'] . '-' . $event['object_name'] );
		}

		return sanitize_key( $analysis['likely_cause'] );
	}

	/**
	 * Whether event objects appear connected.
	 *
	 * @param array $change Change event.
	 * @param array $issue Issue event.
	 * @return bool
	 */
	private static function objects_match( $change, $issue ) {
		$change_object = strtolower( (string) ( $change['object_name'] ?? '' ) );
		$issue_blob    = strtolower(
			(string) ( $issue['object_name'] ?? '' ) . ' ' .
			(string) ( $issue['summary'] ?? '' ) . ' ' .
			(string) ( $issue['details'] ?? '' ) . ' ' .
			(string) ( $issue['context'] ?? '' ) . ' ' .
			(string) ( $issue['source_file'] ?? '' )
		);

		if ( '' !== $change_object && false !== strpos( $issue_blob, $change_object ) ) {
			return true;
		}

		$change_context = strtolower( (string) ( $change['context'] ?? '' ) );

		if ( '' !== $change_context && ! empty( $issue['source_file'] ) ) {
			$source = strtolower( (string) $issue['source_file'] );
			return false !== strpos( $change_context, basename( $source ) );
		}

		return false;
	}

	/**
	 * Minutes between timestamps.
	 *
	 * @param string $before Earlier datetime.
	 * @param string $after Later datetime.
	 * @return int
	 */
	private static function minutes_between( $before, $after ) {
		return max( 0, (int) round( ( strtotime( $after ) - strtotime( $before ) ) / MINUTE_IN_SECONDS ) );
	}
}
