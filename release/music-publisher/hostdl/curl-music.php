<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/jdf.php';

$dir = jdate_win( 'Y/m/d', time() );
$dir_file = 'music/' . $dir;
if ( ! file_exists( $dir_file ) ) {
	mkdir( $dir_file, 0755, true );
}
set_time_limit( 0 );

$artist_en = smp_host_safe_name( smp_host_post( 'artist_en' ) !== '' ? smp_host_post( 'artist_en' ) : smp_host_post( 'enname' ) );
$song_en   = smp_host_safe_name( smp_host_post( 'song_en' ) !== '' ? smp_host_post( 'song_en' ) : smp_host_post( 'entrack' ) );
$link320   = smp_host_post( 'link320' );
$link128   = smp_host_post( 'link128' );
$src       = smp_host_post( 'cover' );

$trackname = $artist_en . ' - ' . $song_en;
$root      = isset( $_SERVER['DOCUMENT_ROOT'] ) ? rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) : __DIR__;
$path320   = $root . '/' . $dir_file . '/' . $trackname . '.mp3';
$path128   = $root . '/' . $dir_file . '/' . $trackname . ' [128].mp3';
$mysrc     = $root . '/' . $dir_file . '/' . $trackname . '.jpg';

function smp_mp3_id_tags( $dirfile, $singer, $song, $axahang ) {
	if ( ! is_file( $dirfile ) ) {
		return;
	}
	$TextEncoding = 'UTF-8';
	require_once __DIR__ . '/getid3/getid3.php';
	$getID3 = new getID3();
	$getID3->setOption( array( 'encoding' => $TextEncoding ) );
	require_once __DIR__ . '/getid3/write.php';
	$tagwriter                    = new getid3_writetags();
	$tagwriter->filename          = $dirfile;
	$tagwriter->tagformats        = array( 'id3v2.3' );
	$tagwriter->overwrite_tags    = true;
	$tagwriter->tag_encoding      = $TextEncoding;
	$tagwriter->remove_other_tags = true;
	$TagData                      = array(
		'title'   => array( $song ),
		'artist'  => array( $singer ),
		'album'   => array( $song . ' Single' ),
		'year'    => array( date( 'Y' ) ),
		'genre'   => array( 'Music' ),
		'comment' => array( '' ),
	);
	if ( is_file( $axahang ) ) {
		$APICdata = file_get_contents( $axahang );
		if ( $APICdata ) {
			$TagData['attached_picture'] = array(
				0 => array(
					'data'          => $APICdata,
					'picturetypeid' => 2,
					'description'   => 'cover',
					'mime'          => 'image/jpeg',
				),
			);
		}
	}
	$tagwriter->tag_data = $TagData;
	$tagwriter->WriteTags();
}

if ( $src ) {
	smp_host_download( $src, $mysrc );
}
if ( $link320 ) {
	smp_host_download( $link320, $path320 );
	smp_mp3_id_tags( $path320, $artist_en, $song_en, $mysrc );
}
if ( $link128 ) {
	smp_host_download( $link128, $path128 );
	smp_mp3_id_tags( $path128, $artist_en, $song_en, $mysrc );
}
if ( is_file( $mysrc ) ) {
	unlink( $mysrc );
}
http_response_code( 200 );
echo 'OK';
