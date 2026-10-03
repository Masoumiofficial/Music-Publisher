<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/jdf.php';

$dir = jdate_win( 'Y/m/d', time() );
$dir_file = 'video/' . $dir;
if ( ! file_exists( $dir_file ) ) {
	mkdir( $dir_file, 0755, true );
}
set_time_limit( 0 );

$enname   = smp_host_safe_name( smp_host_post( 'artist_en' ) !== '' ? smp_host_post( 'artist_en' ) : smp_host_post( 'enname' ) );
$entrack  = smp_host_safe_name( smp_host_post( 'song_en' ) !== '' ? smp_host_post( 'song_en' ) : smp_host_post( 'entrack' ) );
$link1080 = smp_host_post( 'hs_video1080p' ) !== '' ? smp_host_post( 'hs_video1080p' ) : smp_host_post( 'link1080' );
$link720  = smp_host_post( 'hs_video720p' ) !== '' ? smp_host_post( 'hs_video720p' ) : smp_host_post( 'link720' );
$link480  = smp_host_post( 'hs_video480p' ) !== '' ? smp_host_post( 'hs_video480p' ) : smp_host_post( 'link480' );
$link320  = smp_host_post( 'link320' );

$trackname = $enname . ' - ' . $entrack;
$root      = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;

$map = array(
	$link1080 => $root . '/' . $dir_file . '/' . $trackname . ' [1080].mp4',
	$link720  => $root . '/' . $dir_file . '/' . $trackname . ' [720].mp4',
	$link480  => $root . '/' . $dir_file . '/' . $trackname . ' [480].mp4',
	$link320  => $root . '/' . $dir_file . '/' . $trackname . '.mp3',
);
foreach ( $map as $url => $dest ) {
	if ( $url ) {
		smp_host_download( $url, $dest );
	}
}
http_response_code( 200 );
echo 'OK';
