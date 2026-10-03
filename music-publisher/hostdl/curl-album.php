<?php
if ( defined( 'ABSPATH' ) ) { return; }
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/jdf.php';

ini_set( 'max_execution_time', 900 );
set_time_limit( 0 );

$jdate     = jdate_win( 'Y/m/', time() );
$artist_en = smp_host_safe_name( smp_host_post( 'artist_en' ) );
$song_en   = smp_host_safe_name( smp_host_post( 'song_en' ) );
$cover     = smp_host_post( 'cover' );
$album     = $artist_en . ' - ' . $song_en;
$root      = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;

$dir128 = 'album/' . $jdate . $album . ' [128]';
$dir320 = 'album/' . $jdate . $album . ' [320]';
if ( ! file_exists( $dir128 ) ) {
	mkdir( $dir128, 0755, true );
}
if ( ! file_exists( $dir320 ) ) {
	mkdir( $dir320, 0755, true );
}

$mysrc = $root . '/' . $dir128 . '/' . $album . '.jpg';
if ( $cover ) {
	smp_host_download( $cover, $mysrc );
}

for ( $i = 1; $i <= 15; $i++ ) {
	$tname = smp_host_post( 'hs_trackname_' . $i );
	if ( $tname === '' ) {
		$tname = smp_host_post( 'track_name_' . $i );
	}
	$tname = smp_host_safe_name( $tname );
	if ( $tname === 'file' && smp_host_post( 'hs_trackname_' . $i ) === '' && smp_host_post( 'track_name_' . $i ) === '' ) {
		continue;
	}
	$num   = ( $i <= 9 ) ? '0' . $i : (string) $i;
	$l320  = smp_host_post( 'hs_link_320_' . $i );
	if ( $l320 === '' ) {
		$l320 = smp_host_post( 'track_link320_' . $i );
	}
	$l128 = smp_host_post( 'hs_link_128_' . $i );
	if ( $l128 === '' ) {
		$l128 = smp_host_post( 'track_link128_' . $i );
	}
	if ( $l320 ) {
		smp_host_download( $l320, $root . '/' . $dir320 . '/' . $num . '. ' . $artist_en . ' - ' . $tname . '.mp3' );
	}
	if ( $l128 ) {
		smp_host_download( $l128, $root . '/' . $dir128 . '/' . $num . '. ' . $artist_en . ' - ' . $tname . ' [128].mp3' );
	}
}

function smp_zip_dir( $folder, $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return;
	}
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $folder, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			continue;
		}
		$file_path     = $file->getRealPath();
		$relative_path = substr( $file_path, strlen( $folder ) + 1 );
		$zip->addFile( $file_path, $relative_path );
	}
	$zip->close();
}

smp_zip_dir( $root . '/' . $dir320, $root . '/' . $dir320 . '.zip' );
smp_zip_dir( $root . '/' . $dir128, $root . '/' . $dir128 . '.zip' );
if ( is_file( $mysrc ) ) {
	unlink( $mysrc );
}
http_response_code( 200 );
echo 'OK';
