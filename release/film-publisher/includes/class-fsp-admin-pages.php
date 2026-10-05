<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Admin_Pages {

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menus() {
		add_menu_page( __( 'Film panel', 'film-publisher' ), __( 'Film panel', 'film-publisher' ), 'edit_posts', 'fsp-dashboard', array( $this, 'page_movie' ), 'dashicons-video-alt3', 26 );
		add_submenu_page( 'fsp-dashboard', __( 'Movie', 'film-publisher' ), __( 'Movie', 'film-publisher' ), 'edit_posts', 'fsp-dashboard', array( $this, 'page_movie' ) );
		add_submenu_page( 'fsp-dashboard', __( 'Series', 'film-publisher' ), __( 'Series', 'film-publisher' ), 'edit_posts', 'fsp-series', array( $this, 'page_series' ) );
		add_submenu_page( 'fsp-dashboard', __( 'Trailer', 'film-publisher' ), __( 'Trailer', 'film-publisher' ), 'edit_posts', 'fsp-trailer', array( $this, 'page_trailer' ) );
		add_submenu_page( 'fsp-dashboard', __( 'Leech file', 'film-publisher' ), __( 'Leech file', 'film-publisher' ), 'edit_posts', 'fsp-leech', array( $this, 'page_leech' ) );
		add_submenu_page( 'fsp-dashboard', __( 'Settings', 'film-publisher' ), __( 'Settings', 'film-publisher' ), 'manage_options', 'fsp-settings', array( new FSP_Settings(), 'render_settings_page' ) );
	}

	public function enqueue_assets( $hook ) {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pages = array( 'fsp-dashboard', 'fsp-series', 'fsp-trailer', 'fsp-leech', 'fsp-settings' );
		if ( ! in_array( $page, $pages, true ) && false === strpos( (string) $hook, 'fsp' ) ) {
			return;
		}
		wp_enqueue_style( 'fsp-admin', FSP_PLUGIN_URL . 'assets/css/admin.css', array(), FSP_VERSION );
		wp_enqueue_script( 'fsp-admin', FSP_PLUGIN_URL . 'assets/js/admin.js', array(), FSP_VERSION, true );
	}

	private function wrap_open( $icon, $title, $desc, $active ) {
		echo '<div class="fsp-wrap" dir="rtl" data-theme="dark">';
		echo '<div class="fsp-header"><div class="fsp-header-icon">' . esc_html( $icon ) . '</div>';
		echo '<div class="fsp-header-text"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $desc ) . '</p></div>';
		echo '<button type="button" class="fsp-theme-toggle" id="fsp-theme-toggle"><span class="fsp-theme-icon-light">☀️</span><span class="fsp-theme-icon-dark">🌙</span></button></div>';
		( new FSP_Settings() )->tabs( $active );
		$this->notice();
	}

	private function notice() {
		$flash = get_transient( 'fsp_flash_' . get_current_user_id() );
		if ( is_array( $flash ) && ! empty( $flash['msg'] ) ) {
			delete_transient( 'fsp_flash_' . get_current_user_id() );
			$type = ! empty( $flash['err'] ) ? 'error' : 'success';
			echo '<div class="fsp-notice ' . esc_attr( $type ) . '" role="status">' . esc_html( $flash['msg'] ) . '</div>';
		}
	}

	private function card_open( $title ) {
		echo '<div class="fsp-card"><div class="fsp-card-title">' . esc_html( $title ) . '</div>';
	}

	private function card_close() {
		echo '</div>';
	}

	private function field( $label, $html, $req = false ) {
		$star = $req ? '<span class="req">*</span>' : '';
		echo '<div class="fsp-field"><label>' . $star . esc_html( $label ) . '</label>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function input( $name, $placeholder = '', $type = 'text', $req = false ) {
		$r   = $req ? ' required' : '';
		$dir = in_array( $type, array( 'url', 'number' ), true ) ? ' dir="ltr"' : '';
		return '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $dir . $r . '>';
	}

	private function file_upload( $name, $label ) {
		return '<div class="fsp-file-upload"><input type="file" name="' . esc_attr( $name ) . '"><span class="upload-text">' . esc_html( $label ) . '</span></div>';
	}

	private function select_draft() {
		return '<select name="is_draft"><option value="publish">' . esc_html__( 'Publish', 'film-publisher' ) . '</option><option value="draft">' . esc_html__( 'Draft', 'film-publisher' ) . '</option></select>';
	}

	private function form_open( $action ) {
		echo '<form enctype="multipart/form-data" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}

	private function submit_btn( $label ) {
		echo '<div class="fsp-submit-row"><button type="submit" class="fsp-btn fsp-btn-primary">' . esc_html( $label ) . '</button></div></form></div>';
	}

	private function meta_fields() {
		$this->card_open( __( 'Title and credits', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Title (FA)', 'film-publisher' ), $this->input( 'title_fa', '', 'text', true ), true );
		$this->field( __( 'Title (EN)', 'film-publisher' ), $this->input( 'title_en', '', 'text', true ), true );
		$this->field( __( 'Year', 'film-publisher' ), $this->input( 'year', '2024', 'number' ) );
		$this->field( __( 'Duration (minutes)', 'film-publisher' ), $this->input( 'duration', '120', 'number' ) );
		$this->field( __( 'IMDb ID', 'film-publisher' ), $this->input( 'imdb', 'tt0111161' ) );
		$this->field( __( 'Country', 'film-publisher' ), $this->input( 'country' ) );
		$this->field( __( 'Director', 'film-publisher' ), $this->input( 'director' ) );
		$this->field( __( 'Actors', 'film-publisher' ), $this->input( 'actors' ) );
		echo '</div>';
		echo '<div class="fsp-grid" style="margin-top:14px">';
		$this->field( __( 'Summary', 'film-publisher' ), '<textarea name="summary" rows="4"></textarea>' );
		echo '</div>';
		$this->card_close();
	}

	public function page_movie() {
		$this->wrap_open( '🎬', __( 'Publish movie', 'film-publisher' ), __( 'Create a movie post with poster and quality links.', 'film-publisher' ), 'fsp-dashboard' );
		$this->form_open( 'fsp_submit_movie' );
		$this->card_open( __( 'Post', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Status', 'film-publisher' ), $this->select_draft() );
		$this->field( __( 'Audio / subtitle', 'film-publisher' ), '<select name="lang_type"><option value="dubbed">' . esc_html__( 'Dubbed', 'film-publisher' ) . '</option><option value="subbed">' . esc_html__( 'Subtitled', 'film-publisher' ) . '</option><option value="both">' . esc_html__( 'Dubbed + sub', 'film-publisher' ) . '</option></select>' );
		echo '</div>';
		$this->card_close();
		$this->meta_fields();
		$this->card_open( __( 'Poster', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Upload poster', 'film-publisher' ), $this->file_upload( 'fileposter', __( 'Choose image', 'film-publisher' ) ) );
		$this->field( __( 'Poster URL', 'film-publisher' ), $this->input( 'poster', 'https://...', 'url' ) );
		echo '</div>';
		$this->card_close();
		$this->card_open( __( 'Download links', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( '4K', $this->input( 'link_4k', 'https://...', 'url' ) );
		$this->field( '1080p', $this->input( 'link_1080', 'https://...', 'url' ) );
		$this->field( '720p', $this->input( 'link_720', 'https://...', 'url' ) );
		$this->field( '480p', $this->input( 'link_480', 'https://...', 'url' ) );
		$this->field( __( 'Subtitle', 'film-publisher' ), $this->input( 'link_sub', 'https://...', 'url' ) );
		echo '</div>';
		$this->card_close();
		$this->submit_btn( __( 'Publish movie', 'film-publisher' ) );
	}

	public function page_series() {
		$this->wrap_open( '📺', __( 'Publish series', 'film-publisher' ), __( 'Season post with episode download links.', 'film-publisher' ), 'fsp-series' );
		$this->form_open( 'fsp_submit_series' );
		$this->card_open( __( 'Post', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-3">';
		$this->field( __( 'Status', 'film-publisher' ), $this->select_draft() );
		$this->field( __( 'Season number', 'film-publisher' ), $this->input( 'season', '1', 'number', true ), true );
		$this->field( __( 'Audio / subtitle', 'film-publisher' ), '<select name="lang_type"><option value="dubbed">' . esc_html__( 'Dubbed', 'film-publisher' ) . '</option><option value="subbed">' . esc_html__( 'Subtitled', 'film-publisher' ) . '</option></select>' );
		echo '</div>';
		$this->card_close();
		$this->meta_fields();
		$this->card_open( __( 'Poster', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Upload poster', 'film-publisher' ), $this->file_upload( 'fileposter', __( 'Choose image', 'film-publisher' ) ) );
		$this->field( __( 'Poster URL', 'film-publisher' ), $this->input( 'poster', 'https://...', 'url' ) );
		echo '</div>';
		$this->card_close();
		$this->card_open( __( 'Episodes', 'film-publisher' ) );
		echo '<div style="display:grid;gap:8px;">';
		echo '<div class="fsp-track-row" style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:8px;font-weight:600;">';
		echo '<span>#</span><span>' . esc_html__( 'Episode title', 'film-publisher' ) . '</span><span>1080p</span><span>720p</span></div>';
		for ( $i = 1; $i <= 20; $i++ ) {
			echo '<div class="fsp-track-row" style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:8px;">';
			echo '<span>' . (int) $i . '</span>';
			echo '<input type="text" name="ep_title_' . (int) $i . '" placeholder="' . esc_attr( sprintf( __( 'Episode %d', 'film-publisher' ), $i ) ) . '">';
			echo '<input type="url" dir="ltr" name="ep_1080_' . (int) $i . '" placeholder="https://...">';
			echo '<input type="url" dir="ltr" name="ep_720_' . (int) $i . '" placeholder="https://...">';
			echo '</div>';
		}
		echo '</div>';
		$this->card_close();
		$this->submit_btn( __( 'Publish series', 'film-publisher' ) );
	}

	public function page_trailer() {
		$this->wrap_open( '🎞️', __( 'Publish trailer', 'film-publisher' ), __( 'Trailer post with video URL.', 'film-publisher' ), 'fsp-trailer' );
		$this->form_open( 'fsp_submit_trailer' );
		$this->card_open( __( 'Trailer', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Title (FA)', 'film-publisher' ), $this->input( 'title_fa', '', 'text', true ), true );
		$this->field( __( 'Title (EN)', 'film-publisher' ), $this->input( 'title_en', '', 'text', true ), true );
		$this->field( __( 'Trailer URL', 'film-publisher' ), $this->input( 'link_trailer', 'https://...', 'url' ) );
		$this->field( __( 'Status', 'film-publisher' ), $this->select_draft() );
		echo '</div>';
		$this->field( __( 'Summary', 'film-publisher' ), '<textarea name="summary" rows="3"></textarea>' );
		$this->card_close();
		$this->card_open( __( 'Poster', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'Upload poster', 'film-publisher' ), $this->file_upload( 'fileposter', __( 'Choose image', 'film-publisher' ) ) );
		$this->field( __( 'Poster URL', 'film-publisher' ), $this->input( 'poster', 'https://...', 'url' ) );
		echo '</div>';
		$this->card_close();
		$this->submit_btn( __( 'Publish trailer', 'film-publisher' ) );
	}

	public function page_leech() {
		$this->wrap_open( '⬇️', __( 'Leech file', 'film-publisher' ), __( 'Copy a remote video to the download host.', 'film-publisher' ), 'fsp-leech' );
		$this->form_open( 'fsp_submit_leech' );
		$this->card_open( __( 'File', 'film-publisher' ) );
		echo '<div class="fsp-grid fsp-grid-2">';
		$this->field( __( 'File name (EN)', 'film-publisher' ), $this->input( 'filename', 'Movie.Name.2024.1080p', 'text', true ), true );
		$this->field( __( 'Remote URL', 'film-publisher' ), $this->input( 'linkfile', 'https://...', 'url' ) );
		echo '</div>';
		$this->field( __( 'Or upload MP4', 'film-publisher' ), $this->file_upload( 'filemp4', __( 'Choose MP4', 'film-publisher' ) ) );
		$this->card_close();
		$this->submit_btn( __( 'Start leech', 'film-publisher' ) );
	}
}
