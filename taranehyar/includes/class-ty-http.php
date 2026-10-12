<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * توابع مشترک HTTP — اعتبارسنجی آدرس (ضد SSRF) و ارسال به هاست دانلود.
 */
class TaranehYar_Http {

    /**
     * بررسی اینکه آدرس وارد شده یک URL واقعی با http/https هست
     * و به آدرس داخلی شبکه (SSRF) اشاره نمی‌کند — شامل بررسی IPهای resolve شده.
     */
    public static function validate_remote_url( $url ) {
        if ( empty( $url ) ) return true;
        $parts = wp_parse_url( $url );
        if ( empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'invalid_url', 'آدرس وارد شده معتبر نیست.' );
        }
        $host = $parts['host'] ?? '';
        if ( in_array( $host, array( 'localhost', '127.0.0.1', '0.0.0.0', '::1' ), true ) ) {
            return new WP_Error( 'ssrf_blocked', 'آدرس‌های داخلی مجاز نیستند.' );
        }
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return new WP_Error( 'ssrf_blocked', 'آدرس IP داخلی مجاز نیست.' );
            }
        } else {
            $ips = gethostbynamel( $host );
            if ( is_array( $ips ) ) {
                foreach ( $ips as $ip ) {
                    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return new WP_Error( 'ssrf_blocked', 'این دامنه به یک آدرس داخلی اشاره می‌کند و مجاز نیست.' );
                    }
                }
            }
        }
        return true;
    }

    /**
     * ارسال داده به هاست دانلود، فقط به آدرسی که در تنظیمات وارد شده (ضد SSRF).
     * توکن امنیتی به‌صورت خودکار به داده‌ها اضافه می‌شود.
     * بازگشت: true در موفقیت، WP_Error در خطا.
     */
    public static function post_to_dlhost( $endpoint_key, $data ) {
        $url = TaranehYar_Settings::get( $endpoint_key );
        if ( empty( $url ) ) {
            return new WP_Error( 'no_endpoint', 'آدرس هاست دانلود در تنظیمات وارد نشده است.' );
        }
        $valid = self::validate_remote_url( $url );
        if ( is_wp_error( $valid ) ) return $valid;

        $data['token'] = TaranehYar_Settings::get( 'apitoken' );

        $response = wp_remote_post( $url, array(
            'body'      => $data,
            'timeout'   => 300,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'http_error', "هاست دانلود خطا برگرداند (HTTP {$code})." );
        }
        return true;
    }
}
