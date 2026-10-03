<?php
/**
 * Plugin Name:       Film Series Publisher
 * Plugin URI:        https://etehadwp.com
 * Description:       پنل ارسال فیلم، سریال، تریلر و لیچ فایل برای سایت‌های فیلم و سریال وردپرسی.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            تیم توسعه اتحاد وردپرس
 * Author URI:        https://etehadwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       film-publisher
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FSP_VERSION' ) ) {
	define( 'FSP_VERSION', '1.0.0' );
}
if ( ! defined( 'FSP_PLUGIN_FILE' ) ) {
	define( 'FSP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'FSP_PLUGIN_DIR' ) ) {
	define( 'FSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FSP_PLUGIN_URL' ) ) {
	define( 'FSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

$fsp_includes = array(
	'includes/class-fsp-settings.php',
	'includes/class-fsp-admin-pages.php',
	'includes/class-fsp-post-handler.php',
);
foreach ( $fsp_includes as $fsp_rel ) {
	$fsp_path = FSP_PLUGIN_DIR . $fsp_rel;
	if ( ! is_readable( $fsp_path ) ) {
		return;
	}
	require_once $fsp_path;
}

if ( ! class_exists( 'FSP_Settings', false ) || ! class_exists( 'FSP_Admin_Pages', false ) || ! class_exists( 'FSP_Post_Handler', false ) ) {
	return;
}

if ( ! function_exists( 'fsp_load_textdomain' ) ) {
	function fsp_load_textdomain() {
		load_plugin_textdomain( 'film-publisher', false, dirname( plugin_basename( FSP_PLUGIN_FILE ) ) . '/languages' );
	}
}
add_action( 'plugins_loaded', 'fsp_load_textdomain', 1 );

if ( ! function_exists( 'fsp_init' ) ) {
	function fsp_init() {
		$settings = new FSP_Settings();
		$settings->init();
		$admin = new FSP_Admin_Pages();
		$admin->init();
		$handler = new FSP_Post_Handler();
		$handler->init();
	}
}
add_action( 'plugins_loaded', 'fsp_init', 10 );

if ( ! function_exists( 'fsp_activate' ) ) {
	function fsp_activate() {
		if ( false === get_option( 'fsp_options', false ) ) {
			add_option( 'fsp_options', array() );
		}
		update_option( 'fsp_db_version', FSP_VERSION );
	}
}
register_activation_hook( FSP_PLUGIN_FILE, 'fsp_activate' );

if ( ! function_exists( 'fsp_deactivate' ) ) {
	function fsp_deactivate() {}
}
register_deactivation_hook( FSP_PLUGIN_FILE, 'fsp_deactivate' );
