<?php
/**
 * Plugin Name:       ترانه‌یار | TaranehYar
 * Plugin URI:        https://sajadmasoumi.com
 * Description:       پنل حرفه‌ای ارسال پست موزیک، ریمیکس، نوحه، موزیک ویدیو، آلبوم و لیچ فایل برای سایت‌های موزیک وردپرسی
 * Version:           1.0.0
 * Author:            سجاد معصومی
 * Author URI:        https://sajadmasoumi.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       taranehyar
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TY_VERSION', '1.0.0' );
define( 'TY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TY_PLUGIN_DIR . 'includes/class-ty-settings.php';
require_once TY_PLUGIN_DIR . 'includes/class-ty-http.php';
require_once TY_PLUGIN_DIR . 'includes/class-ty-queue.php';
require_once TY_PLUGIN_DIR . 'includes/class-ty-admin-pages.php';
require_once TY_PLUGIN_DIR . 'includes/class-ty-post-handler.php';

function ty_init() {
    $settings = new TaranehYar_Settings();
    $settings->init();

    $queue = new TaranehYar_Queue();
    $queue->init();

    $admin = new TaranehYar_Admin_Pages();
    $admin->init();

    $handler = new TaranehYar_Post_Handler();
    $handler->init();
}
add_action( 'plugins_loaded', 'ty_init' );
