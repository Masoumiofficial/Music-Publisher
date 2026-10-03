<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Post_Handler {

	const ALLOWED_VIDEO = array( 'mp4', 'mkv' );
	const ALLOWED_IMAGE = array( 'jpg', 'jpeg', 'png', 'webp' );

	public function init() {
		add_action( 'admin_post_fsp_submit_movie', array( $this, 'handle_movie' ) );
		add_action( 'admin_post_fsp_submit_series', array( $this, 'handle_series' ) );
		add_action( 'admin_post_fsp_submit_trailer', array( $this, 'handle_trailer' ) );
		add_action( 'admin_post_fsp_submit_leech', array( $this, 'handle_leech' ) );
	}

	private function verify( $action ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'film-publisher' ), 403 );
		}
		check_admin_referer( $action );
	}

	private function redirect( $page, $msg, $err = false ) {
		set_transient(
			'fsp_flash_' . get_current_user_id(),
			array(
				'msg' => $msg,
				'err' => (bool) $err,
			),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	private function cfg( $key, $default = '' ) {
		return FSP_Settings::get( $key, $default );
	}

	private function validate_uploaded_file( $file_key, array $allowed_exts ) {
		if ( empty( $_FILES[ $file_key ]['tmp_name'] ) || $_FILES[ $file_key ]['error'] !== UPLOAD_ERR_OK ) {
			return null;
		}
		$name = sanitize_file_name( wp_unslash( $_FILES[ $file_key ]['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return new WP_Error( 'invalid_ext', __( 'File type is not allowed.', 'film-publisher' ) );
		}
		$filetype = wp_check_filetype( $name );
		if ( empty( $filetype['type'] ) ) {
			return new WP_Error( 'invalid_mime', __( 'File type could not be detected.', 'film-publisher' ) );
		}
		return true;
	}

	private function validate_remote_url( $url ) {
		if ( empty( $url ) ) {
			return true;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'invalid_url', __( 'The URL is not valid.', 'film-publisher' ) );
		}
		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( '' === $host ) {
			return new WP_Error( 'invalid_url', __( 'The URL is not valid.', 'film-publisher' ) );
		}
		$blocked = array( 'localhost', '127.0.0.1', '0.0.0.0', '::1', '169.254.169.254', 'metadata.google.internal' );
		if ( in_array( $host, $blocked, true ) || preg_match( '/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host ) ) {
			return new WP_Error( 'ssrf_blocked', __( 'Internal addresses are not allowed.', 'film-publisher' ) );
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return new WP_Error( 'ssrf_blocked', __( 'Internal IP addresses are not allowed.', 'film-publisher' ) );
			}
		}
		return true;
	}

	private function safe_name( $name ) {
		$name = wp_strip_all_tags( $name );
		$name = str_replace( array( '/', '\\', '..', "\0" ), '', $name );
		$name = trim( $name );
		return $name ? $name : 'untitled';
	}

	private function download_file( $url, $dest ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'Empty URL.', 'film-publisher' ) );
		}
		$valid = $this->validate_remote_url( $url );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 300,
				'stream'    => true,
				'filename'  => $dest,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			@unlink( $dest );
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			@unlink( $dest );
			return new WP_Error( 'http_error', __( 'Download failed.', 'film-publisher' ) );
		}
		return true;
	}

	private function upload_or_download( $file_key, $url_val, $dest, array $allowed_exts ) {
		if ( ! empty( $_FILES[ $file_key ]['tmp_name'] ) ) {
			$valid = $this->validate_uploaded_file( $file_key, $allowed_exts );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			if ( ! move_uploaded_file( $_FILES[ $file_key ]['tmp_name'], $dest ) ) {
				return new WP_Error( 'move_failed', __( 'Could not move uploaded file.', 'film-publisher' ) );
			}
			return true;
		}
		if ( ! empty( $url_val ) ) {
			return $this->download_file( $url_val, $dest );
		}
		return null;
	}

	private function attach_image_to_post( $filepath, $post_id ) {
		if ( ! file_exists( $filepath ) ) {
			return;
		}
		$filetype   = wp_check_filetype( basename( $filepath ), null );
		$upload_dir = wp_upload_dir();
		$attachment = array(
			'guid'           => trailingslashit( $upload_dir['url'] ) . basename( $filepath ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filepath ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $filepath, $post_id );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $filepath ) );
		set_post_thumbnail( $post_id, $attach_id );
	}

	private function send_to_dlhost( $endpoint_key, $data ) {
		$url = $this->cfg( $endpoint_key );
		if ( empty( $url ) ) {
			return new WP_Error( 'no_endpoint', __( 'Download host URL is not set.', 'film-publisher' ) );
		}
		$valid = $this->validate_remote_url( $url );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$secret  = $this->cfg( 'dlhost_secret' );
		$headers = array();
		if ( $secret ) {
			$headers['X-FSP-Key'] = $secret;
			$data['fsp_key']      = $secret;
		}
		$response = wp_remote_post(
			$url,
			array(
				'body'      => $data,
				'timeout'   => 300,
				'sslverify' => true,
				'headers'   => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_error', __( 'Download host returned an error.', 'film-publisher' ) );
		}
		return true;
	}

	private function post_status_and_msg( $is_draft ) {
		if ( 'draft' === $is_draft || ! current_user_can( 'publish_posts' ) ) {
			return array( 'draft', __( 'Saved as draft.', 'film-publisher' ) );
		}
		return array( 'publish', __( 'Published.', 'film-publisher' ) );
	}

	private function get_category_ids( $key, $default_csv ) {
		$raw = $this->cfg( $key, $default_csv );
		$ids = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
		return ! empty( $ids ) ? $ids : array_map( 'intval', explode( ',', $default_csv ) );
	}

	private function ensure_upload_dir( $year, $months ) {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . $year . '/' . $months;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	private function uploads_path( $year, $months, $filename ) {
		return trailingslashit( $this->ensure_upload_dir( $year, $months ) ) . $filename;
	}

	private function check_duplicate_title( $title ) {
		global $wpdb;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_title FROM {$wpdb->posts} WHERE post_title = %s AND post_status NOT IN ('trash','auto-draft')",
				$title
			)
		);
		return ! empty( trim( (string) $existing ) );
	}

	private function insert_common_post( $title, $content, $slug, $status, $cats ) {
		return wp_insert_post(
			array(
				'post_author'   => get_current_user_id(),
				'post_status'   => $status,
				'post_type'     => 'post',
				'post_title'    => $title,
				'post_category' => $cats,
				'post_name'     => sanitize_title( $slug ),
				'post_content'  => $content,
			),
			true
		);
	}

	private function store_film_meta( $post_id, $data ) {
		$map = array(
			'title_fa'  => 'film_title_fa',
			'title_en'  => 'film_title_en',
			'year'      => 'film_year',
			'duration'  => 'film_duration',
			'imdb'      => 'film_imdb',
			'country'   => 'film_country',
			'director'  => 'film_director',
			'actors'    => 'film_actors',
			'summary'   => 'film_summary',
			'lang_type' => 'film_lang_type',
			'kind'      => 'film_kind',
		);
		foreach ( $map as $src => $meta ) {
			if ( isset( $data[ $src ] ) ) {
				update_post_meta( $post_id, $meta, $data[ $src ] );
			}
		}
		$site = $this->cfg( 'namesite' );
		update_post_meta( $post_id, '_yoast_wpseo_title', wp_strip_all_tags( $data['title_fa'] . ' - ' . $site ) );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', wp_strip_all_tags( $data['summary'] ) );
	}

	private function collect_meta_from_post() {
		return array(
			'title_fa'  => sanitize_text_field( wp_unslash( $_POST['title_fa'] ?? '' ) ),
			'title_en'  => $this->safe_name( sanitize_text_field( wp_unslash( $_POST['title_en'] ?? '' ) ) ),
			'year'      => absint( $_POST['year'] ?? 0 ),
			'duration'  => absint( $_POST['duration'] ?? 0 ),
			'imdb'      => sanitize_text_field( wp_unslash( $_POST['imdb'] ?? '' ) ),
			'country'   => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
			'director'  => sanitize_text_field( wp_unslash( $_POST['director'] ?? '' ) ),
			'actors'    => sanitize_text_field( wp_unslash( $_POST['actors'] ?? '' ) ),
			'summary'   => sanitize_textarea_field( wp_unslash( $_POST['summary'] ?? '' ) ),
			'lang_type' => sanitize_key( $_POST['lang_type'] ?? 'dubbed' ),
			'poster'    => esc_url_raw( $_POST['poster'] ?? '' ),
			'is_draft'  => sanitize_text_field( $_POST['is_draft'] ?? 'publish' ),
		);
	}

	private function assert_urls( array $urls, $page ) {
		foreach ( $urls as $u ) {
			$v = $this->validate_remote_url( $u );
			if ( is_wp_error( $v ) ) {
				$this->redirect( $page, $v->get_error_message(), true );
			}
		}
	}

	public function handle_movie() {
		$this->verify( 'fsp_submit_movie' );
		$m = $this->collect_meta_from_post();
		$m['kind'] = 'movie';
		$links     = array(
			'4k'   => esc_url_raw( $_POST['link_4k'] ?? '' ),
			'1080' => esc_url_raw( $_POST['link_1080'] ?? '' ),
			'720'  => esc_url_raw( $_POST['link_720'] ?? '' ),
			'480'  => esc_url_raw( $_POST['link_480'] ?? '' ),
			'sub'  => esc_url_raw( $_POST['link_sub'] ?? '' ),
		);
		if ( '' === $m['title_fa'] || '' === $m['title_en'] ) {
			$this->redirect( 'fsp-dashboard', __( 'Required fields are missing.', 'film-publisher' ), true );
		}
		$this->assert_urls( array_merge( array( $m['poster'] ), $links ), 'fsp-dashboard' );
		$title = sprintf(
			/* translators: 1: title, 2: year */
			__( 'Download movie %1$s (%2$s)', 'film-publisher' ),
			$m['title_fa'],
			$m['year'] ? $m['year'] : ''
		);
		if ( $this->check_duplicate_title( $title ) ) {
			$this->redirect( 'fsp-dashboard', __( 'A post with this title already exists.', 'film-publisher' ), true );
		}
		$content = '<p>' . esc_html( $m['summary'] ) . '</p><!--more-->';
		list( $status, $msg ) = $this->post_status_and_msg( $m['is_draft'] );
		$post_id              = $this->insert_common_post( $title, $content, $m['title_en'], $status, $this->get_category_ids( 'cat_movie', '10' ) );
		if ( is_wp_error( $post_id ) ) {
			$this->redirect( 'fsp-dashboard', $post_id->get_error_message(), true );
		}
		$this->store_film_meta( $post_id, $m );
		$host = untrailingslashit( $this->cfg( 'urldlhost' ) );
		$base = $host . '/movie/' . rawurlencode( $m['title_en'] );
		foreach ( $links as $q => $url ) {
			if ( $url ) {
				update_post_meta( $post_id, 'film_link_' . $q, $base . '.' . $q . ( 'sub' === $q ? '.srt' : '.mp4' ) );
			}
		}
		$year = date( 'Y' );
		$mo   = date( 'm' );
		$path = $this->uploads_path( $year, $mo, $m['title_en'] . '.jpg' );
		$r    = $this->upload_or_download( 'fileposter', $m['poster'], $path, self::ALLOWED_IMAGE );
		if ( true === $r ) {
			$this->attach_image_to_post( $path, $post_id );
		}
		$send = $this->send_to_dlhost(
			'urlfunction_movie',
			array_merge(
				array(
					'title_en' => $m['title_en'],
					'poster'   => $m['poster'],
				),
				$links
			)
		);
		$warn = array();
		if ( is_wp_error( $r ) ) {
			$warn[] = $r->get_error_message();
		}
		if ( is_wp_error( $send ) ) {
			$warn[] = $send->get_error_message();
		}
		$this->redirect( 'fsp-dashboard', $warn ? $msg . ' ' . implode( ' | ', $warn ) : $msg, ! empty( $warn ) );
	}

	public function handle_series() {
		$this->verify( 'fsp_submit_series' );
		$m           = $this->collect_meta_from_post();
		$m['kind']   = 'series';
		$m['season'] = max( 1, absint( $_POST['season'] ?? 1 ) );
		if ( '' === $m['title_fa'] || '' === $m['title_en'] ) {
			$this->redirect( 'fsp-series', __( 'Required fields are missing.', 'film-publisher' ), true );
		}
		$this->assert_urls( array( $m['poster'] ), 'fsp-series' );
		$title = sprintf(
			/* translators: 1: series title, 2: season */
			__( 'Download series %1$s season %2$d', 'film-publisher' ),
			$m['title_fa'],
			$m['season']
		);
		if ( $this->check_duplicate_title( $title ) ) {
			$this->redirect( 'fsp-series', __( 'A post with this title already exists.', 'film-publisher' ), true );
		}
		$content = '<p>' . esc_html( $m['summary'] ) . '</p><!--more-->';
		list( $status, $msg ) = $this->post_status_and_msg( $m['is_draft'] );
		$post_id              = $this->insert_common_post( $title, $content, $m['title_en'] . '-s' . $m['season'], $status, $this->get_category_ids( 'cat_series', '11' ) );
		if ( is_wp_error( $post_id ) ) {
			$this->redirect( 'fsp-series', $post_id->get_error_message(), true );
		}
		$this->store_film_meta( $post_id, $m );
		update_post_meta( $post_id, 'film_season', $m['season'] );
		$host    = untrailingslashit( $this->cfg( 'urldlhost' ) );
		$eps     = array();
		$payload = array(
			'title_en' => $m['title_en'],
			'season'   => $m['season'],
			'poster'   => $m['poster'],
		);
		for ( $i = 1; $i <= 20; $i++ ) {
			$et = sanitize_text_field( wp_unslash( $_POST[ 'ep_title_' . $i ] ?? '' ) );
			$a  = esc_url_raw( $_POST[ 'ep_1080_' . $i ] ?? '' );
			$b  = esc_url_raw( $_POST[ 'ep_720_' . $i ] ?? '' );
			$this->assert_urls( array( $a, $b ), 'fsp-series' );
			if ( '' === $et && '' === $a && '' === $b ) {
				continue;
			}
			$eps[] = array(
				'n'        => $i,
				'title'    => $et ? $et : sprintf( __( 'Episode %d', 'film-publisher' ), $i ),
				'url_1080' => $host . '/series/' . rawurlencode( $m['title_en'] ) . '/S' . $m['season'] . 'E' . $i . '.1080.mp4',
				'url_720'  => $host . '/series/' . rawurlencode( $m['title_en'] ) . '/S' . $m['season'] . 'E' . $i . '.720.mp4',
			);
			$payload[ 'ep_title_' . $i ] = $et;
			$payload[ 'ep_1080_' . $i ]  = $a;
			$payload[ 'ep_720_' . $i ]   = $b;
		}
		update_post_meta( $post_id, 'film_episodes', $eps );
		$year = date( 'Y' );
		$mo   = date( 'm' );
		$path = $this->uploads_path( $year, $mo, $m['title_en'] . '-s' . $m['season'] . '.jpg' );
		$r    = $this->upload_or_download( 'fileposter', $m['poster'], $path, self::ALLOWED_IMAGE );
		if ( true === $r ) {
			$this->attach_image_to_post( $path, $post_id );
		}
		$send = $this->send_to_dlhost( 'urlfunction_series', $payload );
		$warn = array();
		if ( is_wp_error( $r ) ) {
			$warn[] = $r->get_error_message();
		}
		if ( is_wp_error( $send ) ) {
			$warn[] = $send->get_error_message();
		}
		$this->redirect( 'fsp-series', $warn ? $msg . ' ' . implode( ' | ', $warn ) : $msg, ! empty( $warn ) );
	}

	public function handle_trailer() {
		$this->verify( 'fsp_submit_trailer' );
		$title_fa = sanitize_text_field( wp_unslash( $_POST['title_fa'] ?? '' ) );
		$title_en = $this->safe_name( sanitize_text_field( wp_unslash( $_POST['title_en'] ?? '' ) ) );
		$summary  = sanitize_textarea_field( wp_unslash( $_POST['summary'] ?? '' ) );
		$poster   = esc_url_raw( $_POST['poster'] ?? '' );
		$link     = esc_url_raw( $_POST['link_trailer'] ?? '' );
		$draft    = sanitize_text_field( $_POST['is_draft'] ?? 'publish' );
		if ( '' === $title_fa || '' === $title_en ) {
			$this->redirect( 'fsp-trailer', __( 'Required fields are missing.', 'film-publisher' ), true );
		}
		$this->assert_urls( array( $poster, $link ), 'fsp-trailer' );
		$title = sprintf( __( 'Trailer: %s', 'film-publisher' ), $title_fa );
		if ( $this->check_duplicate_title( $title ) ) {
			$this->redirect( 'fsp-trailer', __( 'A post with this title already exists.', 'film-publisher' ), true );
		}
		list( $status, $msg ) = $this->post_status_and_msg( $draft );
		$post_id              = $this->insert_common_post( $title, '<p>' . esc_html( $summary ) . '</p><!--more-->', $title_en . '-trailer', $status, $this->get_category_ids( 'cat_trailer', '12' ) );
		if ( is_wp_error( $post_id ) ) {
			$this->redirect( 'fsp-trailer', $post_id->get_error_message(), true );
		}
		update_post_meta( $post_id, 'film_kind', 'trailer' );
		update_post_meta( $post_id, 'film_title_fa', $title_fa );
		update_post_meta( $post_id, 'film_title_en', $title_en );
		update_post_meta( $post_id, 'film_trailer', $link );
		$year = date( 'Y' );
		$mo   = date( 'm' );
		$path = $this->uploads_path( $year, $mo, $title_en . '-trailer.jpg' );
		$r    = $this->upload_or_download( 'fileposter', $poster, $path, self::ALLOWED_IMAGE );
		if ( true === $r ) {
			$this->attach_image_to_post( $path, $post_id );
		}
		$send = $this->send_to_dlhost(
			'urlfunction_trailer',
			array(
				'title_en' => $title_en,
				'link'     => $link,
				'poster'   => $poster,
			)
		);
		$warn = array();
		if ( is_wp_error( $r ) ) {
			$warn[] = $r->get_error_message();
		}
		if ( is_wp_error( $send ) ) {
			$warn[] = $send->get_error_message();
		}
		$this->redirect( 'fsp-trailer', $warn ? $msg . ' ' . implode( ' | ', $warn ) : $msg, ! empty( $warn ) );
	}

	public function handle_leech() {
		$this->verify( 'fsp_submit_leech' );
		$filename = $this->safe_name( sanitize_text_field( wp_unslash( $_POST['filename'] ?? '' ) ) );
		$link     = esc_url_raw( $_POST['linkfile'] ?? '' );
		if ( '' === $filename ) {
			$this->redirect( 'fsp-leech', __( 'Required fields are missing.', 'film-publisher' ), true );
		}
		$this->assert_urls( array( $link ), 'fsp-leech' );
		$year = date( 'Y' );
		$mo   = date( 'm' );
		$uid  = uniqid( '', true );
		$tmp  = $this->uploads_path( $year, $mo, 'leech-' . $uid . '.mp4' );
		$r    = $this->upload_or_download( 'filemp4', $link, $tmp, self::ALLOWED_VIDEO );
		$uploads = wp_upload_dir();
		$public  = ( true === $r ) ? trailingslashit( $uploads['baseurl'] ) . $year . '/' . $mo . '/' . rawurlencode( basename( $tmp ) ) : '';
		$send    = $this->send_to_dlhost(
			'urlfunction_leech',
			array(
				'filename' => $filename,
				'link'     => $public,
			)
		);
		if ( is_file( $tmp ) ) {
			@unlink( $tmp );
		}
		$warn = array();
		if ( is_wp_error( $r ) ) {
			$warn[] = $r->get_error_message();
		}
		if ( is_wp_error( $send ) ) {
			$warn[] = $send->get_error_message();
		}
		$this->redirect( 'fsp-leech', $warn ? implode( ' | ', $warn ) : __( 'Leech completed.', 'film-publisher' ), ! empty( $warn ) );
	}
}
