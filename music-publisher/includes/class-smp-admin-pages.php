<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMP_Admin_Pages {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menus() {
        add_menu_page(
            __( 'Music panel', 'sajad-music-publisher' ),
            __( 'Music panel', 'sajad-music-publisher' ),
            'edit_posts',
            'smp-dashboard',
            array( $this, 'page_music' ),
            'dashicons-format-audio',
            25
        );
        add_submenu_page( 'smp-dashboard', __( 'Single', 'sajad-music-publisher' ), __( 'Single', 'sajad-music-publisher' ), 'edit_posts', 'smp-dashboard', array( $this, 'page_music' ) );
        add_submenu_page( 'smp-dashboard', __( 'Remix', 'sajad-music-publisher' ), __( 'Remix', 'sajad-music-publisher' ), 'edit_posts', 'smp-remix', array( $this, 'page_remix' ) );
        add_submenu_page( 'smp-dashboard', __( 'Nohe', 'sajad-music-publisher' ), __( 'Nohe', 'sajad-music-publisher' ), 'edit_posts', 'smp-nohe', array( $this, 'page_nohe' ) );
        add_submenu_page( 'smp-dashboard', __( 'Music video', 'sajad-music-publisher' ), __( 'Music video', 'sajad-music-publisher' ), 'edit_posts', 'smp-video', array( $this, 'page_video' ) );
        add_submenu_page( 'smp-dashboard', __( 'Album', 'sajad-music-publisher' ), __( 'Album', 'sajad-music-publisher' ), 'edit_posts', 'smp-album', array( $this, 'page_album' ) );
        add_submenu_page( 'smp-dashboard', __( 'Leech file', 'sajad-music-publisher' ), __( 'Leech file', 'sajad-music-publisher' ), 'edit_posts', 'smp-leech', array( $this, 'page_leech' ) );
        add_submenu_page( 'smp-dashboard', __( 'Settings', 'sajad-music-publisher' ), __( 'Settings', 'sajad-music-publisher' ), 'manage_options', 'smp-settings', array( new SMP_Settings(), 'render_settings_page' ) );
    }

    public function enqueue_assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $pages = array( 'smp-dashboard', 'smp-remix', 'smp-nohe', 'smp-video', 'smp-album', 'smp-leech', 'smp-settings' );
        if ( ! in_array( $page, $pages, true ) && false === strpos( (string) $hook, 'smp' ) ) {
            return;
        }
        wp_enqueue_style( 'smp-admin', SMP_PLUGIN_URL . 'assets/css/admin.css', array(), SMP_VERSION );
        wp_enqueue_script( 'smp-admin', SMP_PLUGIN_URL . 'assets/js/admin.js', array(), SMP_VERSION, true );
    }

    /* ═══════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════ */

    private function nav_tabs( $active ) {
        $tabs = array(
            'smp-dashboard' => array( 'icon' => '🎵', 'label' => 'تک آهنگ' ),
            'smp-remix'     => array( 'icon' => '🎛️', 'label' => 'ریمیکس' ),
            'smp-nohe'      => array( 'icon' => '🕌', 'label' => 'نوحه' ),
            'smp-video'     => array( 'icon' => '🎬', 'label' => 'موزیک ویدیو' ),
            'smp-album'     => array( 'icon' => '💿', 'label' => 'آلبوم' ),
            'smp-leech'     => array( 'icon' => '⬇️', 'label' => 'لیچ فایل' ),
            'smp-settings'  => array( 'icon' => '⚙️', 'label' => 'تنظیمات' ),
        );
        echo '<div class="smp-tabs">';
        foreach ( $tabs as $slug => $t ) {
            $cls = ( $slug === $active ) ? 'smp-tab active' : 'smp-tab';
            $url = admin_url( 'admin.php?page=' . $slug );
            echo '<a href="' . esc_url($url) . '" class="' . $cls . '"><span class="tab-dot"></span>' . $t['icon'] . ' ' . esc_html($t['label']) . '</a>';
        }
        echo '</div>';
    }

    private function header( $icon, $title, $desc ) {
        echo '<div class="smp-header">';
        echo '<div class="smp-header-icon">' . $icon . '</div>';
        echo '<div class="smp-header-text"><h1>' . esc_html($title) . '</h1><p>' . esc_html($desc) . '</p></div>';
        echo '<div class="smp-theme-toggle" id="smp-theme-toggle" title="تغییر حالت روز/شب">
            <span class="smp-theme-icon-light">☀️</span>
            <span class="smp-theme-icon-dark">🌙</span>
        </div>';
        echo '</div>';
    }

    private function notice() {
        $flash = get_transient( 'smp_flash_' . get_current_user_id() );
        if ( is_array( $flash ) && ! empty( $flash['msg'] ) ) {
            delete_transient( 'smp_flash_' . get_current_user_id() );
            $type = ! empty( $flash['err'] ) ? 'error' : 'success';
            echo '<div class="smp-notice ' . esc_attr( $type ) . '" role="status">' . esc_html( $flash['msg'] ) . '</div>';
        }
    }

    private function card_open( $title ) {
        echo '<div class="smp-card"><div class="smp-card-title">' . esc_html( $title ) . '</div>';
    }

    private function card_close() { echo '</div>'; }

    private function field( $label, $html, $req = false ) {
        $req_star = $req ? '<span class="req">*</span>' : '';
        echo '<div class="smp-field"><label>' . $req_star . $label . '</label>' . $html . '</div>';
    }

    private function input( $name, $placeholder = '', $type = 'text', $req = false ) {
        $r = $req ? ' required' : '';
        $dir = ( $type === 'url' ) ? ' dir="ltr"' : '';
        return '<input type="' . $type . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '"' . $dir . $r . '>';
    }

    private function file_upload( $name, $label = 'انتخاب فایل یا بکشید اینجا' ) {
        return '<div class="smp-file-upload"><input type="file" name="' . esc_attr($name) . '">
            <span class="upload-icon">📎</span>
            <span class="upload-text">' . esc_html($label) . '</span></div>';
    }

    private function select_draft() {
        return '<select name="is_draft"><option value="publish">✅ منتشر شود</option><option value="draft">📝 پیشنویس شود</option></select>';
    }

    private function select_fatxt() {
        $o = '';
        for ($i=1;$i<=20;$i++) $o .= '<option value="'.$i.'">نوع '.$i.'</option>';
        return '<select name="fatxt">'.$o.'</select>';
    }

    private function select_entxt() {
        $o = '';
        for ($i=1;$i<=10;$i++) $o .= '<option value="'.$i.'">نوع '.$i.'</option>';
        return '<select name="entxt">'.$o.'</select>';
    }

    private function select_seo() {
        $opts = array('1'=>'- '.SMP_Settings::get('namesite','سایت'),'2'=>'| بهترین کیفیت','3'=>'( کیفیت اصلی ) + متن','4'=>'( ورژن اصلی ) + متن','5'=>'MP3 + متن ترانه','6'=>'| همراه با متن و کیفیت عالی','7'=>'+ متن و کیفیت عالی','8'=>'{ با متن و پخش آنلاین }');
        $o = '';
        foreach ($opts as $v=>$l) $o .= '<option value="'.esc_attr($v).'">'.esc_html($l).'</option>';
        return '<select name="txtseo">'.$o.'</select>';
    }

    private function submit_btn( $label = 'ارسال پست' ) {
        echo '<div class="smp-submit-row"><button type="submit" class="smp-btn smp-btn-primary">🚀 ' . esc_html($label) . '</button></div>';
    }

    private function form_open( $action_val ) {
        $url = admin_url('admin-post.php');
        echo '<form enctype="multipart/form-data" method="POST" action="' . esc_url($url) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action_val) . '">';
        wp_nonce_field( $action_val );
    }

    /* ═══════════════════════════════════════════════
       SHARED MUSIC FORM BODY (تک آهنگ / ریمیکس / نوحه)
    ═══════════════════════════════════════════════ */

    private function music_form_body() {

        $this->card_open('⚙️ تنظیمات پست');
        echo '<div class="smp-grid smp-grid-4">';
        $this->field( 'متن فارسی', $this->select_fatxt() );
        $this->field( 'متن انگلیسی', $this->select_entxt() );
        $this->field( 'عنوان سئو', $this->select_seo() );
        $this->field( 'وضعیت', $this->select_draft() );
        echo '</div>';
        $this->card_close();

        $this->card_open('🎤 اطلاعات اثر');
        echo '<div class="smp-grid smp-grid-2">';
        $this->field( 'نام خواننده', $this->input('name','نام خواننده','text',true), true );
        $this->field( 'نام اثر', $this->input('track','نام اثر','text',true), true );
        $this->field( 'Artist (EN)', $this->input('enname','Artist Name','text',true), true );
        $this->field( 'Track (EN)', $this->input('entrack','Track Name','text',true), true );
        echo '</div>';
        echo '<div class="smp-grid" style="margin-top:14px">';
        $this->field( 'اطلاعات پست', $this->input('info','توضیحات کوتاه درباره اثر') );
        echo '</div>';
        echo '<div class="smp-grid smp-grid-4" style="margin-top:14px">';
        $this->field( 'ترانه‌سرا', $this->input('lyrc','ترانه‌سرا') );
        $this->field( 'آهنگساز', $this->input('melody','آهنگساز') );
        $this->field( 'تنظیم‌کننده', $this->input('arrng','تنظیم کننده') );
        $this->field( 'میکس و مسترینگ', $this->input('mix','میکس و مسترینگ') );
        echo '</div>';
        $this->card_close();

        $this->card_open('🖼️ کاور');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'آپلود مستقیم', $this->file_upload('filecover','انتخاب تصویر کاور') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک کاور پست', $this->input('cover','https://...','url') );
        echo '</div>';
        echo '<div>';
        $this->field( 'لینک کاور فایل MP3', $this->input('cover_mp3','https://...','url') );
        echo '</div>';
        echo '</div>';
        $this->card_close();

        $this->card_open('🎧 فایل‌های صوتی');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'آپلود فایل ۳۲۰', $this->file_upload('filelink320','انتخاب فایل MP3 کیفیت ۳۲۰') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک ۳۲۰', $this->input('link320','https://...','url') );
        echo '</div>';
        echo '<div>';
        $this->field( 'آپلود فایل ۱۲۸', $this->file_upload('filelink128','انتخاب فایل MP3 کیفیت ۱۲۸') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک ۱۲۸', $this->input('link128','https://...','url') );
        echo '</div>';
        echo '</div>';
        $this->card_close();

        $this->card_open('📝 متن آهنگ');
        $this->field( 'متن ترانه', '<textarea name="lyric" placeholder="متن آهنگ را اینجا وارد کنید..."></textarea>' );
        $this->card_close();
    }

    /* ═══════════════════════════════════════════════
       PAGES
    ═══════════════════════════════════════════════ */

    public function page_music() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('🎵','پنل ارسال تک آهنگ','ارسال آهنگ جدید به سایت با تمام جزئیات');
        $this->nav_tabs('smp-dashboard');
        $this->notice();
        $this->form_open('smp_submit_music');
        $this->music_form_body();
        $this->submit_btn('ارسال تک آهنگ');
        echo '</form></div>';
    }

    public function page_remix() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('🎛️','پنل ارسال ریمیکس','آپلود و ارسال ریمیکس به سایت');
        $this->nav_tabs('smp-remix');
        $this->notice();
        $this->form_open('smp_submit_remix');
        $this->music_form_body();
        $this->submit_btn('ارسال ریمیکس');
        echo '</form></div>';
    }

    public function page_nohe() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('🕌','پنل ارسال نوحه','آپلود و ارسال نوحه به سایت');
        $this->nav_tabs('smp-nohe');
        $this->notice();
        $this->form_open('smp_submit_nohe');
        $this->music_form_body();
        $this->submit_btn('ارسال نوحه');
        echo '</form></div>';
    }

    public function page_video() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('🎬','پنل ارسال موزیک ویدیو','آپلود و انتشار موزیک ویدیو');
        $this->nav_tabs('smp-video');
        $this->notice();
        $this->form_open('smp_submit_video');

        $this->card_open('⚙️ تنظیمات پست');
        echo '<div class="smp-grid smp-grid-4">';
        $this->field( 'متن فارسی', $this->select_fatxt() );
        $this->field( 'متن انگلیسی', $this->select_entxt() );
        $this->field( 'عنوان سئو', $this->select_seo() );
        $this->field( 'وضعیت', $this->select_draft() );
        echo '</div>';
        $this->card_close();

        $this->card_open('🎤 اطلاعات اثر');
        echo '<div class="smp-grid smp-grid-2">';
        $this->field( 'نام خواننده', $this->input('name','نام خواننده','text',true), true );
        $this->field( 'نام اثر', $this->input('track','نام اثر','text',true), true );
        $this->field( 'Artist (EN)', $this->input('enname','Artist','text',true), true );
        $this->field( 'Track (EN)', $this->input('entrack','Track Name','text',true), true );
        echo '</div>';
        echo '<div class="smp-grid" style="margin-top:14px">';
        $this->field( 'اطلاعات پست', $this->input('info','توضیحات') );
        echo '</div>';
        $this->card_close();

        $this->card_open('🖼️ کاور');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'آپلود کاور', $this->file_upload('filecover') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک کاور', $this->input('cover','https://...','url') );
        echo '</div>';
        echo '<div></div>';
        echo '</div>';
        $this->card_close();

        $this->card_open('🎬 فایل‌های ویدیو');
        echo '<div class="smp-grid smp-grid-2">';
        $this->field( 'لینک MP4 کیفیت ۱۰۸۰p', $this->input('link1080','https://...','url') );
        $this->field( 'لینک MP4 کیفیت ۷۲۰p', $this->input('link720','https://...','url') );
        $this->field( 'لینک MP4 کیفیت ۴۸۰p', $this->input('link480','https://...','url') );
        $this->field( 'لینک MP3 صوتی', $this->input('link320','https://...','url') );
        echo '</div>';
        $this->card_close();

        $this->submit_btn('ارسال موزیک ویدیو');
        echo '</form></div>';
    }

    public function page_album() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('💿','پنل ارسال آلبوم','ارسال آلبوم با لیست کامل ترک‌ها');
        $this->nav_tabs('smp-album');
        $this->notice();
        $this->form_open('smp_submit_album');

        $this->card_open('⚙️ تنظیمات پست');
        echo '<div class="smp-grid smp-grid-3">';
        $this->field( 'وضعیت', $this->select_draft() );
        $this->field( 'نام هنرمند', $this->input('artist','نام هنرمند','text',true), true );
        $this->field( 'نام آلبوم', $this->input('song','نام آلبوم','text',true), true );
        echo '</div>';
        echo '<div class="smp-grid smp-grid-3" style="margin-top:14px">';
        $this->field( 'Artist (EN)', $this->input('artist_en','Artist','text',true), true );
        $this->field( 'Album (EN)', $this->input('song_en','Album Name','text',true), true );
        $this->field( 'لینک کاور MP3', $this->input('cover_mp3','https://...','url') );
        echo '</div>';
        $this->card_close();

        $this->card_open('🖼️ کاور آلبوم');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'اطلاعات آلبوم', $this->input('info','توضیحات') );
        echo '</div>';
        echo '<div>';
        $this->field( 'لینک کاور', $this->input('cover','https://...','url') );
        echo '</div>';
        echo '</div>';
        $this->card_close();

        $this->card_open('🎵 لیست ترک‌ها');
        echo '<div style="display:grid;gap:8px;">';
        echo '<div class="smp-track-row" style="background:rgba(108,99,255,0.08);border-color:rgba(108,99,255,0.2);">';
        echo '<span class="smp-track-num" style="color:#6C63FF;font-size:10px;">#</span>';
        echo '<span style="font-size:12px;color:#8B8BA7;font-weight:600;">نام ترک</span>';
        echo '<span style="font-size:12px;color:#8B8BA7;font-weight:600;">لینک ۳۲۰</span>';
        echo '<span style="font-size:12px;color:#8B8BA7;font-weight:600;">لینک ۱۲۸</span>';
        echo '</div>';
        for ( $i = 1; $i <= 15; $i++ ) {
            echo '<div class="smp-track-row">';
            echo '<span class="smp-track-num">' . $i . '</span>';
            echo '<input type="text" name="track_name_' . $i . '" placeholder="نام ترک ' . $i . '">';
            echo '<input type="url" name="track_link320_' . $i . '" placeholder="https://... (320)">';
            echo '<input type="url" name="track_link128_' . $i . '" placeholder="https://... (128)">';
            echo '</div>';
        }
        echo '</div>';
        $this->card_close();

        $this->submit_btn('ارسال آلبوم');
        echo '</form></div>';
    }

    public function page_leech() {
        echo '<div class="smp-wrap" dir="rtl" data-theme="dark">';
        $this->header('⬇️','پنل لیچ فایل','انتقال فایل از سرور به هاست دانلود');
        $this->nav_tabs('smp-leech');
        $this->notice();
        $this->form_open('smp_submit_leech');

        $this->card_open('🎤 اطلاعات فایل');
        echo '<div class="smp-grid smp-grid-2">';
        $this->field( 'Artist (EN)', $this->input('enname','Artist','text',true), true );
        $this->field( 'Track (EN)', $this->input('entrack','Track','text',true), true );
        echo '</div>';
        $this->card_close();

        $this->card_open('🖼️ کاور');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'آپلود کاور', $this->file_upload('filecover') );
        echo '</div>';
        echo '<div>';
        $this->field( 'لینک کاور', $this->input('cover','https://...','url') );
        echo '</div>';
        echo '</div>';
        $this->card_close();

        $this->card_open('📁 فایل‌ها');
        echo '<div class="smp-grid smp-grid-2">';
        echo '<div>';
        $this->field( 'آپلود MP3', $this->file_upload('filemp3','انتخاب فایل MP3') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک MP3', $this->input('linkfilemp3','https://...','url') );
        echo '</div>';
        echo '<div>';
        $this->field( 'آپلود MP4', $this->file_upload('filemp4','انتخاب فایل MP4') );
        echo '<div class="smp-or">یا</div>';
        $this->field( 'لینک MP4', $this->input('linkfilemp4','https://...','url') );
        echo '</div>';
        echo '</div>';
        $this->card_close();

        $this->submit_btn('شروع لیچ');
        echo '</form></div>';
    }
}
