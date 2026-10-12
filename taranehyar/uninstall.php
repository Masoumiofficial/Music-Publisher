<?php
/**
 * حذف کامل داده‌های افزونه هنگام Uninstall
 *
 * @package TaranehYar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ty_options' );
