<?php
/**
 * Incident report generator and correlation helper.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Report_Generator {
	/**
	 * Generate an incident report for a time range.
	 *
	 * @param string $range Range key.
	 * @return array
	 */
	public static function generate( $range = '24h' ) {
		$since  = self::range_start( $range );
		$events = WP_Blackbox_Logger::get_events(
			array(
				'date_from_full' => $since,
				'limit'          => 200,
			)
		);

		$events = array_reverse( $events );

		$change_types = WP_Blackbox_Advisor::change_types();
		$issue_types  = WP_Blackbox_Advisor::issue_types();
		$changes      = array_values( array_filter( $events, static function ( $event ) use ( $change_types ) { return in_array( $event['event_type'], $change_types, true ); } ) );
		$issues       = array_values( array_filter( $events, static function ( $event ) use ( $issue_types ) { return in_array( $event['event_type'], $issue_types, true ); } ) );

		return array(
			'range'       => $range,
			'since'       => $since,
			'events'      => $events,
			'changes'     => $changes,
			'issues'      => $issues,
			'root_causes' => WP_Blackbox_Advisor::analyze_events( $events ),
			'summary'     => self::summary( $events, $changes, $issues ),
		);
	}

	/**
	 * Build a readable Markdown report.
	 *
	 * @param array $report Report array.
	 * @return string
	 */
	public static function to_markdown( $report ) {
		$lines = array(
			'# WP Blackbox Incident Report',
			'',
			'Range: ' . $report['range'],
			'Since: ' . $report['since'],
			'',
			'## Summary',
			$report['summary'],
			'',
			'## Possible Root Causes',
		);

		if ( empty( $report['root_causes'] ) ) {
			$lines[] = 'No strong root-cause candidates were found in this range.';
		} else {
			foreach ( $report['root_causes'] as $index => $cause ) {
				$lines[] = sprintf( '%d. %s - %d/100 confidence', $index + 1, $cause['name'], $cause['score'] );
				$lines[] = '   Evidence: ' . implode( '; ', $cause['evidence'] );
				$lines[] = '   Suggested next actions: ' . implode( '; ', $cause['suggested_actions'] );
			}
		}

		$lines[] = '';
		$lines[] = '## Critical Errors And Warnings';
		foreach ( $report['issues'] as $event ) {
			$lines[] = sprintf( '- [%s] %s: %s', $event['created_at'], $event['event_type'], $event['summary'] );
		}

		$lines[] = '';
		$lines[] = '## Recent Changes';
		foreach ( $report['changes'] as $event ) {
			$lines[] = sprintf( '- [%s] %s: %s', $event['created_at'], $event['event_type'], $event['summary'] );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Convert range key to start datetime.
	 *
	 * @param string $range Range key.
	 * @return string
	 */
	private static function range_start( $range ) {
		$seconds = array(
			'1h'  => HOUR_IN_SECONDS,
			'6h'  => 6 * HOUR_IN_SECONDS,
			'24h' => DAY_IN_SECONDS,
			'7d'  => 7 * DAY_IN_SECONDS,
		);

		$delta = $seconds[ $range ] ?? DAY_IN_SECONDS;

		return date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $delta );
	}

	/**
	 * One-line report summary.
	 *
	 * @param array $events Events.
	 * @param array $changes Changes.
	 * @param array $issues Issues.
	 * @return string
	 */
	private static function summary( $events, $changes, $issues ) {
		return sprintf(
			'%d events found: %d changes and %d possible issues. Review highest-confidence root causes first; all causes are evidence-based guesses, not guarantees.',
			count( $events ),
			count( $changes ),
			count( $issues )
		);
	}
}
