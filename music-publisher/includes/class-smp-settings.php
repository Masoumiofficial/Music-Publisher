<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMP_Settings {

	const ALL_KEYS = array(
		'urldlhost',
		'urlfunction_music',
		'urlfunction_remix',
		'urlfunction_video',
		'urlfunction_album',
		'urlfunction_nohe',
		'urlfunction_leech',
		'namesite',
		'namesite2',
		'urlsite',
		'cat_music',
		'cat_remix',
		'cat_nohe',
		'cat_video',
		'cat_album',
		'dlhost_secret',
	);

	const URL_KEYS = array(
		'urldlhost',
		'urlfunction_music',
		'urlfunction_remix',
		'urlfunction_video',
		'urlfunction_album',
		'urlfunction_nohe',
		'urlfunction_leech',
	);

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting(
			'smp_options_group',
			'smp_options',
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
				$parts = array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) );
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
		echo '<tr><th scope="row"><label for="smp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="smp-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" class="regular-text" name="smp_options[' . esc_attr( $key ) . ']" value="' . $val . '" placeholder="' . esc_attr( $placeholder ) . '" dir="ltr" />';
		echo '</td></tr>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sajad-music-publisher' ) );
		}
		$opts = get_option( 'smp_options', array() );
		?>
		<div class="smp-wrap" dir="rtl" data-theme="dark">
			<div class="smp-header">
				<div class="smp-header-icon" aria-hidden="true">⚙️</div>
				<div class="smp-header-text">
					<h1><?php esc_html_e( 'Plugin settings', 'sajad-music-publisher' ); ?></h1>
					<p><?php esc_html_e( 'Download host, categories, and site identity.', 'sajad-music-publisher' ); ?></p>
				</div>
				<button type="button" class="smp-theme-toggle" id="smp-theme-toggle" title="<?php esc_attr_e( 'Toggle light/dark', 'sajad-music-publisher' ); ?>">
					<span class="smp-theme-icon-light" aria-hidden="true">☀️</span>
					<span class="smp-theme-icon-dark" aria-hidden="true">🌙</span>
				</button>
			</div>

			<?php
			$tabs = array(
				'smp-dashboard' => __( 'Single', 'sajad-music-publisher' ),
				'smp-remix'     => __( 'Remix', 'sajad-music-publisher' ),
				'smp-nohe'      => __( 'Nohe', 'sajad-music-publisher' ),
				'smp-video'     => __( 'Music video', 'sajad-music-publisher' ),
				'smp-album'     => __( 'Album', 'sajad-music-publisher' ),
				'smp-leech'     => __( 'Leech', 'sajad-music-publisher' ),
				'smp-settings'  => __( 'Settings', 'sajad-music-publisher' ),
			);
			echo '<nav class="smp-tabs" aria-label="' . esc_attr__( 'Publisher sections', 'sajad-music-publisher' ) . '">';
			foreach ( $tabs as $slug => $label ) {
				$cls = ( 'smp-settings' === $slug ) ? 'smp-tab active' : 'smp-tab';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '" class="' . esc_attr( $cls ) . '"><span class="tab-dot"></span>' . esc_html( $label ) . '</a>';
			}
			echo '</nav>';

			if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<div class="smp-notice success" role="status">' . esc_html__( 'Settings saved.', 'sajad-music-publisher' ) . '</div>';
			}
			?>

			<form method="post" action="options.php">
				<?php settings_fields( 'smp_options_group' ); ?>

				<div class="smp-card">
					<div class="smp-card-title"><?php esc_html_e( 'Download host', 'sajad-music-publisher' ); ?></div>
					<table class="smp-settings-table">
						<?php
						$this->row( $opts, 'urldlhost', __( 'Download host URL', 'sajad-music-publisher' ), 'https://dl.example.ir' );
						$this->row( $opts, 'urlfunction_music', __( 'curl-music.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-music.php' );
						$this->row( $opts, 'urlfunction_remix', __( 'curl-remix.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-remix.php' );
						$this->row( $opts, 'urlfunction_video', __( 'curl-video.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-video.php' );
						$this->row( $opts, 'urlfunction_album', __( 'curl-album.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-album.php' );
						$this->row( $opts, 'urlfunction_nohe', __( 'curl-nohe.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-nohe.php' );
						$this->row( $opts, 'urlfunction_leech', __( 'curl-leech.php URL', 'sajad-music-publisher' ), 'https://dl.example.ir/curl-leech.php' );
						$this->row( $opts, 'dlhost_secret', __( 'Shared secret (X-SMP-Key)', 'sajad-music-publisher' ), '', 'password' );
						?>
					</table>
					<p class="smp-hint"><?php esc_html_e( 'Only the URLs saved here are used as remote endpoints. Set the same shared secret in hostdl/config.php on the download server.', 'sajad-music-publisher' ); ?></p>
				</div>

				<div class="smp-card">
					<div class="smp-card-title"><?php esc_html_e( 'Site identity', 'sajad-music-publisher' ); ?></div>
					<table class="smp-settings-table">
						<?php
						$this->row( $opts, 'namesite', __( 'Site name (Persian)', 'sajad-music-publisher' ) );
						$this->row( $opts, 'namesite2', __( 'Site name (English)', 'sajad-music-publisher' ) );
						$this->row( $opts, 'urlsite', __( 'Site domain (no https://)', 'sajad-music-publisher' ), 'example.ir' );
						?>
					</table>
				</div>

				<div class="smp-card">
					<div class="smp-card-title"><?php esc_html_e( 'Categories (IDs)', 'sajad-music-publisher' ); ?></div>
					<table class="smp-settings-table">
						<?php
						$this->row( $opts, 'cat_music', __( 'Single category IDs', 'sajad-music-publisher' ), '10,12' );
						$this->row( $opts, 'cat_remix', __( 'Remix category IDs', 'sajad-music-publisher' ), '10,13' );
						$this->row( $opts, 'cat_nohe', __( 'Nohe category IDs', 'sajad-music-publisher' ), '10,14' );
						$this->row( $opts, 'cat_video', __( 'Music video category IDs', 'sajad-music-publisher' ), '10,15' );
						$this->row( $opts, 'cat_album', __( 'Album category IDs', 'sajad-music-publisher' ), '2' );
						?>
					</table>
					<p class="smp-hint"><?php esc_html_e( 'Copy category IDs from Posts → Categories. Separate multiple IDs with commas.', 'sajad-music-publisher' ); ?></p>
				</div>

				<div class="smp-submit-row">
					<?php submit_button( __( 'Save settings', 'sajad-music-publisher' ), 'primary', 'submit', false, array( 'class' => 'smp-btn smp-btn-primary' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	public static function get( $key, $default = '' ) {
		$options = get_option( 'smp_options', array() );
		return ( isset( $options[ $key ] ) && $options[ $key ] !== '' ) ? $options[ $key ] : $default;
	}
}
