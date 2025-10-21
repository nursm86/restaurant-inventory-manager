(function ($, window, document) {
	'use strict';

	if (typeof window.rimAdminData === 'undefined') {
		return;
	}

	const { ajaxUrl, nonce } = window.rimAdminData;

	const RIM = {
		request(action, data = {}) {
			const form = new FormData();
			form.append('action', action);
			form.append('nonce', nonce);

			Object.entries(data).forEach(([key, value]) => {
				if (Array.isArray(value)) {
					value.forEach((item) => form.append(`${key}[]`, item));
				} else if (typeof value === 'object' && value !== null) {
					form.append(key, JSON.stringify(value));
				} else if (typeof value !== 'undefined') {
					form.append(key, value);
				}
			});

			return fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: form,
				headers: {
					'X-WP-Nonce': nonce,
				},
			})
				.then((response) => response.json())
				.catch(() => ({
					success: false,
					message: 'Network error. Please try again.',
				}));
		},

		showToast(type, message) {
			const containerId = 'rim-toast-container';
			let container = document.getElementById(containerId);

			if (!container) {
				container = document.createElement('div');
				container.id = containerId;
				document.body.appendChild(container);
			}

			const toast = document.createElement('div');
			toast.className = `rim-toast rim-toast--${type}`;
			toast.setAttribute('role', 'status');
			toast.textContent = message;

			container.appendChild(toast);

			setTimeout(() => {
				toast.classList.add('is-visible');
			}, 10);

			setTimeout(() => {
				toast.classList.remove('is-visible');
				setTimeout(() => {
					if (toast.parentNode) {
						toast.parentNode.removeChild(toast);
					}
				}, 300);
			}, 4000);
		},

		downloadBase64(filename, payload) {
			const link = document.createElement('a');
			link.href = `data:text/csv;base64,${payload}`;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		},

		escapeHtml(value) {
			if (typeof value !== 'string') {
				value = value === null || typeof value === 'undefined' ? '' : String(value);
			}
			return value
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},

		formatBadge(text, theme) {
			return `<span class="rim-badge rim-badge--${theme}">${RIM.escapeHtml(text)}</span>`;
		},

		debounce(fn, wait = 300) {
			let timeout;
			return (...args) => {
				clearTimeout(timeout);
				timeout = setTimeout(() => fn.apply(null, args), wait);
			};
		},
	};

	window.RIMMaterialsApp = function () {
		return {
			table: null,
			showModal: false,
			isEditing: false,
			submitting: false,
			units: window.rimAdminData.settings.units || [],
			form: {
				id: '',
				name: '',
				unit_type: '',
				quantity: '',
				warning_quantity: '',
				supplier: '',
				price: '',
			},
			columnMap: ['name', 'unit_type', 'quantity', 'warning_quantity', 'supplier', 'price', 'last_updated', 'last_edited_by'],

			init() {
				this.initTable();
			},

			initTable() {
				const component = this;

				this.table = $('#rim-materials-table').DataTable({
					serverSide: true,
					processing: true,
					searchDelay: 300,
					order: [[0, 'asc']],
					language: {
						emptyTable: window.rimAdminData.i18n.loading,
					},
					ajax(data, callback) {
						component.fetchMaterials(data, callback);
					},
					columns: [
						{
							data: 'name',
							render(data) {
								return RIM.escapeHtml(data);
							},
						},
						{
							data: 'unit_type',
							render(data) {
								return RIM.escapeHtml(data);
							},
						},
						{
							data: null,
							orderable: true,
							render(row) {
								const base = `${RIM.escapeHtml(row.quantity_formatted)} ${RIM.escapeHtml(row.unit_type)}`;
								if (row.warning_quantity > 0 && row.quantity < row.warning_quantity) {
									return `${base} ${RIM.formatBadge('Low', 'warning')}`;
								}
								return base;
							},
						},
						{
							data: null,
							orderable: true,
							render: (row) => component.renderInlineCell(row, 'warning_quantity', row.warning_quantity_formatted, row.warning_quantity, 'number'),
						},
						{
							data: null,
							orderable: true,
							render: (row) => component.renderInlineCell(row, 'supplier', row.supplier || '', row.supplier || '', 'text'),
						},
						{
							data: null,
							orderable: true,
							render: (row) => component.renderInlineCell(row, 'price', row.price_formatted || '', row.price ?? '', 'currency'),
						},
						{
							data: 'last_updated_display',
							render(data) {
								return RIM.escapeHtml(data || '');
							},
						},
						{
							data: 'last_edited_by_name',
							render(data) {
								return RIM.escapeHtml(data || '');
							},
						},
						{
							data: null,
							orderable: false,
							searchable: false,
							render(row) {
								return `
									<div class="rim-actions">
										<button type="button" class="button-link rim-edit-material" data-id="${row.id}">${RIM.escapeHtml(window.rimAdminData.i18n.edit || 'Edit')}</button>
										<button type="button" class="button-link rim-delete-material" data-id="${row.id}">${RIM.escapeHtml(window.rimAdminData.i18n.delete || 'Delete')}</button>
									</div>
								`;
							},
						},
					],
					createdRow(row, rowData) {
						if (rowData.warning_quantity > 0 && rowData.quantity < rowData.warning_quantity) {
							$(row).addClass('rim-row-warning');
						}
					},
				});

				const tableElement = $('#rim-materials-table');

				tableElement.on('click', '.rim-edit-material', (event) => {
					const rowData = this.table.row($(event.currentTarget).closest('tr')).data();
					this.openEditModal(rowData);
				});

				tableElement.on('click', '.rim-delete-material', (event) => {
					const id = $(event.currentTarget).data('id');
					this.deleteMaterial(id);
				});

				tableElement.on('click', '.rim-inline-trigger', (event) => {
					this.startInlineEdit(event);
				});
			},

			fetchMaterials(dtData, callback) {
				const order = dtData.order && dtData.order.length ? dtData.order[0] : { column: 0, dir: 'asc' };
				const orderBy = this.columnMap[order.column] || 'name';

				const payload = {
					page: Math.floor(dtData.start / dtData.length) + 1,
					per_page: dtData.length,
					search: dtData.search?.value || '',
					order_by: orderBy,
					order: order.dir.toUpperCase(),
				};

				RIM.request('rim_list_materials', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						callback({
							data: [],
							recordsTotal: 0,
							recordsFiltered: 0,
							draw: dtData.draw,
						});
						return;
					}

					callback({
						data: response.data.items || [],
						recordsTotal: response.data.total || 0,
						recordsFiltered: response.data.total || 0,
						draw: dtData.draw,
					});
				});
			},

			renderInlineCell(row, field, displayValue, rawValue, type) {
				const safeDisplay = RIM.escapeHtml(displayValue ?? '');
				const safeRaw = RIM.escapeHtml(rawValue ?? '');
				return `
					<div class="rim-inline-wrapper" data-field="${field}" data-id="${row.id}" data-type="${type}" data-value="${safeRaw}">
						<span class="rim-inline-display">${safeDisplay || '<span class="rim-placeholder">—</span>'}</span>
						<button type="button" class="rim-inline-trigger" aria-label="${RIM.escapeHtml(window.rimAdminData.i18n.editField || 'Edit field')}">
							<span class="dashicons dashicons-edit"></span>
						</button>
					</div>
				`;
			},

			startInlineEdit(event) {
				const wrapper = event.currentTarget.closest('.rim-inline-wrapper');
				if (!wrapper || wrapper.classList.contains('is-editing')) {
					return;
				}

				const type = wrapper.dataset.type;
				const currentValue = wrapper.dataset.value || '';

				const input = document.createElement('input');
				input.className = 'rim-inline-input';
				input.value = currentValue;

				if (type === 'number') {
					input.type = 'number';
					input.step = '0.001';
					input.min = '0';
				} else if (type === 'currency') {
					input.type = 'number';
					input.step = '0.01';
					input.min = '0';
				} else {
					input.type = 'text';
				}

		input.setAttribute('aria-label', window.rimAdminData.i18n.editField || 'Edit value');

				wrapper.classList.add('is-editing');
				wrapper.appendChild(input);
				const display = wrapper.querySelector('.rim-inline-display');
				if (display) {
					display.classList.add('is-hidden');
				}

				const commit = () => {
					input.removeEventListener('blur', commit);
					const newValue = input.value.trim();
					this.commitInlineEdit(wrapper, newValue);
				};

				const cancel = () => {
					input.removeEventListener('blur', commit);
					this.cancelInlineEdit(wrapper);
				};

				input.addEventListener('keydown', (e) => {
					if (e.key === 'Enter') {
						e.preventDefault();
						commit();
					}
					if (e.key === 'Escape') {
						e.preventDefault();
						cancel();
					}
				});

				input.addEventListener('blur', commit);
				input.focus();
				input.select();
			},

			cancelInlineEdit(wrapper) {
				const display = wrapper.querySelector('.rim-inline-display');
				const input = wrapper.querySelector('.rim-inline-input');
				if (input) {
					wrapper.removeChild(input);
				}
				if (display) {
					display.classList.remove('is-hidden');
				}
				wrapper.classList.remove('is-editing');
			},

			commitInlineEdit(wrapper, value) {
				const originalValue = wrapper.dataset.value || '';
				const field = wrapper.dataset.field;
				const id = parseInt(wrapper.dataset.id, 10);
				const type = wrapper.dataset.type;

				if (`${value}` === `${originalValue}`) {
					this.cancelInlineEdit(wrapper);
					return;
				}

				const payload = { id };

				if (type === 'number' || type === 'currency') {
					const numeric = parseFloat(value);
					if (Number.isNaN(numeric) || numeric < 0) {
						RIM.showToast('error', window.rimAdminData.i18n.invalidQuantity);
						this.cancelInlineEdit(wrapper);
						return;
					}
					payload[field] = numeric;
				} else {
					payload[field] = value;
				}

				const input = wrapper.querySelector('.rim-inline-input');
				if (input) {
					input.setAttribute('disabled', 'disabled');
				}

				RIM.request('rim_update_material', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						this.cancelInlineEdit(wrapper);
						return;
					}

					const updated = response.data.material;
					wrapper.dataset.value = updated[field];
					const display = wrapper.querySelector('.rim-inline-display');
					if (display) {
						let newText = '';
						if (field === 'warning_quantity') {
							newText = RIM.escapeHtml(updated.warning_quantity_formatted);
						} else if (field === 'price') {
							newText = RIM.escapeHtml(updated.price_formatted || '');
						} else {
							newText = RIM.escapeHtml(updated[field] || '');
						}
						display.innerHTML = newText || '<span class="rim-placeholder">—</span>';
					}
					this.table.ajax.reload(null, false);
					RIM.showToast('success', response.message || window.rimAdminData.i18n.success);
				}).finally(() => {
					this.cancelInlineEdit(wrapper);
				});
			},

			openCreateModal() {
				this.units = window.rimAdminData.settings.units || this.units;
				this.resetForm();
				this.isEditing = false;
				this.showModal = true;
				document.body.classList.add('rim-modal-open');
			},

			openEditModal(row) {
				this.units = window.rimAdminData.settings.units || this.units;
				this.isEditing = true;
				this.showModal = true;
				document.body.classList.add('rim-modal-open');

				this.form = {
					id: row.id,
					name: row.name,
					unit_type: row.unit_type,
					quantity: row.quantity,
					warning_quantity: row.warning_quantity,
					supplier: row.supplier || '',
					price: row.price !== null ? row.price : '',
				};
			},

			closeModal() {
				this.showModal = false;
				document.body.classList.remove('rim-modal-open');
			},

			resetForm() {
				this.units = window.rimAdminData.settings.units || this.units;
				this.form = {
					id: '',
					name: '',
					unit_type: this.units.length ? this.units[0] : '',
					quantity: '',
					warning_quantity: '',
					supplier: '',
					price: '',
				};
			},

			submitMaterial() {
				this.submitting = true;
				const action = this.isEditing ? 'rim_update_material' : 'rim_add_material';
				const payload = {
					name: this.form.name,
					unit_type: this.form.unit_type,
					warning_quantity: this.form.warning_quantity,
					supplier: this.form.supplier,
					price: this.form.price,
					quantity: this.form.quantity,
				};

				if (this.isEditing) {
					payload.id = this.form.id;
				}

				RIM.request(action, payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						return;
					}

					RIM.showToast('success', response.message || window.rimAdminData.i18n.success);
					this.table.ajax.reload(null, false);
					this.closeModal();
				}).finally(() => {
					this.submitting = false;
				});
			},

			deleteMaterial(id) {
				if (!window.confirm(window.rimAdminData.i18n.confirmDelete)) {
					return;
				}

				RIM.request('rim_delete_material', { id }).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						return;
					}

					RIM.showToast('success', response.message || window.rimAdminData.i18n.success);
					this.table.ajax.reload(null, false);
				});
			},
		};
	};

	window.RIMTransactionsApp = function () {
		return {
			materials: [],
			table: null,
			submitting: false,
			exporting: false,
			form: {
				material_id: '',
				type: 'add',
				quantity: '',
				price: '',
				supplier: '',
				reason: '',
				transaction_date: '',
			},
			filters: {
				material: '',
				type: '',
				date_start: '',
				date_end: '',
			},

			init() {
				this.loadMaterials().then(() => {
					this.initTable();
				});
			},

			loadMaterials() {
				return RIM.request('rim_list_materials', {
					page: 1,
					per_page: 500,
				}).then((response) => {
					if (response.success) {
						this.materials = response.data.items || [];
					}
				});
			},

			initTable() {
				const component = this;
				this.table = $('#rim-transactions-table').DataTable({
					serverSide: true,
					processing: true,
					order: [[6, 'desc']],
					searching: false,
					ajax(data, callback) {
						component.fetchTransactions(data, callback);
					},
					columns: [
						{ data: 'material_name', render: (data) => RIM.escapeHtml(data || '') },
						{
							data: 'type',
							render(data) {
								const theme = data === 'add' ? 'success' : 'warning';
								const label = data === 'add' ? window.rimAdminData.i18n.add || 'Add' : window.rimAdminData.i18n.use || 'Use';
								return RIM.formatBadge(label, theme);
							},
						},
						{ data: 'quantity', render: (data) => RIM.escapeHtml(data || '') },
						{ data: 'price', render: (data) => RIM.escapeHtml(data || '') },
						{ data: 'supplier', render: (data) => RIM.escapeHtml(data || '') },
						{ data: 'reason', render: (data) => RIM.escapeHtml(data || '') },
						{ data: 'transaction_date', render: (data) => RIM.escapeHtml(data || '') },
						{ data: 'created_by', render: (data) => RIM.escapeHtml(data || '') },
					],
				});
			},

			fetchTransactions(dtData, callback) {
				const payload = {
					page: Math.floor(dtData.start / dtData.length) + 1,
					per_page: dtData.length,
					material: this.filters.material,
					type: this.filters.type,
					date_start: this.filters.date_start,
					date_end: this.filters.date_end,
				};

				RIM.request('rim_list_transactions', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						callback({
							data: [],
							recordsTotal: 0,
							recordsFiltered: 0,
							draw: dtData.draw,
						});
						return;
					}

					callback({
						data: response.data.items || [],
						recordsTotal: response.data.total || 0,
						recordsFiltered: response.data.total || 0,
						draw: dtData.draw,
					});
				});
			},

			reloadTable() {
				if (this.table) {
					this.table.ajax.reload();
				}
			},

			resetFilters() {
				this.filters = {
					material: '',
					type: '',
					date_start: '',
					date_end: '',
				};
				this.reloadTable();
			},

			submitTransaction() {
				this.submitting = true;
				const payload = Object.assign({}, this.form);

				if (payload.type === 'use') {
					payload.price = '';
					payload.supplier = '';
				} else {
					payload.reason = '';
				}

				RIM.request('rim_create_transaction', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						return;
					}

					RIM.showToast('success', response.message || window.rimAdminData.i18n.success);
					this.reloadTable();
					this.resetForm();
				}).finally(() => {
					this.submitting = false;
				});
			},

			resetForm() {
				this.form = {
					material_id: '',
					type: 'add',
					quantity: '',
					price: '',
					supplier: '',
					reason: '',
					transaction_date: '',
				};
			},

			exportCsv() {
				this.exporting = true;
				const payload = Object.assign({}, this.filters);

				RIM.request('rim_export_transactions_csv', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						return;
					}

					RIM.downloadBase64(response.data.filename, response.data.payload);
				}).finally(() => {
					this.exporting = false;
				});
			},
		};
	};

	window.RIMReportsApp = function () {
		return {
			filters: {
				date_start: '',
				date_end: '',
			},
			summary: {
				purchases_quantity: '0.000',
				purchases_value: '0.00',
				usage_quantity: '0.000',
			},
			purchases: [],
			usage: [],
			exportingPurchases: false,
			exportingUsage: false,

			init() {
				this.resetFilters();
			},

			resetFilters() {
				const now = new Date();
				const end = now.toISOString().slice(0, 10);
				const weekAgo = new Date(now.getTime() - 6 * 24 * 60 * 60 * 1000)
					.toISOString()
					.slice(0, 10);
				this.filters.date_start = weekAgo;
				this.filters.date_end = end;
				this.reloadAll();
			},

			reloadAll() {
				this.loadSummary();
				this.loadPurchases();
				this.loadUsage();
			},

			loadSummary() {
				RIM.request('rim_report_summary', this.filters).then((response) => {
					if (response.success) {
						this.summary = response.data;
					}
				});
			},

			loadPurchases() {
				RIM.request('rim_report_purchases', this.filters).then((response) => {
					if (response.success) {
						this.purchases = response.data.items || [];
					}
				});
			},

			loadUsage() {
				RIM.request('rim_report_usage', this.filters).then((response) => {
					if (response.success) {
						this.usage = response.data.items || [];
					}
				});
			},

			exportPurchases() {
				this.exportingPurchases = true;
				RIM.request('rim_report_purchases', Object.assign({ export: true }, this.filters)).then((response) => {
					if (response.success && response.data.items) {
						this.generateCsv(
							'RIM-purchases',
							['Material', 'Unit', 'Total Quantity', 'Total Value'],
							response.data.items.map((row) => [row.name, row.unit, row.qty, row.value])
						);
					} else {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
					}
				}).finally(() => {
					this.exportingPurchases = false;
				});
			},

			exportUsage() {
				this.exportingUsage = true;
				RIM.request('rim_report_usage', Object.assign({ export: true }, this.filters)).then((response) => {
					if (response.success && response.data.items) {
						this.generateCsv(
							'RIM-usage',
							['Material', 'Unit', 'Total Quantity'],
							response.data.items.map((row) => [row.name, row.unit, row.qty])
						);
					} else {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
					}
				}).finally(() => {
					this.exportingUsage = false;
				});
			},

			generateCsv(filenamePrefix, headers, rows) {
				const lines = [];
				lines.push(headers.join(','));
				rows.forEach((row) => {
					lines.push(row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','));
				});
				const csvString = lines.join('\n');
				const base64 = window.btoa(unescape(encodeURIComponent(csvString)));
				const filename = `${filenamePrefix}-${new Date().toISOString().slice(0, 10)}.csv`;
				RIM.downloadBase64(filename, base64);
			},
		};
	};

	window.RIMSettingsApp = function () {
		return {
			form: {
				alerts_enabled: true,
				alert_email: '',
				units_list: [],
			},
			unitsInput: '',
			saving: false,

			init(initial) {
				if (initial) {
					this.form = Object.assign({}, initial);
					this.unitsInput = (initial.units_list || []).join(', ');
				}
			},

			save() {
				this.saving = true;

				const payload = {
					alerts_enabled: this.form.alerts_enabled ? 1 : 0,
					alert_email: this.form.alert_email,
					units_list: this.unitsInput,
				};

				RIM.request('rim_save_settings', payload).then((response) => {
					if (!response.success) {
						RIM.showToast('error', response.message || window.rimAdminData.i18n.error);
						return;
					}

					this.form = response.data.settings;
					this.unitsInput = (response.data.settings.units_list || []).join(', ');
					window.rimAdminData.settings.units = response.data.settings.units_list;
					RIM.showToast('success', response.message || window.rimAdminData.i18n.success);
				}).finally(() => {
					this.saving = false;
				});
			},
		};
	};
})(jQuery, window, document);
