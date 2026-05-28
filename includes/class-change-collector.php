<?php
/**
 * Plugin, theme, and core change collector.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Change_Collector {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'activated_plugin', array( __CLASS__, 'plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'plugin_deactivated' ), 10, 2 );
		add_action( 'switch_theme', array( __CLASS__, 'theme_switched' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'upgrader_process_complete' ), 10, 2 );
	}

	/**
	 * Log plugin activation.
	 *
	 * @param string $plugin Plugin file.
	 * @param bool   $network_wide Network activation flag.
	 * @return void
	 */
	public static function plugin_activated( $plugin, $network_wide = false ) {
		$data = self::get_plugin_data( $plugin );

		WP_Blackbox_Logger::log(
			'plugin_activated',
			sprintf( 'Plugin activated: %s', $data['name'] ),
			array(
				'severity'       => 'info',
				'object_type'    => 'plugin',
				'object_name'    => $plugin,
				'object_version' => $data['version'],
				'context'        => array(
					'plugin_file'  => $plugin,
					'plugin_name'  => $data['name'],
					'network_wide' => (bool) $network_wide,
				),
			)
		);
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @param string $plugin Plugin file.
	 * @param bool   $network_wide Network deactivation flag.
	 * @return void
	 */
	public static function plugin_deactivated( $plugin, $network_wide = false ) {
		$data = self::get_plugin_data( $plugin );

		WP_Blackbox_Logger::log(
			'plugin_deactivated',
			sprintf( 'Plugin deactivated: %s', $data['name'] ),
			array(
				'severity'       => 'notice',
				'object_type'    => 'plugin',
				'object_name'    => $plugin,
				'object_version' => $data['version'],
				'context'        => array(
					'plugin_file'  => $plugin,
					'plugin_name'  => $data['name'],
					'network_wide' => (bool) $network_wide,
				),
			)
		);
	}

	/**
	 * Log theme switches.
	 *
	 * @param string   $new_name New theme name.
	 * @param WP_Theme $new_theme New theme object.
	 * @param WP_Theme $old_theme Old theme object.
	 * @return void
	 */
	public static function theme_switched( $new_name, $new_theme, $old_theme ) {
		$old_name = $old_theme instanceof WP_Theme ? $old_theme->get( 'Name' ) : '';

		WP_Blackbox_Logger::log(
			'theme_switched',
			sprintf( 'Theme switched: %s to %s', $old_name ? $old_name : 'Unknown', $new_name ),
			array(
				'severity'       => 'notice',
				'object_type'    => 'theme',
				'object_name'    => $new_theme instanceof WP_Theme ? $new_theme->get_stylesheet() : sanitize_title( $new_name ),
				'object_version' => $new_theme instanceof WP_Theme ? $new_theme->get( 'Version' ) : '',
				'previous_value' => $old_name,
				'new_value'      => $new_name,
				'context'        => array(
					'old_theme' => $old_name,
					'new_theme' => $new_name,
				),
			)
		);
	}

	/**
	 * Log completed plugin/theme/core updates.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $hook_extra Upgrader context.
	 * @return void
	 */
	public static function upgrader_process_complete( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] || empty( $hook_extra['type'] ) ) {
			return;
		}

		if ( 'plugin' === $hook_extra['type'] && ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			foreach ( $hook_extra['plugins'] as $plugin ) {
				$data = self::get_plugin_data( $plugin );
				WP_Blackbox_Logger::log(
					'plugin_updated',
					sprintf( 'Plugin updated: %s', $data['name'] ),
					array(
						'severity'       => 'notice',
						'object_type'    => 'plugin',
						'object_name'    => $plugin,
						'object_version' => $data['version'],
						'context'        => array(
							'plugin_file' => $plugin,
							'plugin_name' => $data['name'],
							'new_version' => $data['version'],
						),
					)
				);
			}
		}

		if ( 'theme' === $hook_extra['type'] && ! empty( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
			foreach ( $hook_extra['themes'] as $theme_slug ) {
				$theme = wp_get_theme( $theme_slug );
				WP_Blackbox_Logger::log(
					'theme_updated',
					sprintf( 'Theme updated: %s', $theme->exists() ? $theme->get( 'Name' ) : $theme_slug ),
					array(
						'severity'       => 'notice',
						'object_type'    => 'theme',
						'object_name'    => $theme_slug,
						'object_version' => $theme->exists() ? $theme->get( 'Version' ) : '',
					)
				);
			}
		}

		if ( 'core' === $hook_extra['type'] ) {
			WP_Blackbox_Logger::log(
				'core_updated',
				sprintf( 'WordPress core updated: %s', get_bloginfo( 'version' ) ),
				array(
					'severity'       => 'notice',
					'object_type'    => 'core',
					'object_name'    => 'wordpress',
					'object_version' => get_bloginfo( 'version' ),
				)
			);
		}
	}

	/**
	 * Read plugin metadata.
	 *
	 * @param string $plugin Plugin file.
	 * @return array
	 */
	private static function get_plugin_data( $plugin ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$file = trailingslashit( WP_PLUGIN_DIR ) . $plugin;

		if ( file_exists( $file ) ) {
			$data = get_plugin_data( $file, false, false );

			return array(
				'name'    => $data['Name'] ? $data['Name'] : $plugin,
				'version' => $data['Version'] ? $data['Version'] : '',
			);
		}

		return array(
			'name'    => $plugin,
			'version' => '',
		);
	}
}
