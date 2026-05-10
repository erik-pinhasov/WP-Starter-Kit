<?php
/**
 * WPSK Login URL — Hide wp-login.php behind a custom slug.
 *
 * CRITICAL FIX: handle_request() now runs at `wp_loaded` (not `plugins_loaded`)
 * so WordPress is fully bootstrapped before we require wp-login.php.
 * The old code caused "Undefined constant AUTOSAVE_INTERVAL" because
 * wp-login.php was loaded before wp-settings.php finished defining constants.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Login_URL extends WPSK_Module {

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
					__( 'Your custom login URL will be: %s. Leave empty to disable.', 'wpsk-login-url' ),
					'<code>' . trailingslashit( home_url() ) . '<strong>&lt;slug&gt;</strong></code>'
				),
				'default'     => '',
				'placeholder' => 'my-login',
				'importance'  => 'high',
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

	public function get_help_html(): string {
		return '<strong>' . esc_html__( 'How it works:', 'wpsk-login-url' ) . '</strong> '
			. esc_html__( 'Choose a secret slug (e.g. "my-login"). Your login page will only be accessible at that URL. Anyone trying wp-login.php directly will be redirected. Make sure to bookmark your new login URL!', 'wpsk-login-url' )
			. '<br><br><strong>⚠️ ' . esc_html__( 'Important:', 'wpsk-login-url' ) . '</strong> '
			. esc_html__( 'If you forget your custom slug, you can deactivate this plugin via FTP by renaming the plugin folder, then access wp-login.php normally.', 'wpsk-login-url' );
	}

	protected function init(): void {
		$this->slug = $this->get_slug();
		if ( '' === $this->slug ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// FIX: Use wp_loaded (not plugins_loaded) so WordPress is fully bootstrapped
		// before we attempt to require wp-login.php. This prevents the
		// "Undefined constant AUTOSAVE_INTERVAL" fatal error.
		add_action( 'wp_loaded', [ $this, 'handle_request' ], 9999 );

		// URL filters — these are safe at any hook.
		add_filter( 'login_url',         [ $this, 'filter_login_url' ], 10, 3 );
		add_filter( 'site_url',          [ $this, 'filter_site_url' ], 10, 4 );
		add_filter( 'network_site_url',  [ $this, 'filter_network_url' ], 10, 3 );
		add_filter( 'wp_redirect',       [ $this, 'filter_redirect' ], 10, 2 );
		add_filter( 'register_url',      [ $this, 'filter_generic_url' ] );
		add_filter( 'lostpassword_url',  [ $this, 'filter_lostpassword_url' ], 10, 2 );
		add_filter( 'logout_url',        [ $this, 'filter_logout_url' ], 10, 2 );
	}

	/* ── Request handler ────────────────────────────────────── */

	public function handle_request(): void {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ) ?? '', '/' );

		// Custom slug → load wp-login.php with full WordPress environment.
		if ( $path === $this->slug || strpos( $path, $this->slug . '/' ) === 0 ) {
			// Mark that we're handling login through the custom slug.
			if ( ! defined( 'WPSK_LOGIN_SLUG_ACTIVE' ) ) {
				define( 'WPSK_LOGIN_SLUG_ACTIVE', true );
			}

			// Ensure all constants wp-login.php needs are defined.
			if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
				define( 'AUTOSAVE_INTERVAL', 60 );
			}

			// Set up globals that wp-login.php expects.
			global $error, $interim_login, $action, $user_login;

			// Parse the action from query string.
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';

			@require_once ABSPATH . 'wp-login.php';
			exit;
		}

		// Block direct wp-login.php access (except for POST actions from our slug
		// and AJAX/CLI requests).
		if ( $this->is_login_request( $path ) ) {
			// Allow POST requests that come from a legitimate login session
			// (e.g. form submissions, postpass, etc.)
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST ) ) {
				// Check if this POST was initiated from our custom slug
				$referer = wp_get_referer();
				if ( $referer && strpos( $referer, '/' . $this->slug ) !== false ) {
					// This is a legitimate form submission from our custom URL.
					if ( ! defined( 'WPSK_LOGIN_SLUG_ACTIVE' ) ) {
						define( 'WPSK_LOGIN_SLUG_ACTIVE', true );
					}
					return; // Let WordPress handle it normally.
				}
			}

			$this->block_request();
		}
	}

	/**
	 * Check if the current request is for wp-login.php.
	 */
	private function is_login_request( string $path ): bool {
		// Direct file access.
		if ( 'wp-login.php' === basename( $path ) ) {
			return true;
		}
		// Check SCRIPT_FILENAME for nginx/litespeed setups.
		$script = $_SERVER['SCRIPT_FILENAME'] ?? '';
		if ( $script && 'wp-login.php' === basename( $script ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Redirect blocked login attempts.
	 */
	private function block_request(): void {
		$redirect_to = $this->get_option( 'redirect_to' );

		if ( 'home' === $redirect_to ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		// Default: 404.
		global $wp_query;
		if ( $wp_query ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			$template = get_404_template();
			if ( $template ) {
				include $template;
			}
			exit;
		}

		// Fallback if $wp_query isn't available yet.
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	/* ── URL filters ────────────────────────────────────────── */

	private function get_slug(): string {
		$slug = $this->get_option( 'slug' );
		return sanitize_title( (string) $slug );
	}

	private function new_login_url( string $scheme = '' ): string {
		$url = home_url( '/' . $this->slug . '/', $scheme );
		return $url;
	}

	public function filter_login_url( string $login_url, string $redirect = '', $force_reauth = false ): string {
		$url = $this->new_login_url();
		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}
		return $url;
	}

	public function filter_site_url( string $url, string $path = '', $scheme = null, $blog_id = null ): string {
		return $this->replace_login_path( $url );
	}

	public function filter_network_url( string $url, string $path = '', $scheme = null ): string {
		return $this->replace_login_path( $url );
	}

	public function filter_redirect( string $location, int $status = 302 ): string {
		return $this->replace_login_path( $location );
	}

	public function filter_generic_url( string $url ): string {
		return $this->replace_login_path( $url );
	}

	public function filter_lostpassword_url( string $url, string $redirect = '' ): string {
		$url = $this->replace_login_path( $url );
		return $url;
	}

	public function filter_logout_url( string $url, string $redirect = '' ): string {
		return $this->replace_login_path( $url );
	}

	/**
	 * Replace wp-login.php in a URL with the custom slug.
	 */
	private function replace_login_path( string $url ): string {
		if ( strpos( (string) $url, 'wp-login.php' ) !== false ) {
			$url = str_replace( 'wp-login.php', $this->slug . '/', (string) $url );
		}
		return $url;
	}
}
