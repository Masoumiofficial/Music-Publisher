<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * صف پردازش پس‌زمینه برای انتقال فایل‌ها به هاست دانلود.
 *
 * فرم‌ها بلافاصله پست را می‌سازند؛ انتقال‌های سنگین در این صف و با WP-Cron
 * انجام می‌شوند تا درخواست ادمین کوتاه بماند و با timeout هاست مشکلی پیش نیاید.
 */
class TaranehYar_Queue {

    const OPT_QUEUE = 'ty_queue';
    const OPT_LOG   = 'ty_queue_log';

    public function init() {
        add_action( 'ty_process_queue', array( $this, 'run' ) );
    }

    /**
     * افزودن کار به صف.
     *
     * @param array $job type (برچسب فارسی), title, endpoint_key, data (داده ارسالی), cleanup (فایل‌های موقت برای حذف پس از ارسال)
     * @return string شناسه کار
     */
    public static function add( $job ) {
        $queue = get_option( self::OPT_QUEUE, array() );
        if ( ! is_array( $queue ) ) $queue = array();

        $item = array(
            'id'           => uniqid( 'job_', true ),
            'type'         => isset( $job['type'] ) ? (string) $job['type'] : '',
            'title'        => isset( $job['title'] ) ? (string) $job['title'] : '',
            'endpoint_key' => isset( $job['endpoint_key'] ) ? (string) $job['endpoint_key'] : '',
            'data'         => isset( $job['data'] ) && is_array( $job['data'] ) ? $job['data'] : array(),
            'cleanup'      => isset( $job['cleanup'] ) ? array_values( (array) $job['cleanup'] ) : array(),
            'created'      => time(),
        );

        $queue[] = $item;
        update_option( self::OPT_QUEUE, $queue, false );
        self::log( $item, 'queued', 'در صف پردازش قرار گرفت.' );

        if ( ! wp_next_scheduled( 'ty_process_queue' ) ) {
            wp_schedule_single_event( time() + 5, 'ty_process_queue' );
        }
        // اجرای تقریباً فوری در پس‌زمینه
        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }

        return $item['id'];
    }

    /**
     * پردازش کارهای صف (حداکثر ~۵ دقیقه در هر نوبت).
     */
    public function run() {
        $queue = get_option( self::OPT_QUEUE, array() );
        if ( empty( $queue ) || ! is_array( $queue ) ) return;

        $deadline = time() + 280;
        while ( ! empty( $queue ) && time() < $deadline ) {
            $item = array_shift( $queue );
            update_option( self::OPT_QUEUE, $queue, false );
            $this->process_item( $item );
        }

        if ( ! empty( $queue ) ) {
            wp_schedule_single_event( time() + 30, 'ty_process_queue' );
        }
    }

    private function process_item( $item ) {
        self::log( $item, 'processing', 'در حال انتقال به هاست دانلود…' );

        $result = TaranehYar_Http::post_to_dlhost( $item['endpoint_key'], $item['data'] );

        foreach ( $item['cleanup'] as $file ) {
            if ( is_string( $file ) && $file !== '' && file_exists( $file ) ) {
                @unlink( $file );
            }
        }

        if ( is_wp_error( $result ) ) {
            self::log( $item, 'error', $result->get_error_message() );
        } else {
            self::log( $item, 'success', 'با موفقیت به هاست دانلود منتقل شد.' );
        }
    }

    /**
     * ثبت وضعیت یک کار در گزارش (حداکثر ۲۰ رکورد آخر).
     */
    public static function log( $item, $status, $message ) {
        $log = get_option( self::OPT_LOG, array() );
        if ( ! is_array( $log ) ) $log = array();

        $entry = array(
            'id'      => $item['id'],
            'type'    => $item['type'],
            'title'   => $item['title'],
            'status'  => $status,
            'message' => $message,
            'time'    => time(),
        );

        foreach ( $log as $k => $e ) {
            if ( isset( $e['id'] ) && $e['id'] === $item['id'] ) {
                $log[ $k ] = $entry;
                update_option( self::OPT_LOG, array_slice( $log, -20 ), false );
                return;
            }
        }

        $log[] = $entry;
        update_option( self::OPT_LOG, array_slice( $log, -20 ), false );
    }

    /** آخرین رکوردهای گزارش (جدیدترین اول) */
    public static function get_log( $limit = 10 ) {
        $log = get_option( self::OPT_LOG, array() );
        if ( ! is_array( $log ) ) return array();
        $log = array_reverse( $log );
        return array_slice( $log, 0, $limit );
    }

    /** برچسب فارسی وضعیت‌ها */
    public static function status_label( $status ) {
        $labels = array(
            'queued'     => 'در صف',
            'processing' => 'در حال انتقال',
            'success'    => 'موفق',
            'error'      => 'خطا',
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }
}
