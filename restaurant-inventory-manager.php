<?php
/**
 * Plugin Name: Restaurant Inventory Manager
 * Plugin URI: https://nurislam.online
 * Description: Manage restaurant raw materials inventory with AJAX-driven admin tools, transaction logs, and low-stock alerts.
 * Version: 1.0.0
 * Author: Md. Nur Islam
 * Author URI: https://nurislam.online
 * Text Domain: restaurant-inventory-manager
 * Domain Path: /languages
 * GitHub Plugin URI: nursm86/restaurant-inventory-manager
 * Primary Branch: main
 *
 * @package SwissBakeryCustomCodes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'RIM_VERSION' ) || define( 'RIM_VERSION', '1.0.0' );
defined( 'RIM_PLUGIN_FILE' ) || define( 'RIM_PLUGIN_FILE', __FILE__ );
defined( 'RIM_PLUGIN_DIR' ) || define( 'RIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'RIM_PLUGIN_URL' ) || define( 'RIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RIM_PLUGIN_DIR . 'includes/helpers.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-activator.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-deactivator.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-uninstaller.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-admin.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-ajax.php';
require_once RIM_PLUGIN_DIR . 'includes/class-rim-email.php';

register_activation_hook( RIM_PLUGIN_FILE, array( 'RIM_Activator', 'activate' ) );
register_deactivation_hook( RIM_PLUGIN_FILE, array( 'RIM_Deactivator', 'deactivate' ) );
register_uninstall_hook( RIM_PLUGIN_FILE, array( 'RIM_Uninstaller', 'uninstall' ) );

/**
 * Boots the plugin.
 *
 * @return void
 */
function rim_bootstrap() {
	load_plugin_textdomain( 'restaurant-inventory-manager', false, dirname( plugin_basename( RIM_PLUGIN_FILE ) ) . '/languages' );

	if ( ! is_admin() ) {
		return;
	}

	$admin = new RIM_Admin();
	$admin->hooks();

	$ajax = new RIM_Ajax();
	$ajax->hooks();
}
add_action( 'plugins_loaded', 'rim_bootstrap' );

