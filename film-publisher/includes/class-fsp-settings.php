<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Settings {

	const ALL_KEYS = array(
		'urldlhost',
		'urlfunction_movie',
		'urlfunction_series',
		'urlfunction_trailer',
		'urlfunction_leech',
		'namesite',
		'namesite2',
		'urlsite',
		'cat_movie',
		'cat_series',
		'cat_trailer',
		'dlhost_secret',
	);

	const URL_KEYS = array(
		'urldlhost',
		'urlfunction_movie',
		'urlfunction_series',
		'urlfunction_trailer',
		'urlfunction_leech',
	);

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting(
			'fsp_options_group',
			'fsp_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$clean = array();
		foreach ( self::ALL_KEYS as $k ) {
			$raw = isset( $input[ $k ] ) ? wp_unslash( $input[ $k ] ) : '';
			if ( in_array( $k, self::URL_KEYS, true ) ) {
				$clean[ $k ] = esc_url_raw( $raw );
			} elseif ( 'dlhost_secret' === $k ) {
				$clean[ $k ] = sanitize_text_field( $raw );
			} elseif ( 0 === strpos( $k, 'cat_' ) ) {
				$parts       = array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) );
				$clean[ $k ] = implode( ',', $parts );
			} elseif ( 'urlsite' === $k ) {
				$clean[ $k ] = sanitize_text_field( preg_replace( '#^https?://#i', '', $raw ) );
			} else {
				$clean[ $k ] = sanitize_text_field( $raw );
			}
		}
		return $clean;
	}

	private function row( $opts, $key, $label, $placeholder = '', $type = 'text' ) {
		$val = isset( $opts[ $key ] ) ? esc_attr( $opts[ $key ] ) : '';
		echo '<tr><th scope="row"><label for="fsp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="fsp-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" class="regular-text" name="fsp_options[' . esc_attr( $key ) . ']" value="' . $val . '" placeholder="' . esc_attr( $placeholder ) . '" dir="ltr" />';
		echo '</td></tr>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-publisher' ) );
		}
		$opts = get_option( 'fsp_options', array() );
		?>
		<div class="fsp-wrap" dir="rtl" data-theme="dark">
			<div class="fsp-header">
				<div class="fsp-header-icon" aria-hidden="true">🎬</div>
				<div class="fsp-header-text">
					<h1><?php esc_html_e( 'Plugin settings', 'film-publisher' ); ?></h1>
					<p><?php esc_html_e( 'Download host, categories, and site identity for movies and series.', 'film-publisher' ); ?></p>
				</div>
				<button type="button" class="fsp-theme-toggle" id="fsp-theme-toggle" title="<?php esc_attr_e( 'Toggle light/dark', 'film-publisher' ); ?>">
					<span class="fsp-theme-icon-light">☀️</span>
					<span class="fsp-theme-icon-dark">🌙</span>
				</button>
			</div>
			<?php $this->tabs( 'fsp-settings' ); ?>
			<?php if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="fsp-notice success" role="status"><?php esc_html_e( 'Settings saved.', 'film-publisher' ); ?></div>
			<?php } ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'fsp_options_group' ); ?>
				<div class="fsp-card">
					<div class="fsp-card-title"><?php esc_html_e( 'Download host', 'film-publisher' ); ?></div>
					<table class="fsp-settings-table">
						<?php
						$this->row( $opts, 'urldlhost', __( 'Download host URL', 'film-publisher' ), 'https://dl.example.ir' );
						$this->row( $opts, 'urlfunction_movie', __( 'curl-movie.php URL', 'film-publisher' ), 'https://dl.example.ir/curl-movie.php' );
						$this->row( $opts, 'urlfunction_series', __( 'curl-series.php URL', 'film-publisher' ), 'https://dl.example.ir/curl-series.php' );
						$this->row( $opts, 'urlfunction_trailer', __( 'curl-trailer.php URL', 'film-publisher' ), 'https://dl.example.ir/curl-trailer.php' );
						$this->row( $opts, 'urlfunction_leech', __( 'curl-leech.php URL', 'film-publisher' ), 'https://dl.example.ir/curl-leech.php' );
						$this->row( $opts, 'dlhost_secret', __( 'Shared secret (X-FSP-Key)', 'film-publisher' ), '', 'password' );
						?>
					</table>
				</div>
				<div class="fsp-card">
					<div class="fsp-card-title"><?php esc_html_e( 'Site identity', 'film-publisher' ); ?></div>
					<table class="fsp-settings-table">
						<?php
						$this->row( $opts, 'namesite', __( 'Site name (Persian)', 'film-publisher' ) );
						$this->row( $opts, 'namesite2', __( 'Site name (English)', 'film-publisher' ) );
						$this->row( $opts, 'urlsite', __( 'Site domain (no https://)', 'film-publisher' ), 'example.ir' );
						?>
					</table>
				</div>
				<div class="fsp-card">
					<div class="fsp-card-title"><?php esc_html_e( 'Categories (IDs)', 'film-publisher' ); ?></div>
					<table class="fsp-settings-table">
						<?php
						$this->row( $opts, 'cat_movie', __( 'Movie category IDs', 'film-publisher' ), '10' );
						$this->row( $opts, 'cat_series', __( 'Series category IDs', 'film-publisher' ), '11' );
						$this->row( $opts, 'cat_trailer', __( 'Trailer category IDs', 'film-publisher' ), '12' );
						?>
					</table>
				</div>
				<div class="fsp-submit-row">
					<?php submit_button( __( 'Save settings', 'film-publisher' ), 'primary', 'submit', false, array( 'class' => 'fsp-btn fsp-btn-primary' ) ); ?>
				</div>
			</form>
			<div class="fsp-card">
				<div class="fsp-card-title"><?php esc_html_e( 'About', 'film-publisher' ); ?></div>
				<p class="fsp-about-text">
					<?php esc_html_e( 'Author and design:', 'film-publisher' ); ?>
					<a href="https://etehadwp.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Etehad WordPress development team', 'film-publisher' ); ?></a>
					— <a href="https://etehadwp.com" target="_blank" rel="noopener noreferrer">etehadwp.com</a>
				</p>
			</div>
		</div>
		<?php
	}

	public function tabs( $active ) {
		$tabs = array(
			'fsp-dashboard' => __( 'Movie', 'film-publisher' ),
			'fsp-series'    => __( 'Series', 'film-publisher' ),
			'fsp-trailer'   => __( 'Trailer', 'film-publisher' ),
			'fsp-leech'     => __( 'Leech', 'film-publisher' ),
			'fsp-settings'  => __( 'Settings', 'film-publisher' ),
		);
		echo '<nav class="fsp-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$cls = ( $slug === $active ) ? 'fsp-tab active' : 'fsp-tab';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	public static function get( $key, $default = '' ) {
		$options = get_option( 'fsp_options', array() );
		return ( isset( $options[ $key ] ) && $options[ $key ] !== '' ) ? $options[ $key ] : $default;
	}
}
