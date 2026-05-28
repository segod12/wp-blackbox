<?php
/**
 * Cron health screen.
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
			<p class="wp-blackbox-kicker"><?php esc_html_e( 'Background Jobs', 'wp-blackbox' ); ?></p>
			<h1><?php esc_html_e( 'Cron / Queue Health', 'wp-blackbox' ); ?></h1>
			<p><?php esc_html_e( 'Detect overdue WP-Cron jobs and WooCommerce Action Scheduler backlogs.', 'wp-blackbox' ); ?></p>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( $scan_url ); ?>"><?php esc_html_e( 'Run Diagnostics Now', 'wp-blackbox' ); ?></a>
	</div>

	<?php if ( 'cron_scan' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cron diagnostics completed.', 'wp-blackbox' ); ?></p></div>
	<?php endif; ?>

	<div class="wp-blackbox-grid">
		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'WP-Cron', 'wp-blackbox' ); ?></h2>
			<div class="wp-blackbox-stats wp-blackbox-stats-inline">
				<div><strong><?php echo $health['wp_cron']['disabled'] ? esc_html__( 'Disabled', 'wp-blackbox' ) : esc_html__( 'Enabled', 'wp-blackbox' ); ?></strong><span><?php esc_html_e( 'DISABLE_WP_CRON', 'wp-blackbox' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( $health['wp_cron']['total_events'] ) ); ?></strong><span><?php esc_html_e( 'Scheduled Events', 'wp-blackbox' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( $health['wp_cron']['overdue_count'] ) ); ?></strong><span><?php esc_html_e( 'Overdue', 'wp-blackbox' ); ?></span></div>
			</div>
			<?php if ( ! empty( $health['wp_cron']['overdue_hooks'] ) ) : ?>
				<h3><?php esc_html_e( 'Overdue Hooks', 'wp-blackbox' ); ?></h3>
				<ul class="wp-blackbox-list">
					<?php foreach ( $health['wp_cron']['overdue_hooks'] as $hook ) : ?>
						<li><?php echo esc_html( $hook ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'Action Scheduler', 'wp-blackbox' ); ?></h2>
			<?php if ( empty( $health['action_scheduler']['available'] ) ) : ?>
				<p><?php esc_html_e( 'Action Scheduler tables were not found.', 'wp-blackbox' ); ?></p>
			<?php else : ?>
				<div class="wp-blackbox-stats wp-blackbox-stats-inline">
					<div><strong><?php echo esc_html( number_format_i18n( $health['action_scheduler']['pending'] ) ); ?></strong><span><?php esc_html_e( 'Pending', 'wp-blackbox' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $health['action_scheduler']['failed'] ) ); ?></strong><span><?php esc_html_e( 'Failed', 'wp-blackbox' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $health['action_scheduler']['in_progress'] ) ); ?></strong><span><?php esc_html_e( 'In Progress', 'wp-blackbox' ); ?></span></div>
				</div>
				<h3><?php esc_html_e( 'Top Failed Hooks', 'wp-blackbox' ); ?></h3>
				<ul class="wp-blackbox-list">
					<?php foreach ( (array) $health['action_scheduler']['failed_hooks'] as $hook ) : ?>
						<li><?php echo esc_html( $hook['hook'] . ' - ' . number_format_i18n( (int) $hook['total'] ) ); ?></li>
					<?php endforeach; ?>
					<?php if ( empty( $health['action_scheduler']['failed_hooks'] ) ) : ?>
						<li><?php esc_html_e( 'No failed hooks found.', 'wp-blackbox' ); ?></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
