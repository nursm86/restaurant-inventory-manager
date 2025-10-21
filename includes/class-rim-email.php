<?php
/**
 * Email helpers for Restaurant Inventory Manager.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIM_Email
 */
class RIM_Email {

	/**
	 * Sends low stock notification email when enabled.
	 *
	 * @param array $material Material data array.
	 * @param int   $user_id  Actor user ID.
	 * @return void
	 */
	public static function send_low_stock_alert( $material, $user_id ) {
		$settings = rim_get_settings();

		if ( empty( $settings['alerts_enabled'] ) ) {
			return;
		}

		$to = ! empty( $settings['alert_email'] ) ? $settings['alert_email'] : get_option( 'admin_email' );

		$user        = get_userdata( $user_id );
		$user_name   = $user ? $user->display_name : __( 'System', 'restaurant-inventory-manager' );
		$material_id = isset( $material['id'] ) ? absint( $material['id'] ) : 0;

		$subject = sprintf(
			/* translators: %s: material name */
			__( '[Restaurant Inventory] Low stock: %s', 'restaurant-inventory-manager' ),
			isset( $material['name'] ) ? $material['name'] : __( 'Unknown material', 'restaurant-inventory-manager' )
		);

		$material_url = add_query_arg( 'material_id', $material_id, rim_get_admin_url( 'raw-materials' ) );

		$body_lines = array(
			sprintf(
				/* translators: %s material name */
				__( 'Material: %s', 'restaurant-inventory-manager' ),
				isset( $material['name'] ) ? $material['name'] : __( 'Unknown', 'restaurant-inventory-manager' )
			),
			sprintf(
				/* translators: %s quantity */
				__( 'Current quantity: %s', 'restaurant-inventory-manager' ),
				isset( $material['quantity'] ) ? rim_format_quantity( $material['quantity'] ) : '0'
			),
			sprintf(
				/* translators: %s quantity */
				__( 'Warning threshold: %s', 'restaurant-inventory-manager' ),
				isset( $material['warning_quantity'] ) ? rim_format_quantity( $material['warning_quantity'] ) : '0'
			),
			sprintf(
				/* translators: %s user display name */
				__( 'Adjusted by: %s', 'restaurant-inventory-manager' ),
				$user_name
			),
			sprintf(
				/* translators: %s date */
				__( 'Date: %s', 'restaurant-inventory-manager' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			__( 'View material:', 'restaurant-inventory-manager' ) . ' ' . $material_url,
		);

		$body = implode( "\n", $body_lines );

		wp_mail( $to, $subject, $body );
	}
}
