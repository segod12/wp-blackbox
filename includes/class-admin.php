<?php
/**
 * Admin screens.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_wp_blackbox_db_snapshot', array( __CLASS__, 'manual_db_snapshot' ) );
		add_action( 'admin_post_wp_blackbox_cron_scan', array( __CLASS__, 'manual_cron_scan' ) );
		add_action( 'admin_post_wp_blackbox_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'WP Blackbox', 'wp-blackbox' ),
			__( 'WP Blackbox', 'wp-blackbox' ),
			'manage_options',
			'wp-blackbox',
			array( __CLASS__, 'render_timeline' ),
			self::menu_icon(),
			58
		);

		add_submenu_page( 'wp-blackbox', __( 'Incident Timeline', 'wp-blackbox' ), __( 'Incident Timeline', 'wp-blackbox' ), 'manage_options', 'wp-blackbox', array( __CLASS__, 'render_timeline' ) );
		add_submenu_page( 'wp-blackbox', __( 'Database Growth', 'wp-blackbox' ), __( 'Database Growth', 'wp-blackbox' ), 'manage_options', 'wp-blackbox-database', array( __CLASS__, 'render_database' ) );
		add_submenu_page( 'wp-blackbox', __( 'Cron / Queue Health', 'wp-blackbox' ), __( 'Cron / Queue Health', 'wp-blackbox' ), 'manage_options', 'wp-blackbox-cron', array( __CLASS__, 'render_cron' ) );
		add_submenu_page( 'wp-blackbox', __( 'Reports', 'wp-blackbox' ), __( 'Reports', 'wp-blackbox' ), 'manage_options', 'wp-blackbox-reports', array( __CLASS__, 'render_reports' ) );
		add_submenu_page( 'wp-blackbox', __( 'Settings', 'wp-blackbox' ), __( 'Settings', 'wp-blackbox' ), 'manage_options', 'wp-blackbox-settings', array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * Load admin assets for all plugin pages.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'wp-blackbox' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-blackbox-admin', WP_BLACKBOX_URL . 'assets/admin.css', array(), WP_BLACKBOX_VERSION );
		wp_enqueue_script( 'wp-blackbox-admin', WP_BLACKBOX_URL . 'assets/admin.js', array(), WP_BLACKBOX_VERSION, true );
	}

	/**
	 * Run database snapshot manually.
	 *
	 * @return void
	 */
	public static function manual_db_snapshot() {
		self::require_admin_action( 'wp_blackbox_db_snapshot' );
		WP_Blackbox_Database_Collector::collect_snapshot();
		self::redirect( 'wp-blackbox-database', 'db_snapshot' );
	}

	/**
	 * Run cron diagnostics manually.
	 *
	 * @return void
	 */
	public static function manual_cron_scan() {
		self::require_admin_action( 'wp_blackbox_cron_scan' );
		WP_Blackbox_Cron_Collector::run_diagnostics();
		self::redirect( 'wp-blackbox-cron', 'cron_scan' );
	}

	/**
	 * Save plugin settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		self::require_admin_action( 'wp_blackbox_save_settings' );

		$settings = array(
			'enable_change_tracking'          => isset( $_POST['enable_change_tracking'] ) ? 1 : 0,
			'enable_fatal_error_tracking'    => isset( $_POST['enable_fatal_error_tracking'] ) ? 1 : 0,
			'enable_slow_request_tracking'   => isset( $_POST['enable_slow_request_tracking'] ) ? 1 : 0,
			'enable_database_growth_tracking' => isset( $_POST['enable_database_growth_tracking'] ) ? 1 : 0,
			'enable_cron_tracking'           => isset( $_POST['enable_cron_tracking'] ) ? 1 : 0,
			'event_retention_days'           => max( 1, absint( $_POST['event_retention_days'] ?? 30 ) ),
			'slow_frontend_threshold'        => max( 0.1, (float) ( $_POST['slow_frontend_threshold'] ?? 3 ) ),
			'slow_admin_threshold'           => max( 0.1, (float) ( $_POST['slow_admin_threshold'] ?? 5 ) ),
			'slow_ajax_threshold'            => max( 0.1, (float) ( $_POST['slow_ajax_threshold'] ?? 2 ) ),
			'slow_rest_threshold'            => max( 0.1, (float) ( $_POST['slow_rest_threshold'] ?? 2 ) ),
			'slow_cron_threshold'            => max( 0.1, (float) ( $_POST['slow_cron_threshold'] ?? 10 ) ),
			'delete_data_on_uninstall'       => isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
		);

		update_option( 'wp_blackbox_settings', $settings, false );

		self::redirect( 'wp-blackbox-settings', 'settings_saved' );
	}

	/**
	 * Render timeline.
	 *
	 * @return void
	 */
	public static function render_timeline() {
		self::require_admin_page();

		$filters = array(
			'event_type' => isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '',
			'severity'   => isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '',
			'date_from'  => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'    => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'limit'      => 150,
		);

		$events           = WP_Blackbox_Logger::get_events( $filters );
		$event_types      = WP_Blackbox_Event_Types::event_types();
		$severities       = WP_Blackbox_Event_Types::severities();
		$notice           = self::notice();

		require WP_BLACKBOX_DIR . 'templates/admin-timeline.php';
	}

	/**
	 * Render database page.
	 *
	 * @return void
	 */
	public static function render_database() {
		self::require_admin_page();

		$summary      = WP_Blackbox_Database_Collector::summary();
		$largest      = WP_Blackbox_Database_Collector::largest_tables( 30 );
		$growth       = WP_Blackbox_Database_Collector::fastest_growth( 30 );
		$snapshot_url = wp_nonce_url( admin_url( 'admin-post.php?action=wp_blackbox_db_snapshot' ), 'wp_blackbox_db_snapshot' );
		$notice       = self::notice();

		require WP_BLACKBOX_DIR . 'templates/admin-database.php';
	}

	/**
	 * Render cron diagnostics page.
	 *
	 * @return void
	 */
	public static function render_cron() {
		self::require_admin_page();

		$health   = WP_Blackbox_Cron_Collector::health();
		$scan_url = wp_nonce_url( admin_url( 'admin-post.php?action=wp_blackbox_cron_scan' ), 'wp_blackbox_cron_scan' );
		$notice   = self::notice();

		require WP_BLACKBOX_DIR . 'templates/admin-cron.php';
	}

	/**
	 * Render reports page.
	 *
	 * @return void
	 */
	public static function render_reports() {
		self::require_admin_page();

		$range     = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '24h';
		$report    = WP_Blackbox_Report_Generator::generate( $range );
		$markdown  = WP_Blackbox_Report_Generator::to_markdown( $report );
		$ranges    = array( '1h' => 'Last 1 hour', '6h' => 'Last 6 hours', '24h' => 'Last 24 hours', '7d' => 'Last 7 days' );
		$notice    = self::notice();

		require WP_BLACKBOX_DIR . 'templates/admin-reports.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		self::require_admin_page();

		$settings = WP_Blackbox_Activator::get_settings();
		$notice   = self::notice();

		require WP_BLACKBOX_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Capability and nonce guard for actions.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private static function require_admin_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-blackbox' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Capability guard for pages.
	 *
	 * @return void
	 */
	private static function require_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-blackbox' ) );
		}
	}

	/**
	 * Redirect to plugin page with a notice key.
	 *
	 * @param string $page Page slug.
	 * @param string $notice Notice key.
	 * @return void
	 */
	private static function redirect( $page, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => $page,
					'wp_blackbox_done' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get current notice key.
	 *
	 * @return string
	 */
	private static function notice() {
		return isset( $_GET['wp_blackbox_done'] ) ? sanitize_key( wp_unslash( $_GET['wp_blackbox_done'] ) ) : '';
	}

	/**
	 * Custom sidebar icon.
	 *
	 * @return string
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true"><rect x="7" y="9" width="50" height="46" rx="10" fill="none" stroke="#a7aaad" stroke-width="5"/><path d="M18 24h28M18 35h14M39 35h7" fill="none" stroke="#a7aaad" stroke-width="5" stroke-linecap="round"/><circle cx="46" cy="45" r="5" fill="#00a6a6"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
