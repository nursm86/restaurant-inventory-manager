<?php
/**
 * Reports admin screen.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rim-wrap" x-data="RIMReportsApp()" x-init="init()">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Inventory Reports', 'restaurant-inventory-manager' ); ?></h1>

	<div class="rim-panel">
		<div class="rim-filters">
			<div class="rim-field">
				<label for="rim-report-start"><?php esc_html_e( 'Start Date', 'restaurant-inventory-manager' ); ?></label>
				<input type="date" id="rim-report-start" x-model="filters.date_start" x-on:change="reloadAll" />
			</div>
			<div class="rim-field">
				<label for="rim-report-end"><?php esc_html_e( 'End Date', 'restaurant-inventory-manager' ); ?></label>
				<input type="date" id="rim-report-end" x-model="filters.date_end" x-on:change="reloadAll" />
			</div>
			<div class="rim-filters__actions">
				<button type="button" class="button" x-on:click="resetFilters"><?php esc_html_e( 'Last 7 days', 'restaurant-inventory-manager' ); ?></button>
			</div>
		</div>
	</div>

	<div class="rim-kpi-grid">
		<div class="rim-kpi">
			<h3><?php esc_html_e( 'Total Purchases (Qty)', 'restaurant-inventory-manager' ); ?></h3>
			<p x-text="summary.purchases_quantity"></p>
		</div>
		<div class="rim-kpi">
			<h3><?php esc_html_e( 'Total Purchases (Value)', 'restaurant-inventory-manager' ); ?></h3>
			<p x-text="summary.purchases_value"></p>
		</div>
		<div class="rim-kpi">
			<h3><?php esc_html_e( 'Total Usage (Qty)', 'restaurant-inventory-manager' ); ?></h3>
			<p x-text="summary.usage_quantity"></p>
		</div>
	</div>

	<div class="rim-panel rim-panel--table">
		<div class="rim-panel__header">
			<h2><?php esc_html_e( 'Purchases by Material', 'restaurant-inventory-manager' ); ?></h2>
			<button type="button" class="button button-secondary" x-on:click="exportPurchases" x-bind:disabled="exportingPurchases">
				<span x-show="! exportingPurchases"><?php esc_html_e( 'Export CSV', 'restaurant-inventory-manager' ); ?></span>
				<span x-show="exportingPurchases"><?php esc_html_e( 'Preparing…', 'restaurant-inventory-manager' ); ?></span>
			</button>
		</div>
		<table class="rim-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Material', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Unit', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Total Quantity', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Total Value', 'restaurant-inventory-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<template x-for="row in purchases" :key="'purchase-' + row.name">
					<tr>
						<td x-text="row.name"></td>
						<td x-text="row.unit"></td>
						<td x-text="row.qty"></td>
						<td x-text="row.value"></td>
					</tr>
				</template>
				<tr x-show="purchases.length === 0">
					<td colspan="4"><?php esc_html_e( 'No purchase data for the selected range.', 'restaurant-inventory-manager' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="rim-panel rim-panel--table">
		<div class="rim-panel__header">
			<h2><?php esc_html_e( 'Usage by Material', 'restaurant-inventory-manager' ); ?></h2>
			<button type="button" class="button button-secondary" x-on:click="exportUsage" x-bind:disabled="exportingUsage">
				<span x-show="! exportingUsage"><?php esc_html_e( 'Export CSV', 'restaurant-inventory-manager' ); ?></span>
				<span x-show="exportingUsage"><?php esc_html_e( 'Preparing…', 'restaurant-inventory-manager' ); ?></span>
			</button>
		</div>
		<table class="rim-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Material', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Unit', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Total Quantity', 'restaurant-inventory-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<template x-for="row in usage" :key="'usage-' + row.name">
					<tr>
						<td x-text="row.name"></td>
						<td x-text="row.unit"></td>
						<td x-text="row.qty"></td>
					</tr>
				</template>
				<tr x-show="usage.length === 0">
					<td colspan="3"><?php esc_html_e( 'No usage data for the selected range.', 'restaurant-inventory-manager' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
