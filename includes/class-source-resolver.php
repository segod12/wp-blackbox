<?php
/**
 * Resolve files to plugin, theme, core, or unknown sources.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Source_Resolver {
	/**
	 * Resolve a file path to a source descriptor.
	 *
	 * @param string $file_path File path.
	 * @return array
	 */
	public static function resolve( $file_path ) {
		$file_path = wp_normalize_path( (string) $file_path );

		if ( '' === $file_path ) {
			return self::unknown();
		}

		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$theme_dir  = wp_normalize_path( get_theme_root() );
		$admin_dir  = wp_normalize_path( ABSPATH . 'wp-admin' );
		$includes   = wp_normalize_path( ABSPATH . WPINC );

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

			if ( 0 === strpos( $file_path, trailingslashit( $mu_plugin_dir ) ) ) {
				return array(
					'source_type'    => 'mu-plugin',
					'source_slug'    => basename( $file_path ),
					'source_name'    => basename( $file_path ),
					'source_version' => '',
				);
			}
		}

		if ( 0 === strpos( $file_path, trailingslashit( $plugin_dir ) ) ) {
			return self::resolve_plugin( $file_path, $plugin_dir );
		}

		if ( 0 === strpos( $file_path, trailingslashit( $theme_dir ) ) ) {
			return self::resolve_theme( $file_path, $theme_dir );
		}

		if ( 0 === strpos( $file_path, trailingslashit( $admin_dir ) ) || 0 === strpos( $file_path, trailingslashit( $includes ) ) ) {
			return array(
				'source_type'    => 'core',
				'source_slug'    => 'wordpress',
				'source_name'    => 'WordPress Core',
				'source_version' => get_bloginfo( 'version' ),
			);
		}

		return self::unknown();
	}

	/**
	 * Resolve plugin files.
	 *
	 * @param string $file_path File path.
	 * @param string $plugin_dir Plugin directory.
	 * @return array
	 */
	private static function resolve_plugin( $file_path, $plugin_dir ) {
		$relative = ltrim( substr( $file_path, strlen( trailingslashit( $plugin_dir ) ) ), '/' );
		$parts    = explode( '/', $relative );
		$slug     = sanitize_key( $parts[0] ?? '' );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( 0 === strpos( $plugin_file, $slug . '/' ) || $plugin_file === $relative ) {
				return array(
					'source_type'    => 'plugin',
					'source_slug'    => $slug,
					'source_name'    => $plugin_data['Name'] ?? $slug,
					'source_version' => $plugin_data['Version'] ?? '',
				);
			}
		}

		return array(
			'source_type'    => 'plugin',
			'source_slug'    => $slug,
			'source_name'    => $slug,
			'source_version' => '',
		);
	}

	/**
	 * Resolve theme files.
	 *
	 * @param string $file_path File path.
	 * @param string $theme_dir Theme directory.
	 * @return array
	 */
	private static function resolve_theme( $file_path, $theme_dir ) {
		$relative = ltrim( substr( $file_path, strlen( trailingslashit( $theme_dir ) ) ), '/' );
		$parts    = explode( '/', $relative );
		$slug     = sanitize_key( $parts[0] ?? '' );
		$theme    = wp_get_theme( $slug );

		return array(
			'source_type'    => 'theme',
			'source_slug'    => $slug,
			'source_name'    => $theme->exists() ? $theme->get( 'Name' ) : $slug,
			'source_version' => $theme->exists() ? $theme->get( 'Version' ) : '',
		);
	}

	/**
	 * Unknown source descriptor.
	 *
	 * @return array
	 */
	private static function unknown() {
		return array(
			'source_type'    => 'unknown',
			'source_slug'    => '',
			'source_name'    => 'Unknown',
			'source_version' => '',
		);
	}
}
