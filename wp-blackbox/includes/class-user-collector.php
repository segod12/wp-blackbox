<?php
/**
 * User and role change collector.
 *
 * @package WP_Blackbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Blackbox_User_Collector {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'user_register', array( __CLASS__, 'user_registered' ), 20, 1 );
		add_action( 'set_user_role', array( __CLASS__, 'user_role_changed' ), 10, 3 );
		add_action( 'profile_update', array( __CLASS__, 'profile_updated' ), 10, 2 );
		add_action( 'deleted_user', array( __CLASS__, 'user_deleted' ), 10, 3 );
	}

	/**
	 * Log new administrator users.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function user_registered( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		WP_Blackbox_Logger::log(
			'admin_user_created',
			sprintf( 'New administrator user created: %s', $user->user_login ),
			array(
				'severity'    => 'warning',
				'object_type' => 'user',
				'object_name' => $user->user_login,
				'context'     => array(
					'user_id'    => $user_id,
					'user_login' => $user->user_login,
					'user_email' => $user->user_email,
				),
			)
		);
	}

	/**
	 * Log role changes to administrator.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role New role.
	 * @param array  $old_roles Old roles.
	 * @return void
	 */
	public static function user_role_changed( $user_id, $role, $old_roles ) {
		if ( 'administrator' !== $role ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		WP_Blackbox_Logger::log(
			'user_role_changed',
			sprintf( 'User role changed to administrator: %s', $user->user_login ),
			array(
				'severity'       => 'warning',
				'object_type'    => 'user',
				'object_name'    => $user->user_login,
				'previous_value' => implode( ', ', (array) $old_roles ),
				'new_value'      => 'administrator',
				'context'        => array(
					'user_id'    => $user_id,
					'user_login' => $user->user_login,
				),
			)
		);
	}

	/**
	 * Log administrator email changes.
	 *
	 * @param int     $user_id User ID.
	 * @param WP_User $old_user_data Previous user data.
	 * @return void
	 */
	public static function profile_updated( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );

		if ( ! $user || $user->user_email === $old_user_data->user_email ) {
			return;
		}

		if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		WP_Blackbox_Logger::log(
			'user_email_changed',
			sprintf( 'Administrator email changed: %s', $user->user_login ),
			array(
				'severity'       => 'notice',
				'object_type'    => 'user',
				'object_name'    => $user->user_login,
				'previous_value' => $old_user_data->user_email,
				'new_value'      => $user->user_email,
			)
		);
	}

	/**
	 * Log deleted administrator users.
	 *
	 * @param int      $id User ID.
	 * @param int|null $reassign Reassigned user ID.
	 * @param WP_User  $user Deleted user object.
	 * @return void
	 */
	public static function user_deleted( $id, $reassign, $user ) {
		if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		WP_Blackbox_Logger::log(
			'admin_user_deleted',
			sprintf( 'Administrator user deleted: %s', $user->user_login ),
			array(
				'severity'    => 'notice',
				'object_type' => 'user',
				'object_name' => $user->user_login,
				'context'     => array(
					'user_id'  => $id,
					'reassign' => $reassign,
				),
			)
		);
	}
}
