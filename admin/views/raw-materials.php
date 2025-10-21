<?php
/**
 * Raw Materials admin screen.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rim-wrap" x-data="RIMMaterialsApp()" x-init="init()">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Raw Materials', 'restaurant-inventory-manager' ); ?></h1>
	<button type="button" class="button button-primary rim-button" x-on:click="openCreateModal">
		<?php esc_html_e( 'Add New Material', 'restaurant-inventory-manager' ); ?>
	</button>

	<div class="rim-panel rim-panel--table">
		<table id="rim-materials-table" class="display rim-table" style="width:100%">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Unit', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Quantity', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Warning Qty', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Supplier', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Last Price', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Last Updated', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Last Edited By', 'restaurant-inventory-manager' ); ?></th>
					<th class="rim-table-actions"><?php esc_html_e( 'Actions', 'restaurant-inventory-manager' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

	<!-- Material Modal -->
	<div class="rim-modal" x-show="showModal" x-cloak>
		<div class="rim-modal__overlay" x-on:click="closeModal"></div>
		<div class="rim-modal__content" role="dialog" aria-modal="true" aria-labelledby="rim-material-modal-title">
			<div class="rim-modal__header">
				<h2 id="rim-material-modal-title" x-text="isEditing ? '<?php echo esc_js( __( 'Edit Material', 'restaurant-inventory-manager' ) ); ?>' : '<?php echo esc_js( __( 'Add Material', 'restaurant-inventory-manager' ) ); ?>'"></h2>
				<button type="button" class="rim-modal__close" x-on:click="closeModal" aria-label="<?php esc_attr_e( 'Close', 'restaurant-inventory-manager' ); ?>">&times;</button>
			</div>
			<form class="rim-form" x-on:submit.prevent="submitMaterial">
				<div class="rim-grid">
					<div class="rim-field">
						<label for="rim-material-name"><?php esc_html_e( 'Material Name', 'restaurant-inventory-manager' ); ?> <span class="rim-required">*</span></label>
						<input type="text" id="rim-material-name" x-model="form.name" required maxlength="190" />
					</div>
					<div class="rim-field">
						<label for="rim-material-unit"><?php esc_html_e( 'Unit Type', 'restaurant-inventory-manager' ); ?> <span class="rim-required">*</span></label>
						<select id="rim-material-unit" x-model="form.unit_type" required>
							<template x-for="unit in units" :key="unit">
								<option x-text="unit"></option>
							</template>
						</select>
					</div>
					<div class="rim-field">
						<label for="rim-material-quantity"><?php esc_html_e( 'Starting Quantity', 'restaurant-inventory-manager' ); ?></label>
						<input type="number" step="0.001" min="0" id="rim-material-quantity" x-model="form.quantity" />
					</div>
					<div class="rim-field">
						<label for="rim-material-warning"><?php esc_html_e( 'Warning Quantity', 'restaurant-inventory-manager' ); ?></label>
						<input type="number" step="0.001" min="0" id="rim-material-warning" x-model="form.warning_quantity" />
					</div>
					<div class="rim-field">
						<label for="rim-material-supplier"><?php esc_html_e( 'Supplier', 'restaurant-inventory-manager' ); ?></label>
						<input type="text" id="rim-material-supplier" x-model="form.supplier" maxlength="190" />
					</div>
					<div class="rim-field">
						<label for="rim-material-price"><?php esc_html_e( 'Last Purchase Price', 'restaurant-inventory-manager' ); ?></label>
						<input type="number" step="0.01" min="0" id="rim-material-price" x-model="form.price" />
					</div>
				</div>
				<div class="rim-modal__footer">
					<button type="button" class="button" x-on:click="closeModal"><?php esc_html_e( 'Cancel', 'restaurant-inventory-manager' ); ?></button>
					<button type="submit" class="button button-primary" x-bind:disabled="submitting">
						<span x-show="! submitting"><?php esc_html_e( 'Save', 'restaurant-inventory-manager' ); ?></span>
						<span x-show="submitting"><?php esc_html_e( 'Saving…', 'restaurant-inventory-manager' ); ?></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
