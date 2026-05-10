<?php
/**
 * WPSK Turnstile — Cloudflare Turnstile CAPTCHA integration.
 *
 * Fixes:
 * - Turnstile widget container now has min-height and no overflow:hidden
 *   to prevent the banner from being clipped.
 * - Added domain mismatch guidance for localhost/staging environments.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Turnstile extends WPSK_Module {

	public function get_id(): string {
		return 'turnstile';
	}

	public function get_name(): string {
		return __( 'Cloudflare Turnstile', 'wpsk-turnstile' );
	}

	public function get_description(): string {
		return __( 'Protect forms with Cloudflare Turnstile — a privacy-friendly, invisible CAPTCHA alternative.', 'wpsk-turnstile' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'    => 'help_block',
				'label' => '',
				'type'  => 'html',
				'html'  => '', // Help is shown via get_help_html() instead.
				'default' => '',
			],
			[
				'id'          => 'site_key',
				'label'       => __( 'Site Key', 'wpsk-turnstile' ),
				'type'        => 'text',
				'description' => __( 'The public site key from your Cloudflare Turnstile widget.', 'wpsk-turnstile' ),
				'default'     => '',
				'placeholder' => '0x4AAAAAAA...',
				'importance'  => 'high',
			],
			[
				'id'          => 'secret_key',
				'label'       => __( 'Secret Key', 'wpsk-turnstile' ),
				'type'        => 'password',
				'description' => __( 'The secret key used for server-side verification. Never share this publicly.', 'wpsk-turnstile' ),
				'default'     => '',
				'placeholder' => '0x4AAAAAAA...',
				'importance'  => 'high',
			],
			[
				'id'          => 'theme',
				'label'       => __( 'Widget Theme', 'wpsk-turnstile' ),
				'type'        => 'select',
				'default'     => 'auto',
				'options'     => [
					'auto'  => __( 'Auto (match system)', 'wpsk-turnstile' ),
					'light' => __( 'Light', 'wpsk-turnstile' ),
					'dark'  => __( 'Dark', 'wpsk-turnstile' ),
				],
			],
			[
				'id'          => 'protected_forms',
				'label'       => __( 'Protected Forms', 'wpsk-turnstile' ),
				'type'        => 'checkboxes',
				'default'     => [ 'wp_login', 'wp_register', 'wp_comment' ],
				'options'     => [
					'wp_login'     => __( 'WordPress Login', 'wpsk-turnstile' ),
					'wp_register'  => __( 'WordPress Registration', 'wpsk-turnstile' ),
					'wp_comment'   => __( 'Comment Form', 'wpsk-turnstile' ),
					'wp_lostpw'    => __( 'Lost Password', 'wpsk-turnstile' ),
					'wc_login'     => __( 'WooCommerce Login', 'wpsk-turnstile' ),
					'wc_register'  => __( 'WooCommerce Registration', 'wpsk-turnstile' ),
					'wc_checkout'  => __( 'WooCommerce Checkout', 'wpsk-turnstile' ),
					'wc_pay'       => __( 'WooCommerce Pay for Order', 'wpsk-turnstile' ),
				],
				'importance' => 'medium',
			],
		];
	}

	public function get_help_html(): string {
		$domain  = wp_parse_url( home_url(), PHP_URL_HOST ) ?? 'unknown';
		$is_local = in_array( $domain, [ 'localhost', '127.0.0.1' ], true )
			|| preg_match( '/\.(local|test|dev|localhost)$/i', $domain );

		$html = '<strong>🔑 ' . esc_html__( 'Getting your keys:', 'wpsk-turnstile' ) . '</strong><br>'
			. sprintf(
				__( '1. Go to <a href="%s" target="_blank">Cloudflare Dashboard → Turnstile</a><br>2. Click "Add Widget"<br>3. Enter your domain name and choose widget type<br>4. Copy the Site Key and Secret Key here', 'wpsk-turnstile' ),
				'https://dash.cloudflare.com/?to=/:account/turnstile'
			);

		if ( $is_local ) {
			$html .= '<br><br><strong style="color:#9b1c1c">⚠️ ' . esc_html__( 'Local development detected!', 'wpsk-turnstile' ) . '</strong><br>'
				. sprintf(
					esc_html__( 'Your site domain is %1$s. Turnstile keys are domain-specific — keys registered for a production domain will NOT work here. You need to create a separate Turnstile widget in Cloudflare with %1$s as the allowed domain, or use Cloudflare\'s test keys:', 'wpsk-turnstile' ),
					'<code>' . esc_html( $domain ) . '</code>'
				)
				. '<br><code>' . esc_html__( 'Site Key: 1x00000000000000000000AA', 'wpsk-turnstile' ) . '</code>'
				. '<br><code>' . esc_html__( 'Secret Key: 1x0000000000000000000000000000000AA', 'wpsk-turnstile' ) . '</code>'
				. '<br>' . esc_html__( '(These test keys always pass verification.)', 'wpsk-turnstile' );
		}

		return $html;
	}

	protected function init(): void {
		$site_key   = $this->get_option( 'site_key' );
		$secret_key = $this->get_option( 'secret_key' );

		if ( empty( $site_key ) || empty( $secret_key ) ) {
			add_action( 'admin_notices', [ $this, 'notice_missing_keys' ] );
			return;
		}

		// Frontend: enqueue Turnstile API + render widgets.
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		$this->register_form_hooks();
	}

	/* ── Frontend scripts ───────────────────────────────────── */

	public function enqueue_scripts(): void {
		$site_key = $this->get_option( 'site_key' );
		$theme    = $this->get_option( 'theme' );

		wp_enqueue_script(
			'wpsk-turnstile-api',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=wpskInitTurnstile&render=explicit',
			[],
			null,
			true
		);

		// Inline JS to render Turnstile widgets.
		// FIX: Widget container CSS ensures the banner is never clipped.
		$inline_js = sprintf(
			'function wpskInitTurnstile(){' .
			'document.querySelectorAll(".wpsk-turnstile-widget").forEach(function(el){' .
			'if(el.dataset.rendered)return;' .
			'el.dataset.rendered="1";' .
			'turnstile.render(el,{sitekey:"%s",theme:"%s",callback:function(t){' .
			'var h=el.querySelector("input[name=cf-turnstile-response]");' .
			'if(h)h.value=t;' .
			'}});' .
			'});' .
			'}',
			esc_js( $site_key ),
			esc_js( $theme ?: 'auto' )
		);
		wp_add_inline_script( 'wpsk-turnstile-api', $inline_js, 'before' );

		// CSS to fix the banner clipping issue.
		wp_add_inline_style( 'wp-block-library', '
			.wpsk-turnstile-widget {
				min-height: 65px;
				overflow: visible !important;
				margin: 10px 0;
				clear: both;
			}
			.wpsk-turnstile-widget iframe {
				max-width: 100%;
				overflow: visible !important;
			}
		' );
	}

	/**
	 * Output the Turnstile widget HTML for a form.
	 */
	public function render_widget(): void {
		echo '<div class="wpsk-turnstile-widget" style="overflow:visible !important; min-height:65px; margin:10px 0;"></div>';
		echo '<input type="hidden" name="cf-turnstile-response" value="" />';
	}

	/* ── Server-side verification ───────────────────────────── */

	public function verify_token(): bool {
		$token = sanitize_text_field( $_POST['cf-turnstile-response'] ?? '' );
		if ( empty( $token ) ) {
			return false;
		}

		$secret = $this->get_option( 'secret_key' );
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $body['success'] );
	}

	private function get_error_message(): string {
		return __( 'CAPTCHA verification failed. Please try again.', 'wpsk-turnstile' );
	}

	/* ── Form hooks ─────────────────────────────────────────── */

	private function register_form_hooks(): void {
		$forms = $this->get_protected_forms();
		if ( empty( $forms ) ) {
			return;
		}

		// WordPress Login.
		if ( in_array( 'wp_login', $forms, true ) ) {
			add_action( 'login_form', [ $this, 'render_widget' ] );
			add_filter( 'authenticate', [ $this, 'validate_login' ], 30, 3 );
		}

		// WordPress Registration.
		if ( in_array( 'wp_register', $forms, true ) ) {
			add_action( 'register_form', [ $this, 'render_widget' ] );
			add_filter( 'registration_errors', [ $this, 'validate_registration' ], 10, 3 );
		}

		// Comment Form.
		if ( in_array( 'wp_comment', $forms, true ) ) {
			add_action( 'comment_form_after_fields', [ $this, 'render_widget' ] );
			add_action( 'comment_form_logged_in_after', [ $this, 'render_widget' ] );
			add_filter( 'preprocess_comment', [ $this, 'validate_comment' ] );
		}

		// Lost Password.
		if ( in_array( 'wp_lostpw', $forms, true ) ) {
			add_action( 'lostpassword_form', [ $this, 'render_widget' ] );
			add_action( 'lostpassword_post', [ $this, 'validate_lostpassword' ] );
		}

		// WooCommerce forms.
		if ( in_array( 'wc_login', $forms, true ) ) {
			add_action( 'woocommerce_login_form', [ $this, 'render_widget' ] );
		}
		if ( in_array( 'wc_register', $forms, true ) ) {
			add_action( 'woocommerce_register_form', [ $this, 'render_widget' ] );
		}
		if ( in_array( 'wc_checkout', $forms, true ) ) {
			add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'render_widget' ] );
		}

		// WooCommerce validation (shared for login/register/checkout).
		if ( array_intersect( [ 'wc_login', 'wc_register' ], $forms ) ) {
			add_filter( 'woocommerce_process_login_errors', [ $this, 'validate_wc_form' ], 10, 3 );
			add_filter( 'woocommerce_process_registration_errors', [ $this, 'validate_wc_form' ], 10, 3 );
		}
		if ( in_array( 'wc_checkout', $forms, true ) ) {
			add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
				if ( ! $this->verify_token() ) {
					$errors->add( 'turnstile', esc_html( $this->get_error_message() ) );
				}
			}, 10, 2 );
		}
	}

	private function get_protected_forms(): array {
		$forms = $this->get_option( 'protected_forms' );
		return is_array( $forms ) ? $forms : [];
	}

	/* ── Validation callbacks ───────────────────────────────── */

	public function validate_login( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}
		if ( ! $this->verify_token() ) {
			return new \WP_Error( 'turnstile_failed', esc_html( $this->get_error_message() ) );
		}
		return $user;
	}

	public function validate_registration( $errors, $sanitized_login, $user_email ) {
		if ( ! $this->verify_token() ) {
			$errors->add( 'turnstile_failed', esc_html( $this->get_error_message() ) );
		}
		return $errors;
	}

	public function validate_comment( $commentdata ) {
		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}
		if ( ! $this->verify_token() ) {
			wp_die( esc_html( $this->get_error_message() ), 403 );
		}
		return $commentdata;
	}

	public function validate_lostpassword( $errors ): void {
		if ( ! $this->verify_token() ) {
			$errors->add( 'turnstile_failed', esc_html( $this->get_error_message() ) );
		}
	}

	public function validate_wc_form( $validation_error, $username = '', $email = '' ) {
		if ( ! $this->verify_token() ) {
			$validation_error->add( 'turnstile_failed', esc_html( $this->get_error_message() ) );
		}
		return $validation_error;
	}

	public function notice_missing_keys(): void {
		$page_url = WPSK_Core::instance()->is_suite()
			? admin_url( 'admin.php?page=wpsk-turnstile' )
			: admin_url( 'options-general.php?page=wpsk-turnstile' );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Cloudflare Turnstile: site key and secret key are required.', 'wpsk-turnstile' ),
			esc_url( $page_url ),
			esc_html__( 'Configure now →', 'wpsk-turnstile' )
		);
	}
}
