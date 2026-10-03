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

define( 'SMP_VERSION', '1.0.0' );
define( 'SMP_PLUGIN_FILE', __FILE__ );
define( 'SMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SMP_PLUGIN_DIR . 'includes/class-smp-settings.php';
require_once SMP_PLUGIN_DIR . 'includes/class-smp-admin-pages.php';
require_once SMP_PLUGIN_DIR . 'includes/class-smp-post-handler.php';

/**
 * Load translations.
 */
function smp_load_textdomain() {
	load_plugin_textdomain( 'sajad-music-publisher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'smp_load_textdomain', 1 );

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
add_action( 'plugins_loaded', 'smp_init', 10 );

/**
 * Activation: store version. Safe to run repeatedly.
 */
function smp_activate() {
	if ( false === get_option( 'smp_options', false ) ) {
		add_option( 'smp_options', array() );
	}
	update_option( 'smp_db_version', SMP_VERSION );
}
register_activation_hook( __FILE__, 'smp_activate' );

/**
 * Deactivation: do not delete user data.
 */
function smp_deactivate() {
	// Intentionally empty: posts, media, and settings are preserved.
}
register_deactivation_hook( __FILE__, 'smp_deactivate' );
