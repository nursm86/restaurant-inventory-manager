<?php
/**
 * Registers AJAX handlers for Restaurant Inventory Manager.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIM_Ajax
 */
class RIM_Ajax {

	/**
	 * Hooks AJAX actions.
	 *
	 * @return void
	 */
	public function hooks() {
		$actions = array(
			'rim_add_material'             => 'handle_add_material',
			'rim_update_material'          => 'handle_update_material',
			'rim_delete_material'          => 'handle_delete_material',
			'rim_list_materials'           => 'handle_list_materials',
			'rim_create_transaction'       => 'handle_create_transaction',
			'rim_list_transactions'        => 'handle_list_transactions',
			'rim_export_transactions_csv'  => 'handle_export_transactions_csv',
			'rim_report_summary'           => 'handle_report_summary',
			'rim_report_purchases'         => 'handle_report_purchases',
			'rim_report_usage'             => 'handle_report_usage',
			'rim_save_settings'            => 'handle_save_settings',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Ensures request is authorized.
	 *
	 * @return void
	 */
	protected function verify_request() {
		if ( ! rim_user_can_manage() ) {
			$this->send_error( __( 'You are not allowed to perform this action.', 'restaurant-inventory-manager' ), array(), 403 );
		}

		check_ajax_referer( 'rim_admin_nonce', 'nonce' );
	}

	/**
	 * Sends successful JSON response.
	 *
	 * @param array  $data Payload.
	 * @param string $message Message.
	 * @return void
	 */
	protected function send_success( $data = array(), $message = '' ) {
		wp_send_json(
			array(
				'success' => true,
				'data'    => $data,
				'message' => $message,
			)
		);
	}

	/**
	 * Sends error JSON response.
	 *
	 * @param string $message Error message.
	 * @param array  $data Extra data.
	 * @param int    $code HTTP status.
	 * @return void
	 */
	protected function send_error( $message, $data = array(), $code = 400 ) {
		wp_send_json(
			array(
				'success' => false,
				'data'    => $data,
				'message' => $message,
			),
			$code
		);
	}

	/**
	 * Handles material creation.
	 *
	 * @return void
	 */
	public function handle_add_material() {
		$this->verify_request();

		global $wpdb;

		$name             = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$unit_type        = isset( $_POST['unit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['unit_type'] ) ) : '';
		$warning_quantity = isset( $_POST['warning_quantity'] ) ? rim_sanitize_decimal( wp_unslash( $_POST['warning_quantity'] ) ) : 0;
		$supplier         = isset( $_POST['supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier'] ) ) : '';
		$price            = isset( $_POST['price'] ) ? rim_sanitize_decimal( wp_unslash( $_POST['price'] ), 2 ) : null;
		$quantity         = isset( $_POST['quantity'] ) ? rim_sanitize_decimal( wp_unslash( $_POST['quantity'] ) ) : 0;

		if ( '' === $name ) {
			$this->send_error( __( 'Material name is required.', 'restaurant-inventory-manager' ) );
		}

		if ( $warning_quantity < 0 ) {
			$this->send_error( __( 'Warning quantity cannot be negative.', 'restaurant-inventory-manager' ) );
		}

		if ( '' === $unit_type ) {
			$this->send_error( __( 'Please select a unit.', 'restaurant-inventory-manager' ) );
		}

		$table = $wpdb->prefix . 'rim_raw_materials';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE name = %s",
				$name
			)
		);

		if ( $exists ) {
			$this->send_error( __( 'A material with that name already exists.', 'restaurant-inventory-manager' ) );
		}

		$now     = current_time( 'mysql' );
		$user_id = get_current_user_id();

		$data = array(
			'name'             => $name,
			'unit_type'        => $unit_type,
			'quantity'         => $quantity,
			'warning_quantity' => $warning_quantity,
			'last_updated'     => $now,
			'last_edited_by'   => $user_id,
			'created_at'       => $now,
		);

		$format = array( '%s', '%s', '%f', '%f', '%s', '%d', '%s' );

		if ( $supplier ) {
			$data['supplier'] = $supplier;
			$format[]         = '%s';
		}

		if ( null !== $price && '' !== $price ) {
			$data['price'] = rim_sanitize_decimal( $price, 2 );
			$format[]      = '%f';
		}

		$inserted = $wpdb->insert( $table, $data, $format );

		if ( ! $inserted ) {
			$this->send_error( __( 'Failed to create material.', 'restaurant-inventory-manager' ) );
		}

		$material_id = (int) $wpdb->insert_id;
		$material    = $this->get_material( $material_id );

		$this->send_success(
			array(
				'material' => $material,
			),
			__( 'Material created successfully.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Handles material updates.
	 *
	 * @return void
	 */
	public function handle_update_material() {
		$this->verify_request();

		global $wpdb;

		$material_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $material_id ) {
			$this->send_error( __( 'Invalid material ID.', 'restaurant-inventory-manager' ) );
		}

		$material = $this->get_material( $material_id );

		if ( ! $material ) {
			$this->send_error( __( 'Material not found.', 'restaurant-inventory-manager' ), array(), 404 );
		}

		$data   = array();
		$format = array();

		if ( isset( $_POST['name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
			if ( '' === $name ) {
				$this->send_error( __( 'Material name cannot be empty.', 'restaurant-inventory-manager' ) );
			}

			if ( strtolower( $name ) !== strtolower( $material['name'] ) ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}rim_raw_materials WHERE name = %s AND id != %d",
						$name,
						$material_id
					)
				);

				if ( $exists ) {
					$this->send_error( __( 'A material with that name already exists.', 'restaurant-inventory-manager' ) );
				}
			}

			$data['name'] = $name;
			$format[]     = '%s';
		}

		if ( isset( $_POST['unit_type'] ) ) {
			$data['unit_type'] = sanitize_text_field( wp_unslash( $_POST['unit_type'] ) );
			$format[]          = '%s';
		}

		if ( isset( $_POST['warning_quantity'] ) ) {
			$warning_quantity = rim_sanitize_decimal( wp_unslash( $_POST['warning_quantity'] ) );

			if ( $warning_quantity < 0 ) {
				$this->send_error( __( 'Warning quantity cannot be negative.', 'restaurant-inventory-manager' ) );
			}

			$data['warning_quantity'] = $warning_quantity;
			$format[]                 = '%f';
		}

		if ( isset( $_POST['supplier'] ) ) {
			$data['supplier'] = sanitize_text_field( wp_unslash( $_POST['supplier'] ) );
			$format[]         = '%s';
		}

		if ( array_key_exists( 'price', $_POST ) ) {
			$price = wp_unslash( $_POST['price'] );
			if ( '' === $price ) {
				$data['price'] = null;
				$format[]      = '%s';
			} else {
				$data['price'] = rim_sanitize_decimal( $price, 2 );
				$format[]      = '%f';
			}
		}

		if ( empty( $data ) ) {
			$this->send_error( __( 'No fields to update.', 'restaurant-inventory-manager' ) );
		}

		$data['last_updated']   = current_time( 'mysql' );
		$data['last_edited_by'] = get_current_user_id();
		$format[]               = '%s';
		$format[]               = '%d';

		$updated = $wpdb->update(
			$wpdb->prefix . 'rim_raw_materials',
			$data,
			array( 'id' => $material_id ),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			$this->send_error( __( 'Failed to update material.', 'restaurant-inventory-manager' ) );
		}

		$material = $this->get_material( $material_id );

		$this->send_success(
			array(
				'material' => $material,
			),
			__( 'Material updated successfully.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Handles material deletion.
	 *
	 * @return void
	 */
	public function handle_delete_material() {
		$this->verify_request();

		global $wpdb;

		$material_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $material_id ) {
			$this->send_error( __( 'Invalid material ID.', 'restaurant-inventory-manager' ) );
		}

		$material = $this->get_material( $material_id );

		if ( ! $material ) {
			$this->send_error( __( 'Material not found.', 'restaurant-inventory-manager' ), array(), 404 );
		}

		$transactions_table = $wpdb->prefix . 'rim_transactions';
		$has_transactions   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$transactions_table} WHERE material_id = %d",
				$material_id
			)
		);

		if ( $has_transactions ) {
			$this->send_error( __( 'Material cannot be deleted while transactions exist.', 'restaurant-inventory-manager' ) );
		}

		$deleted = $wpdb->delete(
			$wpdb->prefix . 'rim_raw_materials',
			array( 'id' => $material_id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			$this->send_error( __( 'Failed to delete material.', 'restaurant-inventory-manager' ) );
		}

		$this->send_success(
			array(
				'id' => $material_id,
			),
			__( 'Material deleted.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Returns paginated materials.
	 *
	 * @return void
	 */
	public function handle_list_materials() {
		$this->verify_request();

		$page     = isset( $_REQUEST['page'] ) ? max( 1, absint( $_REQUEST['page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = isset( $_REQUEST['per_page'] ) ? max( 1, absint( $_REQUEST['per_page'] ) ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search   = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_by = isset( $_REQUEST['order_by'] ) ? sanitize_key( $_REQUEST['order_by'] ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_key( $_REQUEST['order'] ) ) : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$orderable = array( 'name', 'unit_type', 'quantity', 'warning_quantity', 'last_updated', 'supplier', 'price' );
		if ( ! in_array( $order_by, $orderable, true ) ) {
			$order_by = 'name';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$result = $this->get_materials(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => $search,
				'order_by' => $order_by,
				'order'    => $order,
			)
		);

		$this->send_success( $result );
	}

	/**
	 * Handles transaction creation.
	 *
	 * @return void
	 */
	public function handle_create_transaction() {
		$this->verify_request();

		global $wpdb;

		$material_id      = isset( $_POST['material_id'] ) ? absint( $_POST['material_id'] ) : 0;
		$type             = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$quantity         = isset( $_POST['quantity'] ) ? rim_sanitize_decimal( wp_unslash( $_POST['quantity'] ) ) : 0;
		$price            = array_key_exists( 'price', $_POST ) ? wp_unslash( $_POST['price'] ) : null;
		$supplier         = isset( $_POST['supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier'] ) ) : '';
		$reason           = isset( $_POST['reason'] ) ? wp_kses_post( wp_unslash( $_POST['reason'] ) ) : '';
		$transaction_date = isset( $_POST['transaction_date'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_date'] ) ) : '';

		if ( ! in_array( $type, array( 'add', 'use' ), true ) ) {
			$this->send_error( __( 'Invalid transaction type.', 'restaurant-inventory-manager' ) );
		}

		if ( $quantity <= 0 ) {
			$this->send_error( __( 'Quantity must be greater than zero.', 'restaurant-inventory-manager' ) );
		}

		$material = $this->get_material( $material_id, true );

		if ( ! $material ) {
			$this->send_error( __( 'Material not found.', 'restaurant-inventory-manager' ), array(), 404 );
		}

		$date_time = $this->parse_datetime( $transaction_date );

		$user_id = get_current_user_id();

		$new_quantity = (float) $material['quantity'];
		if ( 'add' === $type ) {
			$new_quantity += $quantity;
		} else {
			$new_quantity -= $quantity;

			if ( $new_quantity < 0 ) {
				$this->send_error( __( 'Not enough stock available for this operation.', 'restaurant-inventory-manager' ) );
			}
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$transactions_table = $wpdb->prefix . 'rim_transactions';

		$data = array(
			'material_id'      => $material_id,
			'type'             => $type,
			'quantity'         => $quantity,
			'transaction_date' => $date_time,
			'created_by'       => $user_id,
			'created_at'       => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%f', '%s', '%d', '%s' );

		if ( null !== $price && '' !== $price ) {
			$data['price'] = rim_sanitize_decimal( $price, 2 );
			$format[]      = '%f';
		}

		if ( $supplier ) {
			$data['supplier'] = $supplier;
			$format[]         = '%s';
		}

		if ( $reason ) {
			$data['reason'] = $reason;
			$format[]       = '%s';
		}

		$inserted = $wpdb->insert( $transactions_table, $data, $format );

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->send_error( __( 'Failed to record transaction.', 'restaurant-inventory-manager' ) );
		}

		$update_data = array(
			'quantity'       => $new_quantity,
			'last_updated'   => current_time( 'mysql' ),
			'last_edited_by' => $user_id,
		);
		$update_format = array( '%f', '%s', '%d' );

		if ( 'add' === $type ) {
			if ( $price ) {
				$update_data['price'] = rim_sanitize_decimal( $price, 2 );
				$update_format[]      = '%f';
			}
			if ( $supplier ) {
				$update_data['supplier'] = $supplier;
				$update_format[]         = '%s';
			}
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'rim_raw_materials',
			$update_data,
			array( 'id' => $material_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->send_error( __( 'Failed to update inventory.', 'restaurant-inventory-manager' ) );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$updated_material = $this->get_material( $material_id );

		if ( $updated_material && $updated_material['warning_quantity'] > 0 && $updated_material['quantity'] < $updated_material['warning_quantity'] ) {
			set_transient(
				'rim_low_stock_notice',
				array(
					'name'             => $updated_material['name'],
					'quantity'         => $updated_material['quantity'],
					'warning_quantity' => $updated_material['warning_quantity'],
				),
				MINUTE_IN_SECONDS * 10
			);

			RIM_Email::send_low_stock_alert( $updated_material, $user_id );
		}

		$this->send_success(
			array(
				'material'     => $updated_material,
				'transaction'  => $this->format_transaction(
					$wpdb->get_row(
						$wpdb->prepare(
							"SELECT t.*, m.name AS material_name, m.unit_type, u.display_name AS created_by_name
							FROM {$wpdb->prefix}rim_transactions t
							LEFT JOIN {$wpdb->prefix}rim_raw_materials m ON t.material_id = m.id
							LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
							WHERE t.id = %d",
							$wpdb->insert_id
						),
						ARRAY_A
					)
				),
			),
			__( 'Transaction recorded successfully.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Returns paginated transactions.
	 *
	 * @return void
	 */
	public function handle_list_transactions() {
		$this->verify_request();

		$args = array(
			'page'       => isset( $_REQUEST['page'] ) ? max( 1, absint( $_REQUEST['page'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'per_page'   => isset( $_REQUEST['per_page'] ) ? max( 1, absint( $_REQUEST['per_page'] ) ) : 20, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'material'   => isset( $_REQUEST['material'] ) ? absint( $_REQUEST['material'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'type'       => isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_start' => isset( $_REQUEST['date_start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_start'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_end'   => isset( $_REQUEST['date_end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_end'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$result = $this->get_transactions( $args );

		$this->send_success( $result );
	}

	/**
	 * Exports transactions to CSV responding with base64 payload.
	 *
	 * @return void
	 */
	public function handle_export_transactions_csv() {
		$this->verify_request();

		$args = array(
			'page'       => 1,
			'per_page'   => 0,
			'material'   => isset( $_REQUEST['material'] ) ? absint( $_REQUEST['material'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'type'       => isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_start' => isset( $_REQUEST['date_start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_start'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_end'   => isset( $_REQUEST['date_end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_end'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$result       = $this->get_transactions( $args, false );
		$transactions = isset( $result['items'] ) ? $result['items'] : array();

		$handle = fopen( 'php://temp', 'w' );

		fputcsv(
			$handle,
			array(
				'Material',
				'Type',
				'Quantity',
				'Unit',
				'Price',
				'Supplier',
				'Reason',
				'Transaction Date',
				'Created By',
			)
		);

		foreach ( $transactions as $transaction ) {
			fputcsv(
				$handle,
				array(
					$transaction['material_name'],
					$transaction['type'],
					$transaction['quantity'],
					$transaction['unit'],
					$transaction['price'],
					$transaction['supplier'],
					strip_tags( $transaction['reason'] ),
					$transaction['transaction_date'],
					$transaction['created_by'],
				)
			);
		}

		rewind( $handle );
		$csv      = stream_get_contents( $handle );
		$filename = 'rim-transactions-' . gmdate( 'Ymd-His' ) . '.csv';

		fclose( $handle );

		$this->send_success(
			array(
				'filename' => $filename,
				'payload'  => base64_encode( $csv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obsolete_functions_base64_encode
			),
			__( 'Export generated.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Provides summary metrics for reports.
	 *
	 * @return void
	 */
	public function handle_report_summary() {
		$this->verify_request();

		global $wpdb;

		$date_start = isset( $_REQUEST['date_start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_end   = isset( $_REQUEST['date_end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$range = $this->normalize_date_range( $date_start, $date_end );

		$table = $wpdb->prefix . 'rim_transactions';

		$purchases = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(quantity) AS total_qty, SUM(price) AS total_value
				FROM {$table}
				WHERE type = 'add' AND transaction_date BETWEEN %s AND %s",
				$range['start'],
				$range['end']
			),
			ARRAY_A
		);

		$usage = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(quantity) FROM {$table} WHERE type = 'use' AND transaction_date BETWEEN %s AND %s",
				$range['start'],
				$range['end']
			)
		);

		$this->send_success(
			array(
				'purchases_quantity' => rim_format_quantity( $purchases['total_qty'] ?? 0 ),
				'purchases_value'    => rim_format_price( $purchases['total_value'] ?? 0 ),
				'usage_quantity'     => rim_format_quantity( $usage ?? 0 ),
			)
		);
	}

	/**
	 * Provides purchases breakdown.
	 *
	 * @return void
	 */
	public function handle_report_purchases() {
		$this->verify_request();

		global $wpdb;

		$date_start = isset( $_REQUEST['date_start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_end   = isset( $_REQUEST['date_end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$range = $this->normalize_date_range( $date_start, $date_end );

		$table = $wpdb->prefix . 'rim_transactions';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.name, m.unit_type, SUM(t.quantity) AS total_qty, SUM(t.price) AS total_value
				FROM {$table} t
				INNER JOIN {$wpdb->prefix}rim_raw_materials m ON t.material_id = m.id
				WHERE t.type = 'add' AND t.transaction_date BETWEEN %s AND %s
				GROUP BY m.name, m.unit_type
				ORDER BY m.name ASC",
				$range['start'],
				$range['end']
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'name'   => $row['name'],
				'unit'   => $row['unit_type'],
				'qty'    => rim_format_quantity( $row['total_qty'] ),
				'value'  => rim_format_price( $row['total_value'] ),
			);
		}

		$this->send_success( array( 'items' => $data ) );
	}

	/**
	 * Provides usage breakdown.
	 *
	 * @return void
	 */
	public function handle_report_usage() {
		$this->verify_request();

		global $wpdb;

		$date_start = isset( $_REQUEST['date_start'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_end   = isset( $_REQUEST['date_end'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$range = $this->normalize_date_range( $date_start, $date_end );

		$table = $wpdb->prefix . 'rim_transactions';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.name, m.unit_type, SUM(t.quantity) AS total_qty
				FROM {$table} t
				INNER JOIN {$wpdb->prefix}rim_raw_materials m ON t.material_id = m.id
				WHERE t.type = 'use' AND t.transaction_date BETWEEN %s AND %s
				GROUP BY m.name, m.unit_type
				ORDER BY m.name ASC",
				$range['start'],
				$range['end']
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'name' => $row['name'],
				'unit' => $row['unit_type'],
				'qty'  => rim_format_quantity( $row['total_qty'] ),
			);
		}

		$this->send_success( array( 'items' => $data ) );
	}

	/**
	 * Saves settings via AJAX.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->verify_request();

		$settings = array(
			'alerts_enabled' => isset( $_POST['alerts_enabled'] ) ? (bool) $_POST['alerts_enabled'] : false,
			'alert_email'    => isset( $_POST['alert_email'] ) ? sanitize_email( wp_unslash( $_POST['alert_email'] ) ) : '',
			'units_list'     => isset( $_POST['units_list'] ) ? wp_unslash( $_POST['units_list'] ) : array(),
		);

		$updated = rim_save_settings( $settings );

		$this->send_success(
			array(
				'settings' => $updated,
			),
			__( 'Settings saved.', 'restaurant-inventory-manager' )
		);
	}

	/**
	 * Retrieves a single material.
	 *
	 * @param int  $id Material ID.
	 * @param bool $raw Return raw row.
	 * @return array|null
	 */
	protected function get_material( $id, $raw = false ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}rim_raw_materials WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( $raw ) {
			return $row;
		}

		return $this->format_material( $row );
	}

	/**
	 * Retrieves materials with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	protected function get_materials( $args ) {
		global $wpdb;

		$page     = max( 1, (int) $args['page'] );
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		if ( $per_page <= 0 ) {
			$per_page = 9999;
		}
		$search   = isset( $args['search'] ) ? $args['search'] : '';
		$order_by = isset( $args['order_by'] ) ? $args['order_by'] : 'name';
		$order    = isset( $args['order'] ) ? $args['order'] : 'ASC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where  .= " AND (m.name LIKE %s OR m.unit_type LIKE %s OR m.supplier LIKE %s)";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$order_clause = "ORDER BY {$order_by} {$order}";

		$offset = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}rim_raw_materials m {$where}";
		$count     = $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql );

		$data_sql = "SELECT m.*, u.display_name AS last_edited_by_name
			FROM {$wpdb->prefix}rim_raw_materials m
			LEFT JOIN {$wpdb->users} u ON u.ID = m.last_edited_by
			{$where}
			{$order_clause}
			LIMIT %d OFFSET %d";

		$params_with_limit = $params;
		$params_with_limit[] = $per_page;
		$params_with_limit[] = $offset;

		$rows = $params_with_limit ? $wpdb->get_results( $wpdb->prepare( $data_sql, $params_with_limit ), ARRAY_A ) : $wpdb->get_results( $data_sql, ARRAY_A );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->format_material( $row );
		}

		return array(
			'items'     => $items,
			'total'     => (int) $count,
			'page'      => $page,
			'per_page'  => $per_page,
			'max_pages' => $per_page > 0 ? (int) ceil( $count / $per_page ) : 1,
		);
	}

	/**
	 * Retrieves transactions list.
	 *
	 * @param array $args Arguments.
	 * @param bool  $paginate Whether to paginate.
	 * @return array
	 */
	protected function get_transactions( $args, $paginate = true ) {
		global $wpdb;

		$page     = max( 1, (int) $args['page'] );
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		if ( $per_page <= 0 ) {
			$per_page = 9999;
		}
		$material = isset( $args['material'] ) ? (int) $args['material'] : 0;
		$type     = isset( $args['type'] ) ? $args['type'] : '';

		$range = $this->normalize_date_range( $args['date_start'] ?? '', $args['date_end'] ?? '' );

		$where  = 'WHERE 1=1';
		$params = array();

		$where .= ' AND t.transaction_date BETWEEN %s AND %s';
		$params[] = $range['start'];
		$params[] = $range['end'];

		if ( $material ) {
			$where    .= ' AND t.material_id = %d';
			$params[]  = $material;
		}

		if ( in_array( $type, array( 'add', 'use' ), true ) ) {
			$where    .= ' AND t.type = %s';
			$params[]  = $type;
		}

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}rim_transactions t {$where}";
		$count     = $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$order_clause = 'ORDER BY t.transaction_date DESC';

		$limit = '';
		if ( $paginate && $per_page > 0 ) {
			$offset = ( $page - 1 ) * $per_page;
			$limit  = $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		$data_sql = "
			SELECT t.*, m.name AS material_name, m.unit_type, u.display_name AS created_by_name
			FROM {$wpdb->prefix}rim_transactions t
			LEFT JOIN {$wpdb->prefix}rim_raw_materials m ON t.material_id = m.id
			LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
			{$where}
			{$order_clause}
			{$limit}";

		$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $params ), ARRAY_A );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->format_transaction( $row );
		}

		return array(
			'items'     => $items,
			'total'     => (int) $count,
			'page'      => $paginate ? $page : 1,
			'per_page'  => $paginate ? $per_page : count( $items ),
			'max_pages' => $paginate && $per_page > 0 ? (int) ceil( $count / $per_page ) : 1,
		);
	}

	/**
	 * Normalizes date range inputs.
	 *
	 * @param string $start Start date string.
	 * @param string $end End date string.
	 * @return array
	 */
	protected function normalize_date_range( $start, $end ) {
		$current    = current_time( 'timestamp' );
		$default_end = wp_date( 'Y-m-d H:i:s', $current );
		$default_start = wp_date( 'Y-m-d H:i:s', $current - WEEK_IN_SECONDS );

		if ( empty( $start ) ) {
			$start = $default_start;
		}

		if ( empty( $end ) ) {
			$end = $default_end;
		}

		$start_date = $this->parse_datetime( $start );
		$end_date   = $this->parse_datetime( $end );

		return array(
			'start' => $start_date,
			'end'   => $end_date,
		);
	}

	/**
	 * Parses incoming datetime strings and normalizes to MySQL format.
	 *
	 * @param string $value Input value.
	 * @return string
	 */
	protected function parse_datetime( $value ) {
		if ( empty( $value ) ) {
			return current_time( 'mysql' );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return current_time( 'mysql' );
		}

		return wp_date( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Formats material row.
	 *
	 * @param array $row Row data.
	 * @return array
	 */
	protected function format_material( $row ) {
		$user_display = $row['last_edited_by_name'];

		if ( empty( $user_display ) && ! empty( $row['last_edited_by'] ) ) {
			$user = get_userdata( (int) $row['last_edited_by'] );
			if ( $user ) {
				$user_display = $user->display_name;
			}
		}

		return array(
			'id'               => (int) $row['id'],
			'name'             => $row['name'],
			'unit_type'        => $row['unit_type'],
			'quantity'         => (float) $row['quantity'],
			'quantity_formatted' => rim_format_quantity( $row['quantity'] ),
			'warning_quantity' => (float) $row['warning_quantity'],
			'warning_quantity_formatted' => rim_format_quantity( $row['warning_quantity'] ),
			'supplier'         => $row['supplier'],
			'price'            => isset( $row['price'] ) && null !== $row['price'] ? (float) $row['price'] : null,
			'price_formatted'  => rim_format_price( $row['price'] ),
			'last_updated'     => $row['last_updated'],
			'last_updated_display' => $row['last_updated'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['last_updated'] ) ) : '',
			'last_edited_by'   => (int) $row['last_edited_by'],
			'last_edited_by_name' => $user_display ?: '',
			'created_at'       => $row['created_at'],
		);
	}

	/**
	 * Formats transaction row for JSON.
	 *
	 * @param array $row Row data.
	 * @return array
	 */
	protected function format_transaction( $row ) {
		return array(
			'id'               => (int) $row['id'],
			'material_id'      => (int) $row['material_id'],
			'material_name'    => $row['material_name'],
			'type'             => $row['type'],
			'quantity'         => rim_format_quantity( $row['quantity'] ),
			'unit'             => $row['unit_type'],
			'price'            => rim_format_price( $row['price'] ),
			'supplier'         => $row['supplier'],
			'reason'           => $row['reason'],
			'transaction_date' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['transaction_date'] ) ),
			'created_by'       => $row['created_by_name'],
		);
	}
}
