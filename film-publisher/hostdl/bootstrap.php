<?php
/**
 * Shared helpers for download-host endpoints (NOT loaded by WordPress).
 * Copy this folder to the download server and set config.php.
 */
// Never terminate WordPress if this file is scanned/included from the plugin.
if ( defined( 'ABSPATH' ) ) {
	return;
}

$config_file = __DIR__ . '/config.php';
$config      = is_readable( $config_file ) ? include $config_file : array();
if ( ! is_array( $config ) ) {
	$config = array();
}

$secret = isset( $config['secret'] ) ? (string) $config['secret'] : '';
if ( $secret !== '' ) {
	$provided = '';
	if ( isset( $_SERVER['HTTP_X_FSP_KEY'] ) ) {
		$provided = (string) $_SERVER['HTTP_X_FSP_KEY'];
	} elseif ( isset( $_POST['fsp_key'] ) ) {
		$provided = (string) $_POST['fsp_key'];
	}
	if ( ! hash_equals( $secret, $provided ) ) {
		http_response_code( 403 );
		exit( 'Forbidden' );
	}
}

function smp_host_post( $key ) {
	return isset( $_POST[ $key ] ) ? trim( (string) $_POST[ $key ] ) : '';
}

function smp_host_safe_name( $name ) {
	$name = str_replace( array( '/', '\\', '..', "\0" ), '', $name );
	$name = preg_replace( '/[^\p{L}\p{N}\s\.\-\_\(\)\[\]]+/u', '', $name );
	$name = trim( $name );
	return $name !== '' ? $name : 'file';
}

function smp_host_valid_url( $url ) {
	if ( $url === '' ) {
		return false;
	}
	$parts = parse_url( $url );
	if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	if ( $host === '' || in_array( $host, array( 'localhost', '127.0.0.1', '0.0.0.0', '::1' ), true ) ) {
		return false;
	}
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}
	return true;
}

function smp_host_download( $url, $dest ) {
	if ( ! smp_host_valid_url( $url ) ) {
		return false;
	}
	$fp = fopen( $dest, 'wb' );
	if ( ! $fp ) {
		return false;
	}
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_FILE           => $fp,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 300,
			CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER => true,
		)
	);
	$ok = curl_exec( $ch );
	curl_close( $ch );
	fclose( $fp );
	if ( ! $ok || ! is_file( $dest ) || filesize( $dest ) === 0 ) {
		@unlink( $dest );
		return false;
	}
	return true;
}
