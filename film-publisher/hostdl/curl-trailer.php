<?php
if ( defined( 'ABSPATH' ) ) {
	return;
}
require_once __DIR__ . '/bootstrap.php';
$title = smp_host_safe_name( smp_host_post( 'title_en' ) );
$link  = smp_host_post( 'link' );
$root  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;
$dir   = $root . '/trailer';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
if ( $link ) {
	smp_host_download( $link, $dir . '/' . $title . '.mp4' );
}
http_response_code( 200 );
echo 'OK';
