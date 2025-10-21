<?php
/**
 * Handles plugin activation.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIM_Activator
 */
class RIM_Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::ensure_default_settings();
	}

	/**
	 * Creates database tables via dbDelta.
	 *
	 * @return void
	 */
	protected static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$materials_table = $wpdb->prefix . 'rim_raw_materials';
		$transactions_table = $wpdb->prefix . 'rim_transactions';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$materials_sql = "CREATE TABLE {$materials_table} (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			unit_type VARCHAR(30) NOT NULL DEFAULT 'pcs',
			quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
			warning_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
			supplier VARCHAR(190) DEFAULT NULL,
			price DECIMAL(12,2) DEFAULT NULL,
			last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_edited_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_name (name),
			KEY idx_unit_type (unit_type)
		) ENGINE=InnoDB {$charset_collate};";

		$transactions_sql = "CREATE TABLE {$transactions_table} (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			material_id INT UNSIGNED NOT NULL,
			type ENUM('add','use') NOT NULL,
			quantity DECIMAL(12,3) NOT NULL,
			price DECIMAL(12,2) DEFAULT NULL,
			supplier VARCHAR(190) DEFAULT NULL,
			reason TEXT DEFAULT NULL,
			transaction_date DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_material_id (material_id),
			KEY idx_type (type),
			KEY idx_transaction_date (transaction_date)
		) ENGINE=InnoDB {$charset_collate};";

		dbDelta( $materials_sql );
		dbDelta( $transactions_sql );
	}

	/**
	 * Seeds default settings when option missing.
	 *
	 * @return void
	 */
	protected static function ensure_default_settings() {
		if ( false === get_option( 'rim_settings', false ) ) {
			update_option(
				'rim_settings',
				array(
					'alerts_enabled' => true,
					'alert_email'    => get_option( 'admin_email' ),
					'units_list'     => array( 'kg', 'pcs', 'ltr', 'box', 'pack' ),
				)
			);
		}
	}
}
