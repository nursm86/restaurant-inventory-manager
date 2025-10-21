<?php
/**
 * Handles plugin uninstall.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIM_Uninstaller
 */
class RIM_Uninstaller {

	/**
	 * Executes uninstall cleanup.
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}

		global $wpdb;

		$materials_table     = $wpdb->prefix . 'rim_raw_materials';
		$transactions_table  = $wpdb->prefix . 'rim_transactions';
		$allowed_table_names = array( $materials_table, $transactions_table );

		foreach ( $allowed_table_names as $table_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( 'rim_settings' );
	}
}

