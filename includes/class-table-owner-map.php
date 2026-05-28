<?php
/**
 * Maps known database table patterns to likely owners.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Table_Owner_Map {
	/**
	 * Guess the likely owner for a database table.
	 *
	 * @param string $table_name Table name.
	 * @return string
	 */
	public static function owner_for( $table_name ) {
		global $wpdb;

		$table = strtolower( (string) $table_name );
		$base  = strtolower( $wpdb->prefix );

		$patterns = array(
			$base . 'woocommerce_'      => 'WooCommerce',
			$base . 'wc_'               => 'WooCommerce',
			$base . 'actionscheduler_'  => 'Action Scheduler / WooCommerce',
			$base . 'wf'                => 'Wordfence',
			$base . 'rank_math'         => 'Rank Math',
			$base . 'yoast'             => 'Yoast SEO',
			$base . 'e_events'          => 'Elementor',
			$base . 'amelia'            => 'Amelia',
			$base . 'frm_'              => 'Formidable Forms',
			$base . 'gf_'               => 'Gravity Forms',
			$base . 'wpforms_'          => 'WPForms',
			$base . 'redirection_'      => 'Redirection',
			$base . 'postmeta'          => 'WordPress core / many plugins',
			$base . 'options'           => 'WordPress core / many plugins',
			$base . 'posts'             => 'WordPress core',
			$base . 'users'             => 'WordPress core',
			$base . 'usermeta'          => 'WordPress core / many plugins',
			$base . 'comments'          => 'WordPress core',
			$base . 'commentmeta'       => 'WordPress core / many plugins',
			$base . 'terms'             => 'WordPress core',
			$base . 'term_'             => 'WordPress core',
		);

		foreach ( $patterns as $prefix => $owner ) {
			if ( 0 === strpos( $table, $prefix ) ) {
				return $owner;
			}
		}

		return 'Unknown';
	}
}
