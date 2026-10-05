<?php
if ( defined( 'ABSPATH' ) ) {
	return;
}
require_once __DIR__ . '/bootstrap.php';
set_time_limit( 0 );
$title = smp_host_safe_name( smp_host_post( 'title_en' ) );
$root  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;
$dir   = $root . '/movie';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
$map = array(
	'4k'   => smp_host_post( '4k' ),
	'1080' => smp_host_post( '1080' ),
	'720'  => smp_host_post( '720' ),
	'480'  => smp_host_post( '480' ),
	'sub'  => smp_host_post( 'sub' ),
);
foreach ( $map as $q => $url ) {
	if ( ! $url ) {
		continue;
	}
	$ext = ( 'sub' === $q ) ? '.srt' : '.mp4';
	smp_host_download( $url, $dir . '/' . $title . '.' . $q . $ext );
}
http_response_code( 200 );
echo 'OK';
