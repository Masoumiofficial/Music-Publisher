<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMP_Post_Handler {

    /** پسوندهای مجاز برای هر نوع فایل */
    const ALLOWED_AUDIO = array( 'mp3' );
    const ALLOWED_VIDEO = array( 'mp4' );
    const ALLOWED_IMAGE = array( 'jpg', 'jpeg', 'png', 'webp' );

    public function init() {
        add_action( 'admin_post_smp_submit_music', array( $this, 'handle_music' ) );
        add_action( 'admin_post_smp_submit_remix', array( $this, 'handle_remix' ) );
        add_action( 'admin_post_smp_submit_nohe',  array( $this, 'handle_nohe' ) );
        add_action( 'admin_post_smp_submit_video', array( $this, 'handle_video' ) );
        add_action( 'admin_post_smp_submit_album', array( $this, 'handle_album' ) );
        add_action( 'admin_post_smp_submit_leech', array( $this, 'handle_leech' ) );
    }

    /* ═══════════════════════════════════════════════
       HELPERS — امنیت و اعتبارسنجی
    ═══════════════════════════════════════════════ */

    private function verify( $action ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'sajad-music-publisher' ), 403 );
        }
        check_admin_referer( $action );
    }

    private function redirect( $page, $msg, $err = false ) {
        set_transient(
            'smp_flash_' . get_current_user_id(),
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
        return SMP_Settings::get( $key, $default );
    }

    /**
     * بررسی پسوند فایل آپلودی نسبت به لیست مجاز.
     * این تابع جلوی آپلود فایل‌های اجرایی (php, exe, ...) رو می‌گیره.
     */
    private function validate_uploaded_file( $file_key, array $allowed_exts ) {
        if ( empty( $_FILES[ $file_key ]['tmp_name'] ) || $_FILES[ $file_key ]['error'] !== UPLOAD_ERR_OK ) {
            return null; // فایلی آپلود نشده، مشکلی نیست
        }
        $name = $_FILES[ $file_key ]['name'];
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, $allowed_exts, true ) ) {
            return new WP_Error( 'invalid_ext', "فرمت فایل آپلودی ({$ext}) مجاز نیست. فرمت‌های مجاز: " . implode( ', ', $allowed_exts ) );
        }

        $filetype = wp_check_filetype( $name );
        if ( empty( $filetype['type'] ) ) {
            return new WP_Error( 'invalid_mime', 'نوع فایل آپلودی قابل شناسایی نیست.' );
        }

        return true;
    }

    /**
     * بررسی اینکه آدرس وارد شده یک URL واقعی با http/https هست
     * و به آدرس داخلی شبکه (SSRF) اشاره نمی‌کنه.
     */
    private function validate_remote_url( $url ) {
        if ( empty( $url ) ) return true;
        $parts = wp_parse_url( $url );
        if ( empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'invalid_url', 'آدرس وارد شده معتبر نیست.' );
        }
        $host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        if ( '' === $host ) {
            return new WP_Error( 'invalid_url', __( 'The URL is not valid.', 'sajad-music-publisher' ) );
        }
        $blocked = array( 'localhost', '127.0.0.1', '0.0.0.0', '::1', '169.254.169.254', 'metadata.google.internal' );
        if ( in_array( $host, $blocked, true ) || preg_match( '/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host ) ) {
            return new WP_Error( 'ssrf_blocked', __( 'Internal addresses are not allowed.', 'sajad-music-publisher' ) );
        }
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return new WP_Error( 'ssrf_blocked', __( 'Internal IP addresses are not allowed.', 'sajad-music-publisher' ) );
            }
        }
        return true;
    }

    /**
     * Sanitize a user-supplied name for use in file paths (no traversal).
     */
    private function safe_name( $name ) {
        $name = wp_strip_all_tags( $name );
        $name = str_replace( array( '/', '\\', '..', "\0" ), '', $name );
        $name = trim( $name );
        return $name ? $name : 'untitled';
    }

    /**
     * دانلود فایل به‌صورت stream (بدون لود کامل در حافظه).
     * بازگشت: true در موفقیت، WP_Error در خطا.
     */
    private function download_file( $url, $dest ) {
        if ( empty( $url ) ) return new WP_Error( 'empty_url', 'آدرس خالی است.' );

        $valid = $this->validate_remote_url( $url );
        if ( is_wp_error( $valid ) ) return $valid;

        $args = array(
            'timeout'   => 300,
            'stream'    => true,
            'filename'  => $dest,
            'sslverify' => true,
        );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            @unlink( $dest );
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            @unlink( $dest );
            return new WP_Error( 'http_error', "دانلود فایل با خطا مواجه شد (HTTP {$code}): {$url}" );
        }
        if ( ! file_exists( $dest ) || filesize( $dest ) === 0 ) {
            return new WP_Error( 'empty_file', "فایل دانلودشده خالی است: {$url}" );
        }
        return true;
    }

    /**
     * آپلود مستقیم یا دانلود از لینک، با اعتبارسنجی پسوند فایل.
     * بازگشت: true در موفقیت، WP_Error در خطا، null اگر چیزی ارسال نشده.
     */
    private function upload_or_download( $file_key, $url_val, $dest, array $allowed_exts ) {
        if ( ! empty( $_FILES[ $file_key ]['tmp_name'] ) ) {
            $valid = $this->validate_uploaded_file( $file_key, $allowed_exts );
            if ( is_wp_error( $valid ) ) return $valid;
            if ( ! move_uploaded_file( $_FILES[ $file_key ]['tmp_name'], $dest ) ) {
                return new WP_Error( 'move_failed', 'انتقال فایل آپلودی ناموفق بود.' );
            }
            return true;
        } elseif ( ! empty( $url_val ) ) {
            $ext = strtolower( pathinfo( wp_parse_url( $url_val, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
            if ( $ext && ! in_array( $ext, $allowed_exts, true ) ) {
                return new WP_Error( 'invalid_ext', "پسوند لینک ({$ext}) مجاز نیست." );
            }
            return $this->download_file( $url_val, $dest );
        }
        return null;
    }

    private function attach_image_to_post( $filepath, $post_id ) {
        if ( ! file_exists( $filepath ) ) return;
        $filetype   = wp_check_filetype( basename( $filepath ), null );
        $upload_dir = wp_upload_dir();
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename( $filepath ),
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

    private function resize_image( $filepath, $w, $h ) {
        if ( ! file_exists( $filepath ) ) return;
        $editor = wp_get_image_editor( $filepath );
        if ( is_wp_error( $editor ) ) return;
        $editor->resize( $w, $h, true );
        $editor->save( $filepath );
    }

    /**
     * ارسال داده به هاست دانلود، فقط به دامنه‌ای که در تنظیمات وارد شده (ضد SSRF).
     * بازگشت: true در موفقیت، WP_Error در خطا.
     */
    private function send_to_dlhost( $endpoint_key, $data ) {
        $url = $this->cfg( $endpoint_key );
        if ( empty( $url ) ) {
            return new WP_Error( 'no_endpoint', 'آدرس هاست دانلود در تنظیمات وارد نشده است.' );
        }
        $valid = $this->validate_remote_url( $url );
        if ( is_wp_error( $valid ) ) return $valid;

        $secret = $this->cfg( 'dlhost_secret' );
        $headers = array();
        if ( $secret ) {
            $headers['X-SMP-Key'] = $secret;
            $data['smp_key']      = $secret;
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

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'http_error', "هاست دانلود خطا برگرداند (HTTP {$code})." );
        }
        return true;
    }

    private function jdate_dir() {
        require_once SMP_PLUGIN_DIR . 'includes/jdf.php';
        return jdate_win( 'Y/m/d', time() );
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

    private function post_status_and_msg( $is_draft ) {
        if ( 'draft' === $is_draft || ! current_user_can( 'publish_posts' ) ) {
            return array( 'draft', __( 'The post was saved as a draft.', 'sajad-music-publisher' ) );
        }
        return array( 'publish', __( 'The post was published.', 'sajad-music-publisher' ) );
    }

    /** خواندن دسته‌بندی‌های پیکربندی‌شده از تنظیمات (به‌جای هاردکد) */
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

    private function uploads_url( $year, $months, $filename ) {
        $uploads = wp_upload_dir();
        return trailingslashit( $uploads['baseurl'] ) . $year . '/' . $months . '/' . rawurlencode( $filename );
    }

    private function maybe_set_terms( $post_id, $term, $taxonomy ) {
        if ( $term && taxonomy_exists( $taxonomy ) ) {
            wp_set_object_terms( $post_id, $term, $taxonomy );
        }
    }

    private function collect_warning( array &$warnings, $result, $label ) {
        if ( is_wp_error( $result ) ) {
            $warnings[] = $label . ': ' . $result->get_error_message();
        }
    }

    /* ═══════════════════════════════════════════════
       تک آهنگ / ریمیکس / نوحه  (منطق مشترک)
    ═══════════════════════════════════════════════ */

    private function handle_music_type( $nonce_action, $redirect_page, $curl_key, $cat_key, $cat_default, $musics_type ) {
        $this->verify( $nonce_action );
        set_time_limit( 600 );

        $name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $track      = sanitize_text_field( wp_unslash( $_POST['track'] ?? '' ) );
        $enname     = sanitize_text_field( wp_unslash( $_POST['enname'] ?? '' ) );
        $entrack    = sanitize_text_field( wp_unslash( $_POST['entrack'] ?? '' ) );
        $cover      = esc_url_raw( $_POST['cover'] ?? '' );
        $cover_mp3  = esc_url_raw( $_POST['cover_mp3'] ?? '' );
        $info       = sanitize_text_field( wp_unslash( $_POST['info'] ?? '' ) );
        $lyric      = sanitize_textarea_field( wp_unslash( $_POST['lyric'] ?? '' ) );
        $link320    = esc_url_raw( $_POST['link320'] ?? '' );
        $link128    = esc_url_raw( $_POST['link128'] ?? '' );
        $songwriter = sanitize_text_field( wp_unslash( $_POST['lyrc'] ?? '' ) );
        $composer   = sanitize_text_field( wp_unslash( $_POST['melody'] ?? '' ) );
        $regulator  = sanitize_text_field( wp_unslash( $_POST['arrng'] ?? '' ) );
        $mixmaster  = sanitize_text_field( wp_unslash( $_POST['mix'] ?? '' ) );
        $is_draft   = sanitize_text_field( $_POST['is_draft'] ?? 'publish' );

        if ( empty( $name ) || empty( $track ) || empty( $enname ) || empty( $entrack ) ) {
            $this->redirect( $redirect_page, 'خطا: فیلدهای ستاره‌دار (نام خواننده، نام اثر) اجباری هستند.', true );
        }

        foreach ( array( $cover, $cover_mp3, $link320, $link128 ) as $u ) {
            $v = $this->validate_remote_url( $u );
            if ( is_wp_error( $v ) ) {
                $this->redirect( $redirect_page, 'خطا: ' . $v->get_error_message(), true );
            }
        }

        $urlsite   = $this->cfg( 'urlsite' );
        $urldlhost = $this->cfg( 'urldlhost' );

        $title = "دانلود آهنگ {$name} به نام {$track}";
        if ( $this->check_duplicate_title( $title ) ) {
            $this->redirect( $redirect_page, 'خطا: پستی با این عنوان قبلاً وجود دارد!', true );
        }

        $year   = date( 'Y' ); $months = date( 'm' );
        $this->ensure_upload_dir( $year, $months );

        $enname      = $this->safe_name( $enname );
        $entrack     = $this->safe_name( $entrack );
        $trackname   = "{$enname} - {$entrack}";
        $dir_file    = 'music/' . $this->jdate_dir();
        $filename320 = "{$urldlhost}/{$dir_file}/{$trackname}.mp3";
        $filename128 = "{$urldlhost}/{$dir_file}/{$trackname} [128].mp3";
        $img_url     = $this->uploads_url( $year, $months, $trackname . '.jpg' );
        $img_path    = $this->uploads_path( $year, $months, $trackname . '.jpg' );

        $singer_tag    = sanitize_title_with_dashes( $name );
        $singer_en     = sanitize_title_with_dashes( $enname );
        $singer_fa_url = "https://{$urlsite}/tag/{$singer_tag}/";
        $singer_en_url = "https://{$urlsite}/tag/{$singer_en}/";

        $seo_title = "دانلود آهنگ {$name} به نام {$track} - " . $this->cfg( 'namesite' );
        $metadesc  = "دانلود آهنگ جدید {$name} به نام {$track} با دو کیفیت 128 و 320 - Download New Music {$enname} - {$entrack} - متن ترانه {$name} {$track}.";

        $content = sprintf(
            '
<p style="text-align: center;">هم اکنون شما می توانید <strong>%1$s</strong> از <strong>%2$s</strong> را بصورت آنلاین گوش کرده و با بالاترین کیفیت دانلود نمایید.</p>
<p style="text-align: center;"><a href="https://%3$s/tag/%4$s">دانلود آهنگ جدید</a> <a href="%5$s">%2$s</a> به نام %1$s</p>
<p style="text-align: center;">%6$s</p>
<div style="text-align: center;"><a href="https://%3$s/tag/download-new-music">Download New Music</a> <a href="%7$s">%8$s</a> – %9$s</div>
<!--more-->
',
            esc_html( $track ),
            esc_html( $name ),
            esc_html( $urlsite ),
            'دانلود-آهنگ-جدید',
            esc_url( $singer_fa_url ),
            esc_html( $info ),
            esc_url( $singer_en_url ),
            esc_html( $enname ),
            esc_html( $entrack )
        );

        list( $post_status, $req_msg ) = $this->post_status_and_msg( $is_draft );
        $cat_ids = $this->get_category_ids( $cat_key, $cat_default );

        $post_id = wp_insert_post( array(
            'post_author'   => get_current_user_id(),
            'post_status'   => $post_status,
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( $title ),
            'post_category' => $cat_ids,
            'post_name'     => sanitize_title_with_dashes( $trackname ),
            'post_content'  => $content,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            $this->redirect( $redirect_page, 'خطا در ایجاد پست: ' . $post_id->get_error_message(), true );
        }

        $tags = array( 'download new music', 'دانلود آهنگ جدید', 'دانلود آهنگ ایرانی', $name, $enname );
        wp_set_post_tags( $post_id, $tags );
        $this->maybe_set_terms( $post_id, $name, 'singer' );
        $this->maybe_set_terms( $post_id, $songwriter, 'songwriter' );
        $this->maybe_set_terms( $post_id, $composer, 'composer' );
        $this->maybe_set_terms( $post_id, $regulator, 'regulator' );
        $this->maybe_set_terms( $post_id, $mixmaster, 'mixmaster' );

        update_post_meta( $post_id, '_yoast_wpseo_title',         strip_tags( $seo_title ) );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc',      strip_tags( $metadesc ) );
        update_post_meta( $post_id, '_yoast_wpseo_focuskw',       strip_tags( "آهنگ {$name} {$track}" ) );
        update_post_meta( $post_id, '_yoast_wpseo_linkdex',       '99' );
        update_post_meta( $post_id, '_yoast_wpseo_content_score', '99' );
        update_post_meta( $post_id, 'art_name',       $name );
        update_post_meta( $post_id, 'track_name',     $track );
        update_post_meta( $post_id, 'artist_en',      $enname );
        update_post_meta( $post_id, 'song_en',        $entrack );
        update_post_meta( $post_id, 'music_txt',      $lyric );
        update_post_meta( $post_id, 'fifu_image_alt', $title );
        update_post_meta( $post_id, 'fifu_image_url', $img_url );
        update_post_meta( $post_id, 'musics_type',    $musics_type );
        update_post_meta( $post_id, 'online_ply',     'off' );
        update_post_meta( $post_id, 'pplayer_in',     'off' );
        update_post_meta( $post_id, 'select_effect',  'imghvr-shutter-in-out-diag-1' );

        $warnings = array();

        $temp320 = $this->uploads_path( $year, $months, 'temp-' . absint( $post_id ) . '-320.mp3' );
        $temp128 = $this->uploads_path( $year, $months, 'temp-' . absint( $post_id ) . '-128.mp3' );

        $r = $this->upload_or_download( 'filelink320', $link320, $temp320, self::ALLOWED_AUDIO );
        $this->collect_warning( $warnings, $r, 'فایل ۳۲۰' );
        if ( $r === true ) update_post_meta( $post_id, 'music320', $filename320 );

        $r = $this->upload_or_download( 'filelink128', $link128, $temp128, self::ALLOWED_AUDIO );
        $this->collect_warning( $warnings, $r, 'فایل ۱۲۸' );
        if ( $r === true ) update_post_meta( $post_id, 'music128', $filename128 );

        $r = $this->upload_or_download( 'filecover', $cover, $img_path, self::ALLOWED_IMAGE );
        $this->collect_warning( $warnings, $r, 'کاور' );
        if ( $r === true ) {
            $this->resize_image( $img_path, 470, 470 );
            $this->attach_image_to_post( $img_path, $post_id );
        }

        $r = $this->send_to_dlhost( $curl_key, array(
            'link128'    => $link128,
            'link320'    => $link320,
            'enname'     => $enname,
            'entrack'    => $entrack,
            'artist_en'  => $enname,
            'song_en'    => $entrack,
            'cover'      => $cover,
            'cover_mp3'  => $cover_mp3,
        ) );
        $this->collect_warning( $warnings, $r, 'ارسال به هاست دانلود' );

        @unlink( $temp320 );
        @unlink( $temp128 );

        $final_msg = $req_msg;
        $has_err   = false;
        if ( ! empty( $warnings ) ) {
            $final_msg .= ' ⚠ هشدار: ' . implode( ' | ', $warnings );
            $has_err = true;
        }

        $this->redirect( $redirect_page, $final_msg, $has_err );
    }

    public function handle_music() {
        $this->handle_music_type( 'smp_submit_music', 'smp-dashboard', 'urlfunction_music', 'cat_music', '10,12', 'musicss' );
    }

    public function handle_remix() {
        $this->handle_music_type( 'smp_submit_remix', 'smp-remix', 'urlfunction_remix', 'cat_remix', '10,13', 'musicss_remix' );
    }

    public function handle_nohe() {
        $this->handle_music_type( 'smp_submit_nohe', 'smp-nohe', 'urlfunction_nohe', 'cat_nohe', '10,14', 'musicss_nohe' );
    }

    /* ═══════════════════════════════════════════════
       موزیک ویدیو
    ═══════════════════════════════════════════════ */
    public function handle_video() {
        $this->verify( 'smp_submit_video' );
        set_time_limit( 600 );

        $name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $track    = sanitize_text_field( wp_unslash( $_POST['track'] ?? '' ) );
        $enname   = sanitize_text_field( wp_unslash( $_POST['enname'] ?? '' ) );
        $entrack  = sanitize_text_field( wp_unslash( $_POST['entrack'] ?? '' ) );
        $cover    = esc_url_raw( $_POST['cover'] ?? '' );
        $info     = sanitize_text_field( wp_unslash( $_POST['info'] ?? '' ) );
        $link1080 = esc_url_raw( $_POST['link1080'] ?? '' );
        $link720  = esc_url_raw( $_POST['link720'] ?? '' );
        $link480  = esc_url_raw( $_POST['link480'] ?? '' );
        $link320  = esc_url_raw( $_POST['link320'] ?? '' );
        $is_draft = sanitize_text_field( $_POST['is_draft'] ?? 'publish' );

        if ( empty( $name ) || empty( $track ) || empty( $enname ) || empty( $entrack ) ) {
            $this->redirect( 'smp-video', 'خطا: فیلدهای اجباری را پر کنید.', true );
        }

        foreach ( array( $cover, $link1080, $link720, $link480, $link320 ) as $u ) {
            $v = $this->validate_remote_url( $u );
            if ( is_wp_error( $v ) ) {
                $this->redirect( 'smp-video', 'خطا: ' . $v->get_error_message(), true );
            }
        }

        $urlsite   = $this->cfg( 'urlsite' );
        $urldlhost = $this->cfg( 'urldlhost' );

        $title = "دانلود موزیک ویدیو {$name} به نام {$track}";
        if ( $this->check_duplicate_title( $title ) ) {
            $this->redirect( 'smp-video', 'خطا: پستی با این عنوان قبلاً وجود دارد!', true );
        }

        $year   = date( 'Y' ); $months = date( 'm' );
        $this->ensure_upload_dir( $year, $months );

        $enname    = $this->safe_name( $enname );
        $entrack   = $this->safe_name( $entrack );
        $trackname = "{$enname} - {$entrack}";
        $dir_file  = 'video/' . $this->jdate_dir();
        $img_url   = $this->uploads_url( $year, $months, $trackname . '.jpg' );
        $img_path  = $this->uploads_path( $year, $months, $trackname . '.jpg' );

        $seo_title = "دانلود موزیک ویدیو {$name} به نام {$track} - " . $this->cfg( 'namesite' );
        $metadesc  = "دانلود موزیک ویدیو جدید {$name} به نام {$track} - Download New Music Video {$enname} - {$entrack}";

        $content = sprintf(
            '
<p style="text-align: center;">موزیک ویدیو <strong>%1$s</strong> از <strong>%2$s</strong></p>
<p style="text-align: center;">%3$s</p>
<!--more-->
',
            esc_html( $track ), esc_html( $name ), esc_html( $info )
        );

        list( $post_status, $req_msg ) = $this->post_status_and_msg( $is_draft );
        $cat_ids = $this->get_category_ids( 'cat_video', '10,15' );

        $post_id = wp_insert_post( array(
            'post_author'   => get_current_user_id(),
            'post_status'   => $post_status,
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( $title ),
            'post_category' => $cat_ids,
            'post_name'     => sanitize_title_with_dashes( $trackname ),
            'post_content'  => $content,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            $this->redirect( 'smp-video', 'خطا در ایجاد پست: ' . $post_id->get_error_message(), true );
        }

        update_post_meta( $post_id, '_yoast_wpseo_title',    strip_tags( $seo_title ) );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', strip_tags( $metadesc ) );
        update_post_meta( $post_id, 'art_name',       $name );
        update_post_meta( $post_id, 'track_name',     $track );
        update_post_meta( $post_id, 'artist_en',      $enname );
        update_post_meta( $post_id, 'song_en',        $entrack );
        update_post_meta( $post_id, 'fifu_image_url', $img_url );
        update_post_meta( $post_id, 'musics_type',    'musicss_video' );
        update_post_meta( $post_id, 'online_ply',     'off' );
        if ( ! empty( $link1080 ) ) update_post_meta( $post_id, 'video1080', "{$urldlhost}/{$dir_file}/{$trackname} [1080].mp4" );
        if ( ! empty( $link720 ) )  update_post_meta( $post_id, 'video720',  "{$urldlhost}/{$dir_file}/{$trackname} [720].mp4" );
        if ( ! empty( $link480 ) )  update_post_meta( $post_id, 'video480',  "{$urldlhost}/{$dir_file}/{$trackname} [480].mp4" );
        if ( ! empty( $link320 ) )  update_post_meta( $post_id, 'music320',  "{$urldlhost}/{$dir_file}/{$trackname}.mp3" );

        $warnings = array();

        $r = $this->upload_or_download( 'filecover', $cover, $img_path, self::ALLOWED_IMAGE );
        $this->collect_warning( $warnings, $r, 'کاور' );
        if ( $r === true ) {
            $this->resize_image( $img_path, 470, 470 );
            $this->attach_image_to_post( $img_path, $post_id );
        }

        $r = $this->send_to_dlhost( 'urlfunction_video', array(
            'link1080'      => $link1080,
            'link720'       => $link720,
            'link480'       => $link480,
            'link320'       => $link320,
            'hs_video1080p' => $link1080,
            'hs_video720p'  => $link720,
            'hs_video480p'  => $link480,
            'enname'        => $enname,
            'entrack'       => $entrack,
            'artist_en'     => $enname,
            'song_en'       => $entrack,
            'cover'         => $cover,
        ) );
        $this->collect_warning( $warnings, $r, 'ارسال به هاست دانلود' );

        $final_msg = $req_msg;
        $has_err   = false;
        if ( ! empty( $warnings ) ) {
            $final_msg .= ' ⚠ هشدار: ' . implode( ' | ', $warnings );
            $has_err = true;
        }

        $this->redirect( 'smp-video', $final_msg, $has_err );
    }

    /* ═══════════════════════════════════════════════
       آلبوم
    ═══════════════════════════════════════════════ */
    public function handle_album() {
        $this->verify( 'smp_submit_album' );
        set_time_limit( 600 );

        $artist    = sanitize_text_field( wp_unslash( $_POST['artist'] ?? '' ) );
        $song      = sanitize_text_field( wp_unslash( $_POST['song'] ?? '' ) );
        $artist_en = sanitize_text_field( wp_unslash( $_POST['artist_en'] ?? '' ) );
        $song_en   = sanitize_text_field( wp_unslash( $_POST['song_en'] ?? '' ) );
        $cover     = esc_url_raw( $_POST['cover'] ?? '' );
        $info      = sanitize_text_field( wp_unslash( $_POST['info'] ?? '' ) );
        $cover_mp3 = esc_url_raw( $_POST['cover_mp3'] ?? '' );
        $is_draft  = sanitize_text_field( $_POST['is_draft'] ?? 'publish' );

        if ( empty( $artist ) || empty( $song ) || empty( $artist_en ) || empty( $song_en ) ) {
            $this->redirect( 'smp-album', 'خطا: فیلدهای اجباری را پر کنید.', true );
        }

        foreach ( array( $cover, $cover_mp3 ) as $u ) {
            $v = $this->validate_remote_url( $u );
            if ( is_wp_error( $v ) ) {
                $this->redirect( 'smp-album', 'خطا: ' . $v->get_error_message(), true );
            }
        }

        $urlsite   = $this->cfg( 'urlsite' );
        $urldlhost = $this->cfg( 'urldlhost' );

        $title = "دانلود آلبوم جدید {$artist} به نام {$song}";
        if ( $this->check_duplicate_title( $title ) ) {
            $this->redirect( 'smp-album', 'خطا: پستی با این عنوان قبلاً وجود دارد!', true );
        }

        $year   = date( 'Y' ); $months = date( 'm' );
        $this->ensure_upload_dir( $year, $months );

        $artist_en     = $this->safe_name( $artist_en );
        $song_en       = $this->safe_name( $song_en );
        $album_name    = "{$artist_en} - {$song_en}";
        $album_name128 = "{$artist_en} - {$song_en} [128]";
        $album_name320 = "{$artist_en} - {$song_en} [320]";
        $dir_file      = 'album/' . $this->jdate_dir();
        $img_path      = $this->uploads_path( $year, $months, $album_name . '.jpg' );
        $img_url       = $this->uploads_url( $year, $months, $album_name . '.jpg' );

        $singer_tag_fa = "https://{$urlsite}/tag/" . sanitize_title_with_dashes( $artist ) . '/';
        $singer_tag_en = "https://{$urlsite}/tag/" . sanitize_title_with_dashes( $artist_en ) . '/';

        $seo_title = "دانلود آلبوم جدید {$artist} به نام {$song} - " . $this->cfg( 'namesite' );
        $metadesc  = "دانلود آلبوم جدید {$artist} به نام {$song} Download New Album {$artist_en} Called {$song_en} همراه با بالاترین کیفیت بصورت تکی و یکجا";

        $content = sprintf(
            '
<p style="text-align: center;"><strong>آلبوم %1$s</strong> از <strong>%2$s</strong></p>
<p style="text-align: center;"><a href="%3$s">%2$s</a> بنام %1$s</p>
<div style="text-align: center;"><a href="%4$s">%5$s</a> – %6$s</div>
<p style="text-align: center;">%7$s</p>
<!--more-->
',
            esc_html( $song ), esc_html( $artist ), esc_url( $singer_tag_fa ),
            esc_url( $singer_tag_en ), esc_html( $artist_en ), esc_html( $song_en ), esc_html( $info )
        );

        list( $post_status, $req_msg ) = $this->post_status_and_msg( $is_draft );
        $cat_ids = $this->get_category_ids( 'cat_album', '2' );

        $post_id = wp_insert_post( array(
            'post_author'   => get_current_user_id(),
            'post_status'   => $post_status,
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( $title ),
            'post_category' => $cat_ids,
            'post_name'     => sanitize_title_with_dashes( $album_name ),
            'post_content'  => $content,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            $this->redirect( 'smp-album', 'خطا در ایجاد پست: ' . $post_id->get_error_message(), true );
        }

        update_post_meta( $post_id, '_yoast_wpseo_title',    strip_tags( $seo_title ) );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', strip_tags( $metadesc ) );
        update_post_meta( $post_id, 'art_name',       $artist );
        update_post_meta( $post_id, 'track_name',     $song );
        update_post_meta( $post_id, 'artist_en',      $artist_en );
        update_post_meta( $post_id, 'song_en',        $song_en );
        update_post_meta( $post_id, 'fifu_image_url', $img_url );
        update_post_meta( $post_id, 'musics_type',    'musicss_album' );
        update_post_meta( $post_id, 'online_ply',     'off' );
        update_post_meta( $post_id, 'slider_song',    'off' );
        update_post_meta( $post_id, 'pplayer_in',     'off' );
        update_post_meta( $post_id, 'album320',       "{$urldlhost}/{$dir_file}/{$album_name320}.zip" );
        update_post_meta( $post_id, 'album128',       "{$urldlhost}/{$dir_file}/{$album_name128}.zip" );
        update_post_meta( $post_id, 'talbume320',     "{$urldlhost}/{$dir_file}/{$album_name320}" );
        update_post_meta( $post_id, 'talbume128',     "{$urldlhost}/{$dir_file}/{$album_name128}" );

        $this->maybe_set_terms( $post_id, $artist, 'singer' );
        wp_set_post_tags( $post_id, array( 'download new album', 'دانلود آلبوم', 'دانلود آلبوم جدید', $artist, $artist_en ) );

        $tracks_data   = array();
        $track_warning = false;
        for ( $i = 1; $i <= 15; $i++ ) {
            $tname = sanitize_text_field( wp_unslash( $_POST["track_name_{$i}"] ?? '' ) );
            $tl320 = esc_url_raw( $_POST["track_link320_{$i}"] ?? '' );
            $tl128 = esc_url_raw( $_POST["track_link128_{$i}"] ?? '' );
            if ( empty( $tname ) ) continue;

            foreach ( array( $tl320, $tl128 ) as $u ) {
                if ( is_wp_error( $this->validate_remote_url( $u ) ) ) $track_warning = true;
            }

            $tracks_data[] = array(
                'title'     => $tname,
                'al_url320' => "{$urldlhost}/{$dir_file}/{$album_name320}/{$artist_en} - {$tname}.mp3",
                'al_url128' => "{$urldlhost}/{$dir_file}/{$album_name128}/{$artist_en} - {$tname} [128].mp3",
            );
        }
        update_post_meta( $post_id, 'album_dl', $tracks_data );

        $warnings = array();
        if ( $track_warning ) $warnings[] = 'برخی لینک‌های ترک‌ها نامعتبر بودند و نادیده گرفته شدند';

        $r = $this->upload_or_download( 'filecover', $cover, $img_path, self::ALLOWED_IMAGE );
        $this->collect_warning( $warnings, $r, 'کاور' );
        if ( $r === true ) {
            $this->attach_image_to_post( $img_path, $post_id );
        }

        $curl_data = array( 'artist_en' => $artist_en, 'song_en' => $song_en, 'cover' => $cover, 'cover_mp3' => $cover_mp3 );
        for ( $i = 1; $i <= 15; $i++ ) {
            $tname = sanitize_text_field( wp_unslash( $_POST["track_name_{$i}"] ?? '' ) );
            $tl320 = esc_url_raw( $_POST["track_link320_{$i}"] ?? '' );
            $tl128 = esc_url_raw( $_POST["track_link128_{$i}"] ?? '' );
            $curl_data["track_name_{$i}"]      = $tname;
            $curl_data["track_link320_{$i}"]   = $tl320;
            $curl_data["track_link128_{$i}"]   = $tl128;
            $curl_data[ 'hs_trackname_' . $i ] = $tname;
            $curl_data[ 'hs_link_320_' . $i ]  = $tl320;
            $curl_data[ 'hs_link_128_' . $i ]  = $tl128;
        }
        $r = $this->send_to_dlhost( 'urlfunction_album', $curl_data );
        $this->collect_warning( $warnings, $r, 'ارسال به هاست دانلود' );

        $final_msg = $req_msg;
        $has_err   = false;
        if ( ! empty( $warnings ) ) {
            $final_msg .= ' ⚠ هشدار: ' . implode( ' | ', $warnings );
            $has_err = true;
        }

        $this->redirect( 'smp-album', $final_msg, $has_err );
    }

    /* ═══════════════════════════════════════════════
       لیچ فایل
    ═══════════════════════════════════════════════ */
    public function handle_leech() {
        $this->verify( 'smp_submit_leech' );
        set_time_limit( 600 );

        $enname      = sanitize_text_field( wp_unslash( $_POST['enname'] ?? '' ) );
        $entrack     = sanitize_text_field( wp_unslash( $_POST['entrack'] ?? '' ) );
        $cover       = esc_url_raw( $_POST['cover'] ?? '' );
        $linkfilemp3 = esc_url_raw( $_POST['linkfilemp3'] ?? '' );
        $linkfilemp4 = esc_url_raw( $_POST['linkfilemp4'] ?? '' );

        if ( empty( $enname ) || empty( $entrack ) ) {
            $this->redirect( 'smp-leech', 'خطا: فیلدهای اجباری را پر کنید.', true );
        }

        foreach ( array( $cover, $linkfilemp3, $linkfilemp4 ) as $u ) {
            $v = $this->validate_remote_url( $u );
            if ( is_wp_error( $v ) ) {
                $this->redirect( 'smp-leech', 'خطا: ' . $v->get_error_message(), true );
            }
        }

        $enname    = $this->safe_name( $enname );
        $entrack   = $this->safe_name( $entrack );
        $year      = date( 'Y' );
        $months    = date( 'm' );
        $this->ensure_upload_dir( $year, $months );
        $filenames = "{$enname} - {$entrack}";

        $uid      = uniqid( '', true );
        $temp_jpg = $this->uploads_path( $year, $months, 'leech-' . $uid . '.jpg' );
        $temp_mp3 = $this->uploads_path( $year, $months, 'leech-' . $uid . '.mp3' );
        $temp_mp4 = $this->uploads_path( $year, $months, 'leech-' . $uid . '.mp4' );

        $warnings = array();

        $r1 = $this->upload_or_download( 'filecover', $cover, $temp_jpg, self::ALLOWED_IMAGE );
        $this->collect_warning( $warnings, $r1, 'کاور' );

        $r2 = $this->upload_or_download( 'filemp3', $linkfilemp3, $temp_mp3, self::ALLOWED_AUDIO );
        $this->collect_warning( $warnings, $r2, 'فایل MP3' );

        $r3 = $this->upload_or_download( 'filemp4', $linkfilemp4, $temp_mp4, self::ALLOWED_VIDEO );
        $this->collect_warning( $warnings, $r3, 'فایل MP4' );

        $cover_url = ( true === $r1 ) ? $this->uploads_url( $year, $months, 'leech-' . $uid . '.jpg' ) : '';
        $link_mp3  = ( true === $r2 ) ? $this->uploads_url( $year, $months, 'leech-' . $uid . '.mp3' ) : '';
        $link_mp4  = ( true === $r3 ) ? $this->uploads_url( $year, $months, 'leech-' . $uid . '.mp4' ) : '';

        $r = $this->send_to_dlhost( 'urlfunction_leech', array(
            'linkfilemp3' => $link_mp3,
            'linkfilemp4' => $link_mp4,
            'enname'      => $enname,
            'entrack'     => $entrack,
            'cover'       => $cover_url,
            'FileNames'   => $filenames,
        ) );
        $this->collect_warning( $warnings, $r, 'ارسال به هاست دانلود' );

        @unlink( $temp_mp3 );
        @unlink( $temp_mp4 );
        @unlink( $temp_jpg );

        $final_msg = 'لیچ فایل با موفقیت انجام شد.';
        $has_err   = false;
        if ( ! empty( $warnings ) ) {
            $final_msg = '⚠ مشکلاتی پیش آمد: ' . implode( ' | ', $warnings );
            $has_err = true;
        }

        $this->redirect( 'smp-leech', $final_msg, $has_err );
    }
}
