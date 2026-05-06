<?php
/**
 * WPSK Turnstile — Cloudflare Turnstile CAPTCHA for WordPress.
 *
 * @package    WPStarterKit
 * @subpackage Modules\Turnstile
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Turnstile extends WPSK_Module {

	/* ----------------------------------------------------------
	 * Module identity
	 * ---------------------------------------------------------- */

	public function get_id(): string {
		return 'turnstile';
	}

	public function get_name(): string {
		return __( 'Cloudflare Turnstile', 'wpsk-turnstile' );
	}

	public function get_description(): string {
		return __( 'Protect login, registration, checkout, and other forms with Cloudflare Turnstile CAPTCHA.', 'wpsk-turnstile' );
	}

	/* ----------------------------------------------------------
	 * Settings definition
	 * ---------------------------------------------------------- */

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'site_key',
				'label'       => __( 'Site Key', 'wpsk-turnstile' ),
				'type'        => 'text',
				'description' => __( 'Your Cloudflare Turnstile site key. Get it from the Cloudflare dashboard → Turnstile.', 'wpsk-turnstile' ),
				'default'     => '',
				'placeholder' => '0x4AAAAAAA...',
			],
			[
				'id'          => 'secret_key',
				'label'       => __( 'Secret Key', 'wpsk-turnstile' ),
				'type'        => 'password',
				'description' => __( 'Your Cloudflare Turnstile secret key. Never share this publicly.', 'wpsk-turnstile' ),
				'default'     => '',
				'placeholder' => '0x4AAAAAAA...',
			],
			[
				'id'          => 'theme',
				'label'       => __( 'Widget Theme', 'wpsk-turnstile' ),
				'type'        => 'select',
				'description' => __( 'Appearance of the Turnstile widget.', 'wpsk-turnstile' ),
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
				'description' => '',
				'default'     => [ 'wp_login', 'wp_register', 'wp_lost_password' ],
				'options'     => [
					'wp_login'         => __( 'WordPress Login', 'wpsk-turnstile' ),
					'wp_register'      => __( 'WordPress Registration', 'wpsk-turnstile' ),
					'wp_lost_password' => __( 'WordPress Lost Password', 'wpsk-turnstile' ),
					'wc_login'         => __( 'WooCommerce Login', 'wpsk-turnstile' ),
					'wc_register'      => __( 'WooCommerce Registration', 'wpsk-turnstile' ),
					'wc_checkout'      => __( 'WooCommerce Checkout', 'wpsk-turnstile' ),
					'wc_pay'           => __( 'WooCommerce Pay for Order', 'wpsk-turnstile' ),
				],
			],
			[
				'id'          => 'error_message',
				'label'       => __( 'Error Message', 'wpsk-turnstile' ),
				'type'        => 'text',
				'description' => __( 'Message shown when verification fails. Leave empty for the default.', 'wpsk-turnstile' ),
				'default'     => '',
				'placeholder' => __( 'Security verification failed. Please try again.', 'wpsk-turnstile' ),
			],
		];
	}

	/* ----------------------------------------------------------
	 * Init — hook into WP
	 * ---------------------------------------------------------- */

	protected function init(): void {
		$site_key   = $this->get_option( 'site_key' );
		$secret_key = $this->get_option( 'secret_key' );

		// No keys configured — nothing to do.
		if ( '' === $site_key || '' === $secret_key ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', [ $this, 'notice_missing_keys' ] );
			}
			return;
		}

		$forms = $this->get_protected_forms();
		if ( empty( $forms ) ) {
			return;
		}

		// Widget rendering hooks.
		$render_map = [
			'wp_login'         => [ 'login_form' ],
			'wp_register'      => [ 'register_form' ],
			'wp_lost_password' => [ 'lostpassword_form' ],
			'wc_login'         => [ 'woocommerce_login_form' ],
			'wc_register'      => [ 'woocommerce_register_form' ],
			'wc_checkout'      => [ 'woocommerce_review_order_before_submit' ],
			'wc_pay'           => [ 'woocommerce_pay_order_before_submit' ],
		];

		foreach ( $forms as $form_id ) {
			if ( isset( $render_map[ $form_id ] ) ) {
				foreach ( $render_map[ $form_id ] as $hook ) {
					add_action( $hook, [ $this, 'render_widget' ] );
				}
			}
		}

		// Enqueue scripts.
		$wp_forms = array_intersect( $forms, [ 'wp_login', 'wp_register', 'wp_lost_password' ] );
		if ( ! empty( $wp_forms ) ) {
			add_action( 'login_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		$frontend_forms = array_diff( $forms, [ 'wp_login', 'wp_register', 'wp_lost_password' ] );
		if ( ! empty( $frontend_forms ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		// Verification hooks.
		$this->register_verification_hooks( $forms );
	}

	/* ----------------------------------------------------------
	 * Widget rendering
	 * ---------------------------------------------------------- */

	/**
	 * Output the Turnstile widget container.
	 */
	public function render_widget(): void {
		echo '<div class="wpsk-turnstile-widget" style="margin:15px 0"></div>';
	}

	/**
	 * Enqueue the Turnstile API script and our init logic.
	 */
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

		// Prevent caching/optimization plugins from touching this script.
		add_filter( 'script_loader_tag', function ( string $tag, string $handle ) {
			if ( 'wpsk-turnstile-api' === $handle ) {
				return str_replace( ' src', ' data-no-optimize="1" data-no-minify="1" src', $tag );
			}
			return $tag;
		}, 10, 2 );

		wp_add_inline_script(
			'wpsk-turnstile-api',
			sprintf(
				'window.wpskInitTurnstile=function(){' .
				'document.querySelectorAll(".wpsk-turnstile-widget").forEach(function(el){' .
				'if(!el.hasAttribute("data-rendered")){' .
				'turnstile.render(el,{sitekey:%s,theme:%s});' .
				'el.setAttribute("data-rendered","true");}});};',
				wp_json_encode( $site_key ),
				wp_json_encode( $theme )
			),
			'before'
		);
	}

	/* ----------------------------------------------------------
	 * Verification
	 * ---------------------------------------------------------- */

	/**
	 * Verify the Turnstile response token against the Cloudflare API.
	 */
	public function verify_token( string $token = '' ): bool {
		$secret_key = $this->get_option( 'secret_key' );
		if ( '' === $secret_key ) {
			return false;
		}

		if ( '' === $token ) {
			$token = isset( $_POST['cf-turnstile-response'] )
				? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) )
				: '';
		}

		if ( '' === $token ) {
			return false;
		}

		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => $secret_key,
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] )
					? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
					: '',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		return isset( $result['success'] ) && true === $result['success'];
	}

	/**
	 * Get the localised error message.
	 */
	private function get_error_message(): string {
		$custom = $this->get_option( 'error_message' );
		if ( '' !== $custom ) {
			return $custom;
		}
		return __( 'Security verification failed. Please try again.', 'wpsk-turnstile' );
	}

	/**
	 * Register all verification hooks for the enabled forms.
	 */
	private function register_verification_hooks( array $forms ): void {

		// -- WordPress core forms --

		if ( in_array( 'wp_login', $forms, true ) ) {
			add_filter( 'authenticate', function ( $user ) {
				if ( isset( $_POST['log'] ) && ! $this->verify_token() ) {
					return new \WP_Error( 'wpsk_turnstile', '<strong>' . esc_html__( 'Error:', 'wpsk-turnstile' ) . '</strong> ' . esc_html( $this->get_error_message() ) );
				}
				return $user;
			}, 21 );
		}

		if ( in_array( 'wp_register', $forms, true ) ) {
			add_filter( 'registration_errors', function ( $errors ) {
				if ( isset( $_POST['user_login'] ) && ! $this->verify_token() ) {
					$errors->add( 'wpsk_turnstile', '<strong>' . esc_html__( 'Error:', 'wpsk-turnstile' ) . '</strong> ' . esc_html( $this->get_error_message() ) );
				}
				return $errors;
			} );
		}

		if ( in_array( 'wp_lost_password', $forms, true ) ) {
			add_action( 'lostpassword_post', function ( $errors ) {
				if ( isset( $_POST['user_login'] ) && ! $this->verify_token() ) {
					$errors->add( 'wpsk_turnstile', '<strong>' . esc_html__( 'Error:', 'wpsk-turnstile' ) . '</strong> ' . esc_html( $this->get_error_message() ) );
				}
			} );
		}

		// -- WooCommerce forms --

		if ( ! class_exists( 'WooCommerce' ) ) {
			return; // WooCommerce hooks only when WC is active.
		}

		if ( in_array( 'wc_login', $forms, true ) ) {
			add_filter( 'woocommerce_process_login_errors', function ( $error ) {
				if ( isset( $_POST['login'] ) && ! $this->verify_token() ) {
					$error->add( 'wpsk_turnstile', esc_html( $this->get_error_message() ) );
				}
				return $error;
			} );
		}

		if ( in_array( 'wc_register', $forms, true ) ) {
			add_filter( 'woocommerce_process_registration_errors', function ( $errors ) {
				if ( isset( $_POST['register'] ) && ! $this->verify_token() ) {
					$errors->add( 'wpsk_turnstile', esc_html( $this->get_error_message() ) );
				}
				return $errors;
			} );
		}

		if ( in_array( 'wc_checkout', $forms, true ) ) {
			add_action( 'woocommerce_checkout_process', function () {
				if ( ! $this->verify_token() ) {
					wc_add_notice( esc_html( $this->get_error_message() ), 'error' );
				}
			} );
		}

		if ( in_array( 'wc_pay', $forms, true ) ) {
			add_action( 'woocommerce_before_pay_action', function () {
				if ( isset( $_POST['woocommerce_pay'] ) && ! $this->verify_token() ) {
					wc_add_notice( esc_html( $this->get_error_message() ), 'error' );
				}
			} );
		}
	}

	/* ----------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------- */

	/**
	 * Get the list of protected form IDs.
	 *
	 * @return string[]
	 */
	private function get_protected_forms(): array {
		$forms = $this->get_option( 'protected_forms' );
		return is_array( $forms ) ? $forms : [];
	}

	/**
	 * Admin notice when keys are not configured.
	 */
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
