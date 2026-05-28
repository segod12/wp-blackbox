<?php
/**
 * Admin incident timeline template.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_blackbox_pretty_json' ) ) {
	/**
	 * Pretty-print JSON when possible.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function wp_blackbox_pretty_json( $value ) {
		$decoded = json_decode( $value, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $value;
		}

		return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}

$issue_types      = WP_Blackbox_Advisor::issue_types();
$total_events     = count( $events );
$issue_count      = 0;
$critical_count   = 0;
$top_confidence   = 0;
$latest_issue     = '';

foreach ( $events as $event_for_stats ) {
	if ( in_array( $event_for_stats['event_type'], $issue_types, true ) ) {
		$issue_count++;
		$latest_issue = '' === $latest_issue ? $event_for_stats['summary'] : $latest_issue;
	}

	if ( 'critical' === $event_for_stats['severity'] ) {
		$critical_count++;
	}

	$event_advice = WP_Blackbox_Advisor::analyze_event( $event_for_stats );
	if ( ! empty( $event_advice['is_issue'] ) ) {
		$top_confidence = max( $top_confidence, (int) $event_advice['confidence'] );
	}
}
?>
<div class="wrap wp-blackbox">
	<div class="wp-blackbox-header">
		<div>
			<p class="wp-blackbox-kicker"><?php esc_html_e( 'Incident Recorder', 'wp-blackbox' ); ?></p>
			<h1><?php esc_html_e( 'Incident Timeline', 'wp-blackbox' ); ?></h1>
			<p><?php esc_html_e( 'Track what changed, what failed afterward, and what deserves the first investigation pass.', 'wp-blackbox' ); ?></p>
		</div>
	</div>

	<div class="wp-blackbox-command-grid" aria-label="<?php esc_attr_e( 'Incident summary', 'wp-blackbox' ); ?>">
		<div class="wp-blackbox-command-card">
			<span><?php esc_html_e( 'Events', 'wp-blackbox' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $total_events ) ); ?></strong>
			<small><?php esc_html_e( 'Current timeline view', 'wp-blackbox' ); ?></small>
		</div>
		<div class="wp-blackbox-command-card wp-blackbox-command-card-warning">
			<span><?php esc_html_e( 'Issues', 'wp-blackbox' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $issue_count ) ); ?></strong>
			<small><?php echo esc_html( $latest_issue ? $latest_issue : __( 'No issue detected in this view', 'wp-blackbox' ) ); ?></small>
		</div>
		<div class="wp-blackbox-command-card wp-blackbox-command-card-critical">
			<span><?php esc_html_e( 'Critical', 'wp-blackbox' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $critical_count ) ); ?></strong>
			<small><?php esc_html_e( 'Fatal or site-breaking signals', 'wp-blackbox' ); ?></small>
		</div>
		<div class="wp-blackbox-command-card wp-blackbox-command-card-confidence">
			<span><?php esc_html_e( 'Top Confidence', 'wp-blackbox' ); ?></span>
			<strong><?php echo esc_html( $top_confidence ); ?>/100</strong>
			<small><?php esc_html_e( 'Advisor evidence score', 'wp-blackbox' ); ?></small>
		</div>
	</div>

	<div class="wp-blackbox-panel">
		<form method="get" class="wp-blackbox-filters">
			<input type="hidden" name="page" value="wp-blackbox" />
			<select name="event_type">
				<option value=""><?php esc_html_e( 'All event types', 'wp-blackbox' ); ?></option>
				<?php foreach ( $event_types as $event_type ) : ?>
					<option value="<?php echo esc_attr( $event_type ); ?>" <?php selected( $filters['event_type'], $event_type ); ?>><?php echo esc_html( str_replace( '_', ' ', $event_type ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="severity">
				<option value=""><?php esc_html_e( 'All severities', 'wp-blackbox' ); ?></option>
				<?php foreach ( $severities as $severity ) : ?>
					<option value="<?php echo esc_attr( $severity ); ?>" <?php selected( $filters['severity'], $severity ); ?>><?php echo esc_html( ucfirst( $severity ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search plugin, theme, user...', 'wp-blackbox' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-blackbox' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-blackbox' ) ); ?>"><?php esc_html_e( 'Reset', 'wp-blackbox' ); ?></a>
		</form>
	</div>

	<div class="wp-blackbox-timeline">
		<?php if ( empty( $events ) ) : ?>
			<div class="wp-blackbox-empty"><?php esc_html_e( 'No events found yet. WP Blackbox will start recording monitored changes, issues, and performance signals as they happen.', 'wp-blackbox' ); ?></div>
		<?php else : ?>
			<?php foreach ( $events as $event ) : ?>
				<?php
				$timestamp = strtotime( $event['created_at'] );
				$context   = ! empty( $event['context'] ) ? wp_blackbox_pretty_json( $event['context'] ) : '';
				$advice    = WP_Blackbox_Advisor::analyze_event( $event );
				?>
				<details class="wp-blackbox-event wp-blackbox-event-<?php echo esc_attr( $event['severity'] ); ?>">
					<summary>
						<span class="wp-blackbox-event-time"><?php echo esc_html( $timestamp ? wp_date( 'M j, Y H:i:s', $timestamp ) : $event['created_at'] ); ?></span>
						<span class="wp-blackbox-severity wp-blackbox-severity-<?php echo esc_attr( $event['severity'] ); ?>"><?php echo esc_html( ucfirst( $event['severity'] ) ); ?></span>
						<span class="wp-blackbox-event-main">
							<strong><?php echo esc_html( $event['summary'] ); ?></strong>
							<small>
								<?php echo esc_html( str_replace( '_', ' ', $event['event_type'] ) ); ?>
								<?php if ( ! empty( $event['object_name'] ) ) : ?>
									<?php echo esc_html( ' / ' . $event['object_name'] ); ?>
								<?php endif; ?>
							</small>
						</span>
						<span class="wp-blackbox-event-user"><?php echo ! empty( $event['user_login'] ) ? esc_html( $event['user_login'] ) : esc_html__( 'System', 'wp-blackbox' ); ?></span>
					</summary>

					<div class="wp-blackbox-details-grid">
						<div class="wp-blackbox-detail-card wp-blackbox-detail-wide wp-blackbox-advisor">
							<h3><?php esc_html_e( 'Advisor', 'wp-blackbox' ); ?></h3>
							<?php if ( ! empty( $advice['is_issue'] ) ) : ?>
								<div class="wp-blackbox-advisor-score">
									<strong><?php echo esc_html( $advice['confidence'] ); ?>/100</strong>
									<span><?php esc_html_e( 'Confidence', 'wp-blackbox' ); ?></span>
								</div>
								<p><strong><?php esc_html_e( 'Likely cause:', 'wp-blackbox' ); ?></strong> <?php echo esc_html( $advice['likely_cause'] ); ?></p>
							<?php endif; ?>
							<p><?php echo esc_html( $advice['summary'] ); ?></p>

							<?php if ( ! empty( $advice['evidence'] ) ) : ?>
								<h4><?php esc_html_e( 'Evidence', 'wp-blackbox' ); ?></h4>
								<ul>
									<?php foreach ( $advice['evidence'] as $evidence ) : ?>
										<li><?php echo esc_html( $evidence ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( ! empty( $advice['related_events'] ) ) : ?>
								<h4><?php esc_html_e( 'Related Changes Before This Issue', 'wp-blackbox' ); ?></h4>
								<ul>
									<?php foreach ( $advice['related_events'] as $related_event ) : ?>
										<li><?php echo esc_html( sprintf( '[%s] %s', $related_event['created_at'], $related_event['summary'] ) ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( ! empty( $advice['suggested_actions'] ) ) : ?>
								<h4><?php esc_html_e( 'Suggested Next Actions', 'wp-blackbox' ); ?></h4>
								<ol>
									<?php foreach ( $advice['suggested_actions'] as $action ) : ?>
										<li><?php echo esc_html( $action ); ?></li>
									<?php endforeach; ?>
								</ol>
							<?php endif; ?>
						</div>

						<div class="wp-blackbox-detail-card">
							<h3><?php esc_html_e( 'Event', 'wp-blackbox' ); ?></h3>
							<dl>
								<dt><?php esc_html_e( 'Type', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['event_type'] ); ?></dd>
								<dt><?php esc_html_e( 'Object', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['object_name'] ? $event['object_name'] : '-' ); ?></dd>
								<dt><?php esc_html_e( 'Version', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['object_version'] ? $event['object_version'] : '-' ); ?></dd>
								<dt><?php esc_html_e( 'User', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['user_login'] ? $event['user_login'] : '-' ); ?></dd>
							</dl>
						</div>

						<div class="wp-blackbox-detail-card">
							<h3><?php esc_html_e( 'Request', 'wp-blackbox' ); ?></h3>
							<dl>
								<dt><?php esc_html_e( 'IP', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['ip_address'] ? $event['ip_address'] : '-' ); ?></dd>
								<dt><?php esc_html_e( 'URI', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['request_uri'] ? $event['request_uri'] : '-' ); ?></dd>
								<dt><?php esc_html_e( 'File', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['source_file'] ? $event['source_file'] : '-' ); ?></dd>
								<dt><?php esc_html_e( 'Line', 'wp-blackbox' ); ?></dt><dd><?php echo esc_html( $event['source_line'] ? $event['source_line'] : '-' ); ?></dd>
							</dl>
						</div>

						<?php if ( ! empty( $event['details'] ) || ! empty( $event['previous_value'] ) || ! empty( $event['new_value'] ) ) : ?>
							<div class="wp-blackbox-detail-card wp-blackbox-detail-wide">
								<h3><?php esc_html_e( 'Details', 'wp-blackbox' ); ?></h3>
								<?php if ( ! empty( $event['details'] ) ) : ?>
									<pre><?php echo esc_html( $event['details'] ); ?></pre>
								<?php endif; ?>
								<?php if ( ! empty( $event['previous_value'] ) || ! empty( $event['new_value'] ) ) : ?>
									<div class="wp-blackbox-diff">
										<div><strong><?php esc_html_e( 'Before', 'wp-blackbox' ); ?></strong><pre><?php echo esc_html( $event['previous_value'] ); ?></pre></div>
										<div><strong><?php esc_html_e( 'After', 'wp-blackbox' ); ?></strong><pre><?php echo esc_html( $event['new_value'] ); ?></pre></div>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $context ) ) : ?>
							<div class="wp-blackbox-detail-card wp-blackbox-detail-wide">
								<h3><?php esc_html_e( 'Full Context', 'wp-blackbox' ); ?></h3>
								<pre><?php echo esc_html( $context ); ?></pre>
							</div>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
