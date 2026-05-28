<?php
/**
 * Settings screen.
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
			<p class="wp-blackbox-kicker"><?php esc_html_e( 'Configuration', 'wp-blackbox' ); ?></p>
			<h1><?php esc_html_e( 'Settings', 'wp-blackbox' ); ?></h1>
			<p><?php esc_html_e( 'Control retention, collectors, and slow request thresholds.', 'wp-blackbox' ); ?></p>
		</div>
	</div>

	<?php if ( 'settings_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-blackbox' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-blackbox-panel wp-blackbox-settings">
		<input type="hidden" name="action" value="wp_blackbox_save_settings" />
		<?php wp_nonce_field( 'wp_blackbox_save_settings' ); ?>

		<h2><?php esc_html_e( 'Collectors', 'wp-blackbox' ); ?></h2>
		<label><input type="checkbox" name="enable_change_tracking" <?php checked( $settings['enable_change_tracking'] ); ?> /> <?php esc_html_e( 'Track plugin, theme, option, and user changes', 'wp-blackbox' ); ?></label>
		<label><input type="checkbox" name="enable_fatal_error_tracking" <?php checked( $settings['enable_fatal_error_tracking'] ); ?> /> <?php esc_html_e( 'Track fatal PHP errors', 'wp-blackbox' ); ?></label>
		<label><input type="checkbox" name="enable_slow_request_tracking" <?php checked( $settings['enable_slow_request_tracking'] ); ?> /> <?php esc_html_e( 'Track slow requests', 'wp-blackbox' ); ?></label>
		<label><input type="checkbox" name="enable_database_growth_tracking" <?php checked( $settings['enable_database_growth_tracking'] ); ?> /> <?php esc_html_e( 'Track database growth', 'wp-blackbox' ); ?></label>
		<label><input type="checkbox" name="enable_cron_tracking" <?php checked( $settings['enable_cron_tracking'] ); ?> /> <?php esc_html_e( 'Track cron and Action Scheduler health', 'wp-blackbox' ); ?></label>

		<h2><?php esc_html_e( 'Retention', 'wp-blackbox' ); ?></h2>
		<label><?php esc_html_e( 'Event retention days', 'wp-blackbox' ); ?><input type="number" min="1" name="event_retention_days" value="<?php echo esc_attr( $settings['event_retention_days'] ); ?>" /></label>
		<label><input type="checkbox" name="delete_data_on_uninstall" <?php checked( $settings['delete_data_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete WP Blackbox data on uninstall', 'wp-blackbox' ); ?></label>

		<h2><?php esc_html_e( 'Slow Request Thresholds', 'wp-blackbox' ); ?></h2>
		<div class="wp-blackbox-settings-grid">
			<label><?php esc_html_e( 'Frontend seconds', 'wp-blackbox' ); ?><input type="number" min="0.1" step="0.1" name="slow_frontend_threshold" value="<?php echo esc_attr( $settings['slow_frontend_threshold'] ); ?>" /></label>
			<label><?php esc_html_e( 'Admin seconds', 'wp-blackbox' ); ?><input type="number" min="0.1" step="0.1" name="slow_admin_threshold" value="<?php echo esc_attr( $settings['slow_admin_threshold'] ); ?>" /></label>
			<label><?php esc_html_e( 'AJAX seconds', 'wp-blackbox' ); ?><input type="number" min="0.1" step="0.1" name="slow_ajax_threshold" value="<?php echo esc_attr( $settings['slow_ajax_threshold'] ); ?>" /></label>
			<label><?php esc_html_e( 'REST seconds', 'wp-blackbox' ); ?><input type="number" min="0.1" step="0.1" name="slow_rest_threshold" value="<?php echo esc_attr( $settings['slow_rest_threshold'] ); ?>" /></label>
			<label><?php esc_html_e( 'Cron seconds', 'wp-blackbox' ); ?><input type="number" min="0.1" step="0.1" name="slow_cron_threshold" value="<?php echo esc_attr( $settings['slow_cron_threshold'] ); ?>" /></label>
		</div>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-blackbox' ); ?></button></p>
	</form>
</div>
