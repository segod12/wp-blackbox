<?php
/**
 * Uninstall handler.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wp_blackbox_settings', array() );

if ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) ) {
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'blackbox_events' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'blackbox_table_snapshots' );
	delete_option( 'wp_blackbox_settings' );
}
