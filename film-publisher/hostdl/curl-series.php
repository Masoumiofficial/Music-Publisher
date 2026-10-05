<?php
if ( defined( 'ABSPATH' ) ) {
	return;
}
require_once __DIR__ . '/bootstrap.php';
set_time_limit( 0 );
$title  = smp_host_safe_name( smp_host_post( 'title_en' ) );
$season = max( 1, (int) smp_host_post( 'season' ) );
$root   = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;
$dir    = $root . '/series/' . $title;
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
for ( $i = 1; $i <= 20; $i++ ) {
	$a = smp_host_post( 'ep_1080_' . $i );
	$b = smp_host_post( 'ep_720_' . $i );
	if ( $a ) {
		smp_host_download( $a, $dir . '/S' . $season . 'E' . $i . '.1080.mp4' );
	}
	if ( $b ) {
		smp_host_download( $b, $dir . '/S' . $season . 'E' . $i . '.720.mp4' );
	}
}
http_response_code( 200 );
echo 'OK';
