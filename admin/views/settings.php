<?php
/**
 * Settings admin screen.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = rim_get_settings();
?>
<div class="wrap rim-wrap" x-data="RIMSettingsApp()" x-init="init(<?php echo wp_json_encode( $settings ); ?>)">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Inventory Settings', 'restaurant-inventory-manager' ); ?></h1>

	<div class="rim-panel">
		<form class="rim-form" x-on:submit.prevent="save">
			<div class="rim-field rim-field--toggle">
				<label for="rim-alerts-enabled">
					<span><?php esc_html_e( 'Enable Low Stock Alerts', 'restaurant-inventory-manager' ); ?></span>
				</label>
				<input type="checkbox" id="rim-alerts-enabled" x-model="form.alerts_enabled" />
			</div>
			<div class="rim-field">
				<label for="rim-alert-email"><?php esc_html_e( 'Alert Email Address', 'restaurant-inventory-manager' ); ?></label>
				<input type="email" id="rim-alert-email" x-model="form.alert_email" required />
				<p class="description"><?php esc_html_e( 'Alerts will be sent to this email when stock drops below warning quantity.', 'restaurant-inventory-manager' ); ?></p>
			</div>
			<div class="rim-field">
				<label for="rim-units-list"><?php esc_html_e( 'Units List', 'restaurant-inventory-manager' ); ?></label>
				<textarea id="rim-units-list" rows="3" x-model="unitsInput"></textarea>
				<p class="description"><?php esc_html_e( 'Enter units separated by commas or new lines (e.g., kg, pcs, box).', 'restaurant-inventory-manager' ); ?></p>
			</div>
			<div class="rim-form__actions">
				<button type="submit" class="button button-primary" x-bind:disabled="saving">
					<span x-show="! saving"><?php esc_html_e( 'Save Settings', 'restaurant-inventory-manager' ); ?></span>
					<span x-show="saving"><?php esc_html_e( 'Saving…', 'restaurant-inventory-manager' ); ?></span>
				</button>
			</div>
		</form>
	</div>
</div>
