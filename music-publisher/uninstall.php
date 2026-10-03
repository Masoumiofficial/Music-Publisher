<?php
/**
 * Fired when the plugin is uninstalled.
 * Deletes plugin options only. Posts, terms, and media are left intact.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'smp_options' );
delete_option( 'smp_db_version' );

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 500 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'smp_options' );
		delete_option( 'smp_db_version' );
		restore_current_blog();
	}
}
