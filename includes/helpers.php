<?php
/**
 * Helper functions for Restaurant Inventory Manager.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether current user can manage the plugin.
 *
 * @return bool
 */
function rim_user_can_manage() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

/**
 * Returns plugin settings with defaults merged.
 *
 * @return array
 */
function rim_get_settings() {
	$defaults = array(
		'alerts_enabled' => true,
		'alert_email'    => get_option( 'admin_email' ),
		'units_list'     => array( 'kg', 'pcs', 'ltr', 'box', 'pack' ),
	);

	$settings = get_option( 'rim_settings', array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, $defaults );
}

/**
 * Sanitizes and persists plugin settings.
 *
 * @param array $input Settings payload.
 * @return array
 */
function rim_save_settings( $input ) {
	$sanitized = array();

	$sanitized['alerts_enabled'] = array_key_exists( 'alerts_enabled', $input ) ? (bool) $input['alerts_enabled'] : true;

	if ( isset( $input['alert_email'] ) ) {
		$sanitized['alert_email'] = sanitize_email( $input['alert_email'] );
		if ( empty( $sanitized['alert_email'] ) ) {
			$sanitized['alert_email'] = get_option( 'admin_email' );
		}
	}

	$units = array();
	if ( isset( $input['units_list'] ) ) {
		if ( is_array( $input['units_list'] ) ) {
			$candidate_units = $input['units_list'];
		} else {
			$candidate_units = explode( ',', (string) $input['units_list'] );
		}
		foreach ( $candidate_units as $unit ) {
			$unit = trim( sanitize_text_field( $unit ) );
			if ( ! empty( $unit ) ) {
				$units[] = $unit;
			}
		}
	}

	if ( empty( $units ) ) {
		$units = array( 'kg', 'pcs', 'ltr', 'box', 'pack' );
	}

	$sanitized['units_list'] = array_values( array_unique( $units ) );

	update_option( 'rim_settings', $sanitized );

	return rim_get_settings();
}

/**
 * Formats a decimal quantity respecting precision.
 *
 * @param float $value Quantity value.
 * @param int   $precision Precision.
 * @return string
 */
function rim_format_quantity( $value, $precision = 3 ) {
	return number_format_i18n( (float) $value, $precision );
}

/**
 * Formats price values.
 *
 * @param float $price Price value.
 * @return string
 */
function rim_format_price( $price ) {
	if ( null === $price || '' === $price ) {
		return '';
	}

	return number_format_i18n( (float) $price, 2 );
}

/**
 * Normalizes decimal input.
 *
 * @param mixed $value Incoming value.
 * @param int   $precision Precision.
 * @return float
 */
function rim_sanitize_decimal( $value, $precision = 3 ) {
	$value = (float) str_replace( ',', '.', (string) $value );
	return round( $value, $precision );
}

/**
 * Builds URL to plugin admin page.
 *
 * @param string $page Page slug.
 * @return string
 */
function rim_get_admin_url( $page = 'raw-materials' ) {
	return admin_url( 'admin.php?page=rim-' . $page );
}

/**
 * Returns the capability required for menu access.
 *
 * @return string
 */
function rim_get_manage_capability() {
	return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
}
