<?php
/**
 * WPSK Login URL — Custom login page URL.
 *
 * Hides wp-login.php behind a user-chosen slug and redirects
 * direct access attempts to a configurable destination.
 *
 * @package    WPStarterKit
 * @subpackage Modules\LoginURL
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Login_URL extends WPSK_Module {

	/** @var string|null Cached slug value. */
	private $slug = null;

	public function get_id(): string {
		return 'login-url';
	}

	public function get_name(): string {
		return __( 'Custom Login URL', 'wpsk-login-url' );
	}

	public function get_description(): string {
		return __( 'Hide wp-login.php behind a custom URL to reduce brute-force attacks.', 'wpsk-login-url' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'slug',
				'label'       => __( 'Login Slug', 'wpsk-login-url' ),
				'type'        => 'text',
				'description' => sprintf(
					__( 'Your custom login URL will be: %s', 'wpsk-login-url' ),
					'<code>' . trailingslashit( home_url() ) . '<strong>&lt;slug&gt;</strong></code>'
				),
				'default'     => '',
				'placeholder' => 'my-login',
			],
			[
				'id'      => 'redirect_to',
				'label'   => __( 'Redirect Blocked Attempts To', 'wpsk-login-url' ),
				'type'    => 'select',
				'default' => '404',
				'options' => [
					'404'  => __( '404 Not Found page', 'wpsk-login-url' ),
					'home' => __( 'Homepage', 'wpsk-login-url' ),
				],
				'description' => __( 'Where to send visitors who try to access wp-login.php directly.', 'wpsk-login-url' ),
			],
		];
	}

	protected function init(): void {
		$this->slug = $this->get_slug();
		if ( '' === $this->slug ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'plugins_loaded', [ $this, 'handle_request' ], 9999 );
		add_filter( 'login_url', [ $this, 'filter_login_url' ], 10, 3 );
		add_filter( 'site_url', [ $this, 'filter_site_url' ], 10, 4 );
		add_filter( 'network_site_url', [ $this, 'filter_network_url' ], 10, 3 );
		add_filter( 'wp_redirect', [ $this, 'filter_redirect' ], 10, 2 );
		add_filter( 'register_url', [ $this, 'filter_generic_url' ] );
		add_filter( 'lostpassword_url', [ $this, 'filter_lostpassword_url' ], 10, 2 );
		add_filter( 'logout_url', [ $this, 'filter_logout_url' ], 10, 2 );
	}

	/* ── Request handler ────────────────────────────────────── */

	public function handle_request(): void {
		$path = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

		// Custom slug → load wp-login.php
		if ( $path === $this->slug ) {
			define( 'WPSK_LOGIN_SLUG_ACTIVE', true );
			global $error, $interim_login, $action, $user_login;
			@require_once ABSPATH . 'wp-login.php';
			exit;
		}

		// Block direct wp-login.php access
		if ( 'wp-login.php' === basename( $path ) && ! $this->is_allowed_action() ) {
			$this->redirect_blocked();
		}
	}

	/* ── URL filters ────────────────────────────────────────── */

	public function filter_login_url( $url, $redirect, $force_reauth ) {
		return $this->rewrite( $url );
	}
	public function filter_site_url( $url, $path = '', $scheme = null, $blog_id = null ) {
		return $this->rewrite( $url );
	}
	public function filter_network_url( $url, $path = '', $scheme = null ) {
		return $this->rewrite( $url );
	}
	public function filter_redirect( $location, $status ) {
		return $this->rewrite( $location );
	}
	public function filter_generic_url( $url ) {
		return $this->rewrite( $url );
	}
	public function filter_lostpassword_url( $url, $redirect ) {
		return $this->rewrite( $url );
	}
	public function filter_logout_url( $url, $redirect ) {
		return $this->rewrite( $url );
	}

	/* ── Settings page with current URL display ─────────────── */

	public function render_settings_page(): void {
		$page = 'wpsk_' . $this->get_id();
		$slug = $this->get_slug();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_name() ); ?></h1>
			<p class="description"><?php echo esc_html( $this->get_description() ); ?></p>

			<?php if ( '' !== $slug ) : ?>
			<div class="notice notice-success inline" style="margin:15px 0">
				<p>
					<strong><?php esc_html_e( 'Your login URL:', 'wpsk-login-url' ); ?></strong>
					<code><a href="<?php echo esc_url( home_url( $slug ) ); ?>"><?php echo esc_html( home_url( $slug ) ); ?></a></code>
				</p>
			</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( $page ); do_settings_sections( $page ); submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Recovery', 'wpsk-login-url' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'If you forget your custom login URL, reset via WP-CLI or database:', 'wpsk-login-url' ); ?>
				<br><code>wp option delete wpsk_login-url_slug</code>
			</p>
		</div>
		<?php
	}

	/* ── Helpers ─────────────────────────────────────────────── */

	private function get_slug(): string {
		if ( null !== $this->slug ) {
			return $this->slug;
		}
		return sanitize_title( $this->get_option( 'slug' ) );
	}

	private function is_allowed_action(): bool {
		if ( defined( 'WPSK_LOGIN_SLUG_ACTIVE' ) ) {
			return true;
		}
		$action = $_GET['action'] ?? $_POST['action'] ?? '';
		return in_array( $action, [ 'postpass', 'rp', 'resetpass', 'confirmaction' ], true );
	}

	private function rewrite( string $url ): string {
		if ( '' !== $this->slug && false !== strpos( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', $this->slug, $url );
		}
		return $url;
	}

	private function redirect_blocked(): void {
		if ( 'home' === $this->get_option( 'redirect_to' ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
		status_header( 404 );
		nocache_headers();
		$tpl = get_404_template();
		if ( $tpl ) { include $tpl; } else { wp_die( 'Not Found', 404, [ 'response' => 404 ] ); }
		exit;
	}
}
