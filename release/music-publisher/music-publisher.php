<?php
/**
 * Plugin Name:       Sajad Music Publisher
 * Plugin URI:        https://etehadwp.com
 * Description:       Admin workflow to publish singles, remixes, nohe, music videos, albums, and leech files to Persian music WordPress sites.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            تیم توسعه اتحاد وردپرس
 * Author URI:        https://etehadwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sajad-music-publisher
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SMP_VERSION' ) ) {
	define( 'SMP_VERSION', '1.0.0' );
}
if ( ! defined( 'SMP_PLUGIN_FILE' ) ) {
	define( 'SMP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'SMP_PLUGIN_DIR' ) ) {
	define( 'SMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SMP_PLUGIN_URL' ) ) {
	define( 'SMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

$smp_includes = array(
	'includes/class-smp-settings.php',
	'includes/class-smp-admin-pages.php',
	'includes/class-smp-post-handler.php',
);
foreach ( $smp_includes as $smp_rel ) {
	$smp_path = SMP_PLUGIN_DIR . $smp_rel;
	if ( ! is_readable( $smp_path ) ) {
		return;
	}
	require_once $smp_path;
}

if ( ! class_exists( 'SMP_Settings', false ) || ! class_exists( 'SMP_Admin_Pages', false ) || ! class_exists( 'SMP_Post_Handler', false ) ) {
	return;
}

if ( ! function_exists( 'smp_load_textdomain' ) ) {
	/**
	 * Load translations.
	 */
	function smp_load_textdomain() {
		load_plugin_textdomain( 'sajad-music-publisher', false, dirname( plugin_basename( SMP_PLUGIN_FILE ) ) . '/languages' );
	}
}
add_action( 'plugins_loaded', 'smp_load_textdomain', 1 );

if ( ! function_exists( 'smp_init' ) ) {
	/**
	 * Bootstrap plugin classes.
	 */
	function smp_init() {
		$settings = new SMP_Settings();
		$settings->init();

		$admin = new SMP_Admin_Pages();
		$admin->init();

		$handler = new SMP_Post_Handler();
		$handler->init();
	}
}
add_action( 'plugins_loaded', 'smp_init', 10 );

if ( ! function_exists( 'smp_activate' ) ) {
	/**
	 * Activation: store version. Safe to run repeatedly.
	 */
	function smp_activate() {
		if ( false === get_option( 'smp_options', false ) ) {
			add_option( 'smp_options', array() );
		}
		update_option( 'smp_db_version', SMP_VERSION );
	}
}
register_activation_hook( SMP_PLUGIN_FILE, 'smp_activate' );

if ( ! function_exists( 'smp_deactivate' ) ) {
	/**
	 * Deactivation: do not delete user data.
	 */
	function smp_deactivate() {
	}
}
register_deactivation_hook( SMP_PLUGIN_FILE, 'smp_deactivate' );
