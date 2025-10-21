<?php
/**
 * Stock Transactions admin screen.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rim-wrap" x-data="RIMTransactionsApp()" x-init="init()">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Stock Transactions', 'restaurant-inventory-manager' ); ?></h1>

	<div class="rim-panel">
		<form class="rim-form" x-on:submit.prevent="submitTransaction">
			<div class="rim-grid">
				<div class="rim-field">
					<label for="rim-transaction-material"><?php esc_html_e( 'Material', 'restaurant-inventory-manager' ); ?> <span class="rim-required">*</span></label>
					<select id="rim-transaction-material" x-model="form.material_id" x-on:change="handleMaterialChange" required>
						<option value=""><?php esc_html_e( 'Select material', 'restaurant-inventory-manager' ); ?></option>
						<template x-for="material in materials" :key="material.id">
							<option :value="material.id" x-text="material.name"></option>
						</template>
					</select>
				</div>
				<div class="rim-field">
					<label><?php esc_html_e( 'Transaction Type', 'restaurant-inventory-manager' ); ?></label>
					<div class="rim-radio-group">
						<label class="rim-radio">
							<input type="radio" value="add" x-model="form.type" />
							<span><?php esc_html_e( 'Add', 'restaurant-inventory-manager' ); ?></span>
						</label>
						<label class="rim-radio">
							<input type="radio" value="use" x-model="form.type" />
							<span><?php esc_html_e( 'Use', 'restaurant-inventory-manager' ); ?></span>
						</label>
					</div>
				</div>
				<div class="rim-field">
					<label for="rim-transaction-quantity"><?php esc_html_e( 'Quantity', 'restaurant-inventory-manager' ); ?> <span class="rim-required">*</span></label>
					<input type="number" id="rim-transaction-quantity" step="0.001" min="0.001" x-model="form.quantity" required />
				</div>
				<div class="rim-field" x-show="form.type === 'add'" x-cloak>
					<label for="rim-transaction-price"><?php esc_html_e( 'Price', 'restaurant-inventory-manager' ); ?></label>
					<input type="number" id="rim-transaction-price" step="0.01" min="0" x-model="form.price" />
				</div>
				<div class="rim-field" x-show="form.type === 'add'" x-cloak>
					<label for="rim-transaction-supplier"><?php esc_html_e( 'Supplier', 'restaurant-inventory-manager' ); ?></label>
					<input type="text" id="rim-transaction-supplier" x-model="form.supplier" maxlength="190" />
				</div>
				<div class="rim-field" x-show="form.type === 'use'" x-cloak>
					<label for="rim-transaction-reason"><?php esc_html_e( 'Reason', 'restaurant-inventory-manager' ); ?></label>
					<textarea id="rim-transaction-reason" rows="2" x-model="form.reason"></textarea>
				</div>
				<div class="rim-field">
					<label for="rim-transaction-date"><?php esc_html_e( 'Transaction Date & Time', 'restaurant-inventory-manager' ); ?></label>
					<input type="datetime-local" id="rim-transaction-date" x-model="form.transaction_date" />
				</div>
			</div>
			<div class="rim-form__actions">
				<button type="submit" class="button button-primary" x-bind:disabled="submitting">
					<span x-show="! submitting"><?php esc_html_e( 'Record Transaction', 'restaurant-inventory-manager' ); ?></span>
					<span x-show="submitting"><?php esc_html_e( 'Saving…', 'restaurant-inventory-manager' ); ?></span>
				</button>
			</div>
		</form>
	</div>

	<div class="rim-panel">
		<div class="rim-filters">
			<div class="rim-field">
				<label for="rim-filter-material"><?php esc_html_e( 'Material', 'restaurant-inventory-manager' ); ?></label>
				<select id="rim-filter-material" x-model="filters.material" x-on:change="reloadTable">
					<option value=""><?php esc_html_e( 'All materials', 'restaurant-inventory-manager' ); ?></option>
					<template x-for="material in materials" :key="'filter-' + material.id">
						<option :value="material.id" x-text="material.name"></option>
					</template>
				</select>
			</div>
			<div class="rim-field">
				<label for="rim-filter-type"><?php esc_html_e( 'Type', 'restaurant-inventory-manager' ); ?></label>
				<select id="rim-filter-type" x-model="filters.type" x-on:change="reloadTable">
					<option value=""><?php esc_html_e( 'All types', 'restaurant-inventory-manager' ); ?></option>
					<option value="add"><?php esc_html_e( 'Add', 'restaurant-inventory-manager' ); ?></option>
					<option value="use"><?php esc_html_e( 'Use', 'restaurant-inventory-manager' ); ?></option>
				</select>
			</div>
			<div class="rim-field">
				<label for="rim-filter-start"><?php esc_html_e( 'Start Date', 'restaurant-inventory-manager' ); ?></label>
				<input type="date" id="rim-filter-start" x-model="filters.date_start" x-on:change="reloadTable" />
			</div>
			<div class="rim-field">
				<label for="rim-filter-end"><?php esc_html_e( 'End Date', 'restaurant-inventory-manager' ); ?></label>
				<input type="date" id="rim-filter-end" x-model="filters.date_end" x-on:change="reloadTable" />
			</div>
			<div class="rim-filters__actions">
				<button type="button" class="button" x-on:click="resetFilters"><?php esc_html_e( 'Reset', 'restaurant-inventory-manager' ); ?></button>
				<button type="button" class="button button-secondary" x-on:click="exportCsv" x-bind:disabled="exporting">
					<span x-show="! exporting"><?php esc_html_e( 'Export CSV', 'restaurant-inventory-manager' ); ?></span>
					<span x-show="exporting"><?php esc_html_e( 'Preparing…', 'restaurant-inventory-manager' ); ?></span>
				</button>
			</div>
		</div>
	</div>

	<div class="rim-panel rim-panel--table">
		<table id="rim-transactions-table" class="display rim-table" style="width:100%">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Material', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Type', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Quantity', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Price', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Supplier', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Transaction Date', 'restaurant-inventory-manager' ); ?></th>
					<th><?php esc_html_e( 'Created By', 'restaurant-inventory-manager' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>
