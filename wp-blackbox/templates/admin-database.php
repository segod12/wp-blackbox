<?php
/**
 * Database growth screen.
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
			<p class="wp-blackbox-kicker"><?php esc_html_e( 'Database Monitor', 'wp-blackbox' ); ?></p>
			<h1><?php esc_html_e( 'Database Growth', 'wp-blackbox' ); ?></h1>
			<p><?php esc_html_e( 'Find large or fast-growing tables and the plugin most likely responsible.', 'wp-blackbox' ); ?></p>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( $snapshot_url ); ?>"><?php esc_html_e( 'Run Snapshot Now', 'wp-blackbox' ); ?></a>
	</div>

	<?php if ( 'db_snapshot' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Database snapshot completed.', 'wp-blackbox' ); ?></p></div>
	<?php endif; ?>

	<div class="wp-blackbox-stats">
		<div><strong><?php echo esc_html( number_format_i18n( $summary['snapshot_count'] ) ); ?></strong><span><?php esc_html_e( 'Snapshots', 'wp-blackbox' ); ?></span></div>
		<div><strong><?php echo esc_html( $summary['last_snapshot'] ? $summary['last_snapshot'] : '-' ); ?></strong><span><?php esc_html_e( 'Last Snapshot', 'wp-blackbox' ); ?></span></div>
	</div>

	<div class="wp-blackbox-grid">
		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'Largest Tables', 'wp-blackbox' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Table', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Owner', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Rows', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Size', 'wp-blackbox' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $largest as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['table_name'] ); ?></strong></td>
							<td><?php echo esc_html( $row['owner'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['table_rows'] ) ); ?></td>
							<td><?php echo esc_html( $row['total_size_human'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $largest ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No snapshots yet. Run a snapshot to populate this view.', 'wp-blackbox' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="wp-blackbox-panel">
			<h2><?php esc_html_e( 'Fastest Growth', 'wp-blackbox' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Table', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Growth', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Percent', 'wp-blackbox' ); ?></th><th><?php esc_html_e( 'Owner', 'wp-blackbox' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $growth as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['table_name'] ); ?></strong></td>
							<td><?php echo esc_html( WP_Blackbox_Database_Collector::format_bytes( max( 0, (int) $row['growth_bytes'] ) ) ); ?></td>
							<td><?php echo esc_html( $row['growth_pct'] . '%' ); ?></td>
							<td><?php echo esc_html( $row['owner'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $growth ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'Need at least two snapshots to calculate growth.', 'wp-blackbox' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
