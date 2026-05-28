<?php
/**
 * Reports screen.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wp-blackbox">
	<div class="wp-blackbox-header">
		<div>
			<p class="wp-blackbox-kicker"><?php esc_html_e( 'Root Cause Review', 'wp-blackbox' ); ?></p>
			<h1><?php esc_html_e( 'Incident Report', 'wp-blackbox' ); ?></h1>
			<p><?php esc_html_e( 'Summarize what changed before errors, slow requests, database growth, or queue problems appeared.', 'wp-blackbox' ); ?></p>
		</div>
	</div>

	<div class="wp-blackbox-panel">
		<form method="get" class="wp-blackbox-filters">
			<input type="hidden" name="page" value="wp-blackbox-reports" />
			<select name="range">
				<?php foreach ( $ranges as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $range, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Generate Report', 'wp-blackbox' ); ?></button>
		</form>
	</div>

	<div class="wp-blackbox-grid">
		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'Summary', 'wp-blackbox' ); ?></h2>
			<p><?php echo esc_html( $report['summary'] ); ?></p>
			<div class="wp-blackbox-stats wp-blackbox-stats-inline">
				<div><strong><?php echo esc_html( count( $report['events'] ) ); ?></strong><span><?php esc_html_e( 'Events', 'wp-blackbox' ); ?></span></div>
				<div><strong><?php echo esc_html( count( $report['changes'] ) ); ?></strong><span><?php esc_html_e( 'Changes', 'wp-blackbox' ); ?></span></div>
				<div><strong><?php echo esc_html( count( $report['issues'] ) ); ?></strong><span><?php esc_html_e( 'Issues Afterward', 'wp-blackbox' ); ?></span></div>
			</div>
		</div>

		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'Possible Root Causes', 'wp-blackbox' ); ?></h2>
			<?php if ( empty( $report['root_causes'] ) ) : ?>
				<p><?php esc_html_e( 'No strong candidates were found in this range.', 'wp-blackbox' ); ?></p>
			<?php else : ?>
				<?php foreach ( $report['root_causes'] as $cause ) : ?>
					<div class="wp-blackbox-cause">
						<div><strong><?php echo esc_html( $cause['name'] ); ?></strong><span><?php echo esc_html( $cause['score'] ); ?>/100</span></div>
						<h3><?php esc_html_e( 'Evidence', 'wp-blackbox' ); ?></h3>
						<ul>
							<?php foreach ( $cause['evidence'] as $evidence ) : ?>
								<li><?php echo esc_html( $evidence ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( ! empty( $cause['suggested_actions'] ) ) : ?>
							<h3><?php esc_html_e( 'Suggested Actions', 'wp-blackbox' ); ?></h3>
							<ol>
								<?php foreach ( $cause['suggested_actions'] as $action ) : ?>
									<li><?php echo esc_html( $action ); ?></li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="wp-blackbox-panel">
		<h2><?php esc_html_e( 'Markdown Export', 'wp-blackbox' ); ?></h2>
		<textarea class="wp-blackbox-report" readonly><?php echo esc_textarea( $markdown ); ?></textarea>
	</div>
</div>
