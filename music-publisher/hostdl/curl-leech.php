<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/jdf.php';

$dir = jdate_win( 'Y/m/d', time() );
$dir_file = 'music/' . $dir;
if ( ! file_exists( $dir_file ) ) {
	mkdir( $dir_file, 0755, true );
}
set_time_limit( 0 );

$enname      = smp_host_safe_name( smp_host_post( 'enname' ) );
$entrack     = smp_host_safe_name( smp_host_post( 'entrack' ) );
$file_names  = smp_host_safe_name( smp_host_post( 'FileNames' ) !== '' ? smp_host_post( 'FileNames' ) : ( $enname . ' - ' . $entrack ) );
$linkfilemp3 = smp_host_post( 'linkfilemp3' );
$linkfilemp4 = smp_host_post( 'linkfilemp4' );
$cover       = smp_host_post( 'cover' );

$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;
$path_mp3 = $root . '/' . $dir_file . '/' . $file_names . '.mp3';
$path_mp4 = $root . '/' . $dir_file . '/' . $file_names . '.mp4';
$mysrc    = $root . '/' . $dir_file . '/' . $file_names . '.jpg';

if ( $cover ) {
	smp_host_download( $cover, $mysrc );
}
if ( $linkfilemp3 ) {
	smp_host_download( $linkfilemp3, $path_mp3 );
}
if ( $linkfilemp4 ) {
	smp_host_download( $linkfilemp4, $path_mp4 );
}
http_response_code( 200 );
echo 'OK';
