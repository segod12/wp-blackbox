<?php
/**
 * Database growth monitor.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_Database_Collector {
	/**
	 * Snapshot table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'blackbox_table_snapshots';
	}

	/**
	 * Register scheduled collector.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_blackbox_daily_db_snapshot', array( __CLASS__, 'collect_snapshot' ) );
	}

	/**
	 * Create database snapshot table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			table_name VARCHAR(191) NOT NULL,
			table_rows BIGINT UNSIGNED NULL,
			data_length BIGINT UNSIGNED NULL,
			index_length BIGINT UNSIGNED NULL,
			total_size BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY table_name (table_name),
			KEY created_at (created_at),
			KEY total_size (total_size)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Collect a database size snapshot and log growth warnings.
	 *
	 * @return int Number of tables snapshotted.
	 */
	public static function collect_snapshot() {
		global $wpdb;

		$database = DB_NAME;
		$prefix   = $wpdb->esc_like( $wpdb->prefix ) . '%';
		$now      = current_time( 'mysql' );

		$tables = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s
				ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC',
				$database,
				$prefix
			),
			ARRAY_A
		);

		if ( empty( $tables ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $tables as $table ) {
			$name        = (string) $table['TABLE_NAME'];
			$rows        = max( 0, (int) $table['TABLE_ROWS'] );
			$data_length = max( 0, (int) $table['DATA_LENGTH'] );
			$index_size  = max( 0, (int) $table['INDEX_LENGTH'] );
			$total_size  = $data_length + $index_size;
			$previous    = self::previous_snapshot( $name );

			$wpdb->insert(
				self::table_name(),
				array(
					'table_name'   => $name,
					'table_rows'   => $rows,
					'data_length'  => $data_length,
					'index_length' => $index_size,
					'total_size'   => $total_size,
					'created_at'   => $now,
				),
				array( '%s', '%d', '%d', '%d', '%d', '%s' )
			);

			$count++;

			self::maybe_log_growth_warning( $name, $rows, $total_size, $previous );
		}

		WP_Blackbox_Logger::log(
			'db_snapshot',
			sprintf( 'Database snapshot recorded for %d tables.', $count ),
			array(
				'severity'    => 'info',
				'object_type' => 'database',
				'object_name' => DB_NAME,
				'context'     => array(
					'tables_snapshotted' => $count,
				),
			)
		);

		return $count;
	}

	/**
	 * Get largest current tables.
	 *
	 * @param int $limit Result limit.
	 * @return array
	 */
	public static function largest_tables( $limit = 25 ) {
		global $wpdb;

		$limit = min( 100, max( 1, absint( $limit ) ) );

		$sql = 'SELECT s.*
			FROM ' . self::table_name() . ' s
			INNER JOIN (
				SELECT table_name, MAX(created_at) AS max_created
				FROM ' . self::table_name() . '
				GROUP BY table_name
			) latest ON latest.table_name = s.table_name AND latest.max_created = s.created_at
			ORDER BY s.total_size DESC
			LIMIT %d';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A );

		return array_map( array( __CLASS__, 'decorate_snapshot' ), (array) $rows );
	}

	/**
	 * Get fastest-growing tables compared with the previous snapshot.
	 *
	 * @param int $limit Result limit.
	 * @return array
	 */
	public static function fastest_growth( $limit = 25 ) {
		$latest = self::largest_tables( 100 );
		$growth = array();

		foreach ( $latest as $row ) {
			$previous = self::previous_snapshot( $row['table_name'], $row['created_at'] );
			$delta    = $previous ? (int) $row['total_size'] - (int) $previous['total_size'] : 0;
			$row['growth_bytes'] = $delta;
			$row['growth_pct']   = ( $previous && (int) $previous['total_size'] > 0 ) ? round( ( $delta / (int) $previous['total_size'] ) * 100, 2 ) : 0;
			$growth[]            = $row;
		}

		usort(
			$growth,
			static function ( $a, $b ) {
				return (int) $b['growth_bytes'] <=> (int) $a['growth_bytes'];
			}
		);

		return array_slice( $growth, 0, min( 100, max( 1, absint( $limit ) ) ) );
	}

	/**
	 * Return snapshot count and last run time.
	 *
	 * @return array
	 */
	public static function summary() {
		global $wpdb;

		$row = $wpdb->get_row(
			'SELECT COUNT(*) AS snapshot_count, MAX(created_at) AS last_snapshot FROM ' . self::table_name(),
			ARRAY_A
		);

		return array(
			'snapshot_count' => isset( $row['snapshot_count'] ) ? (int) $row['snapshot_count'] : 0,
			'last_snapshot'  => $row['last_snapshot'] ?? '',
		);
	}

	/**
	 * Format bytes for display.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$bytes = max( 0, (float) $bytes );
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		for ( $i = 0; $bytes >= 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}

		return sprintf( '%s %s', round( $bytes, 2 ), $units[ $i ] );
	}

	/**
	 * Get the previous snapshot for a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $before Optional cutoff.
	 * @return array|null
	 */
	private static function previous_snapshot( $table_name, $before = '' ) {
		global $wpdb;

		if ( '' === $before ) {
			$sql = 'SELECT * FROM ' . self::table_name() . ' WHERE table_name = %s ORDER BY created_at DESC, id DESC LIMIT 1';
			return $wpdb->get_row( $wpdb->prepare( $sql, $table_name ), ARRAY_A );
		}

		$sql = 'SELECT * FROM ' . self::table_name() . ' WHERE table_name = %s AND created_at < %s ORDER BY created_at DESC, id DESC LIMIT 1';
		return $wpdb->get_row( $wpdb->prepare( $sql, $table_name, $before ), ARRAY_A );
	}

	/**
	 * Log growth warnings when a table changes dramatically.
	 *
	 * @param string     $table_name Table name.
	 * @param int        $rows Rows.
	 * @param int        $total_size Total size.
	 * @param array|null $previous Previous snapshot.
	 * @return void
	 */
	private static function maybe_log_growth_warning( $table_name, $rows, $total_size, $previous ) {
		$owner       = WP_Blackbox_Table_Owner_Map::owner_for( $table_name );
		$large_limit = 500 * MB_IN_BYTES;

		if ( $total_size >= $large_limit ) {
			self::log_table_event(
				$table_name,
				'db_table_growth',
				sprintf( 'Large database table detected: %s is %s.', $table_name, self::format_bytes( $total_size ) ),
				'warning',
				$owner,
				$rows,
				$total_size,
				$previous
			);
			return;
		}

		if ( ! $previous ) {
			return;
		}

		$delta     = $total_size - (int) $previous['total_size'];
		$delta_pct = (int) $previous['total_size'] > 0 ? ( $delta / (int) $previous['total_size'] ) * 100 : 0;

		if ( $delta >= 100 * MB_IN_BYTES || ( $delta > 0 && $delta_pct >= 50 ) ) {
			self::log_table_event(
				$table_name,
				'db_table_growth',
				sprintf( 'Database table grew quickly: %s increased by %s.', $table_name, self::format_bytes( $delta ) ),
				'warning',
				$owner,
				$rows,
				$total_size,
				$previous
			);
		}
	}

	/**
	 * Write a table growth event.
	 *
	 * @param string     $table_name Table name.
	 * @param string     $event_type Event type.
	 * @param string     $summary Summary.
	 * @param string     $severity Severity.
	 * @param string     $owner Owner.
	 * @param int        $rows Rows.
	 * @param int        $total_size Total size.
	 * @param array|null $previous Previous snapshot.
	 * @return void
	 */
	private static function log_table_event( $table_name, $event_type, $summary, $severity, $owner, $rows, $total_size, $previous ) {
		WP_Blackbox_Logger::log(
			$event_type,
			$summary,
			array(
				'severity'    => $severity,
				'object_type' => 'database_table',
				'object_name' => $table_name,
				'details'     => sprintf( 'Likely owner: %s. Suggested next check: inspect failed background jobs, logs, transients, revisions, or plugin-specific cleanup tools before deleting anything.', $owner ),
				'context'     => array(
					'table_name'       => $table_name,
					'owner'            => $owner,
					'rows'             => $rows,
					'total_size'       => $total_size,
					'total_size_human' => self::format_bytes( $total_size ),
					'previous_size'    => $previous['total_size'] ?? 0,
				),
			)
		);
	}

	/**
	 * Add display fields.
	 *
	 * @param array $row Snapshot row.
	 * @return array
	 */
	private static function decorate_snapshot( $row ) {
		$row['owner']            = WP_Blackbox_Table_Owner_Map::owner_for( $row['table_name'] );
		$row['total_size_human'] = self::format_bytes( (int) $row['total_size'] );
		$row['data_size_human']  = self::format_bytes( (int) $row['data_length'] );
		$row['index_size_human'] = self::format_bytes( (int) $row['index_length'] );

		return $row;
	}
}
