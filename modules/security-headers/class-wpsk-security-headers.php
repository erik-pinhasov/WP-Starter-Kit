<?php
/**
 * WPSK Security Headers — HTTP security headers & WordPress hardening.
 *
 * @package    WPStarterKit
 * @subpackage Modules\SecurityHeaders
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Security_Headers extends WPSK_Module {

	/* ----------------------------------------------------------
	 * Module identity
	 * ---------------------------------------------------------- */

	public function get_id(): string {
		return 'security-headers';
	}

	public function get_name(): string {
		return __( 'Security Headers', 'wpsk-security-headers' );
	}

	public function get_description(): string {
		return __( 'Add HTTP security headers (CSP, Permissions-Policy, HSTS) and harden WordPress against common attack vectors.', 'wpsk-security-headers' );
	}

	/* ----------------------------------------------------------
	 * Settings definition
	 * ---------------------------------------------------------- */

	public function get_settings_fields(): array {
		return [

			/* ── HTTP headers ──────────────────────────────── */

			[
				'id'      => 'http_headers',
				'label'   => __( 'HTTP Security Headers', 'wpsk-security-headers' ),
				'type'    => 'checkboxes',
				'default' => [ 'x_content_type', 'x_frame', 'referrer' ],
				'options' => [
					'x_content_type' => __( 'X-Content-Type-Options: nosniff', 'wpsk-security-headers' ),
					'x_frame'        => __( 'X-Frame-Options: SAMEORIGIN', 'wpsk-security-headers' ),
					'referrer'       => __( 'Referrer-Policy: strict-origin-when-cross-origin', 'wpsk-security-headers' ),
					'permissions'    => __( 'Permissions-Policy (restrict browser APIs)', 'wpsk-security-headers' ),
					'hsts'           => __( 'Strict-Transport-Security (HSTS) — only enable if your site uses HTTPS exclusively', 'wpsk-security-headers' ),
				],
			],
			[
				'id'          => 'permissions_policy',
				'label'       => __( 'Permissions-Policy Directives', 'wpsk-security-headers' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated list of restricted APIs. Default restricts camera, microphone, geolocation, and XR.', 'wpsk-security-headers' ),
				'default'     => 'camera=(), microphone=(), geolocation=(), xr-spatial-tracking=()',
				'placeholder' => 'camera=(), microphone=(), geolocation=()',
			],
			[
				'id'          => 'hsts_max_age',
				'label'       => __( 'HSTS Max-Age (seconds)', 'wpsk-security-headers' ),
				'type'        => 'number',
				'description' => __( 'How long browsers should remember to use HTTPS. 31536000 = 1 year (recommended).', 'wpsk-security-headers' ),
				'default'     => 31536000,
			],

			/* ── CSP ───────────────────────────────────────── */

			[
				'id'          => 'csp_enable',
				'label'       => __( 'Content Security Policy', 'wpsk-security-headers' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Content-Security-Policy header.', 'wpsk-security-headers' ),
				'default'     => '',
			],
			[
				'id'      => 'csp_mode',
				'label'   => __( 'CSP Mode', 'wpsk-security-headers' ),
				'type'    => 'select',
				'default' => 'report',
				'options' => [
					'report'  => __( 'Report-Only (log violations, don\'t block)', 'wpsk-security-headers' ),
					'enforce' => __( 'Enforce (block violations)', 'wpsk-security-headers' ),
				],
				'description' => __( 'Start with Report-Only to test, then switch to Enforce once you\'ve fixed all violations.', 'wpsk-security-headers' ),
			],
			[
				'id'          => 'csp_directives',
				'label'       => __( 'CSP Directives', 'wpsk-security-headers' ),
				'type'        => 'textarea',
				'description' => __( 'Full CSP policy string. Example: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:', 'wpsk-security-headers' ),
				'default'     => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests",
				'placeholder' => "default-src 'self'; ...",
			],

			/* ── WordPress hardening ──────────────────────── */

			[
				'id'      => 'hardening',
				'label'   => __( 'WordPress Hardening', 'wpsk-security-headers' ),
				'type'    => 'checkboxes',
				'default' => [ 'hide_rest_users', 'block_author_enum', 'remove_generator' ],
				'options' => [
					'hide_rest_users'    => __( 'Hide REST API users endpoint from unauthenticated visitors', 'wpsk-security-headers' ),
					'block_author_enum'  => __( 'Block author enumeration (?author=N and /author/ archives)', 'wpsk-security-headers' ),
					'remove_generator'   => __( 'Remove WordPress version from page source', 'wpsk-security-headers' ),
					'remove_rsd'         => __( 'Remove RSD (Really Simple Discovery) link', 'wpsk-security-headers' ),
					'remove_wlw'         => __( 'Remove Windows Live Writer manifest link', 'wpsk-security-headers' ),
					'disable_xmlrpc'     => __( 'Disable XML-RPC (legacy remote publishing protocol)', 'wpsk-security-headers' ),
					'safe_email'         => __( 'Enable [safe_email] shortcode for email obfuscation', 'wpsk-security-headers' ),
				],
			],
		];
	}

	/* ----------------------------------------------------------
	 * Init — hook into WP
	 * ---------------------------------------------------------- */

	protected function init(): void {
		$this->init_http_headers();
		$this->init_csp();
		$this->init_hardening();
	}

	/* ----------------------------------------------------------
	 * HTTP security headers
	 * ---------------------------------------------------------- */

	private function init_http_headers(): void {
		$headers = $this->get_option( 'http_headers' );
		if ( ! is_array( $headers ) || empty( $headers ) ) {
			return;
		}

		add_action( 'send_headers', function () use ( $headers ) {
			if ( headers_sent() ) {
				return;
			}

			if ( in_array( 'x_content_type', $headers, true ) ) {
				header( 'X-Content-Type-Options: nosniff', true );
			}

			if ( in_array( 'x_frame', $headers, true ) ) {
				header( 'X-Frame-Options: SAMEORIGIN', true );
			}

			if ( in_array( 'referrer', $headers, true ) ) {
				header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
			}

			if ( in_array( 'permissions', $headers, true ) ) {
				$directives = $this->get_option( 'permissions_policy' );
				if ( '' !== $directives ) {
					header( 'Permissions-Policy: ' . $directives, true );
				}
			}

			if ( in_array( 'hsts', $headers, true ) ) {
				$max_age = (int) $this->get_option( 'hsts_max_age' );
				if ( $max_age > 0 ) {
					header( 'Strict-Transport-Security: max-age=' . $max_age . '; includeSubDomains', true );
				}
			}
		}, 1 );
	}

	/* ----------------------------------------------------------
	 * Content Security Policy
	 * ---------------------------------------------------------- */

	private function init_csp(): void {
		if ( '1' !== $this->get_option( 'csp_enable' ) ) {
			return;
		}

		add_action( 'send_headers', function () {
			if ( headers_sent() ) {
				return;
			}

			$directives = trim( $this->get_option( 'csp_directives' ) );
			if ( '' === $directives ) {
				return;
			}

			$mode   = $this->get_option( 'csp_mode' );
			$header = 'enforce' === $mode
				? 'Content-Security-Policy'
				: 'Content-Security-Policy-Report-Only';

			header( $header . ': ' . $directives, true );
		}, 1 );
	}

	/* ----------------------------------------------------------
	 * WordPress hardening
	 * ---------------------------------------------------------- */

	private function init_hardening(): void {
		$flags = $this->get_option( 'hardening' );
		if ( ! is_array( $flags ) || empty( $flags ) ) {
			return;
		}

		// Hide REST API /wp/v2/users from unauthenticated requests.
		if ( in_array( 'hide_rest_users', $flags, true ) ) {
			add_filter( 'rest_endpoints', function ( array $endpoints ): array {
				if ( ! is_user_logged_in() ) {
					unset(
						$endpoints['/wp/v2/users'],
						$endpoints['/wp/v2/users/(?P<id>[\d]+)']
					);
				}
				return $endpoints;
			} );
		}

		// Block author enumeration.
		if ( in_array( 'block_author_enum', $flags, true ) ) {
			add_action( 'template_redirect', function () {
				if ( is_author() || ( isset( $_GET['author'] ) && preg_match( '/^\d+$/', $_GET['author'] ) ) ) {
					wp_safe_redirect( home_url(), 301 );
					exit;
				}
			} );
		}

		// Remove WordPress version meta tag.
		if ( in_array( 'remove_generator', $flags, true ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			remove_action( 'wp_head', 'wp_generator' );
		}

		// Remove RSD link.
		if ( in_array( 'remove_rsd', $flags, true ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		// Remove WLW manifest link.
		if ( in_array( 'remove_wlw', $flags, true ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		// Disable XML-RPC.
		if ( in_array( 'disable_xmlrpc', $flags, true ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'pings_open', '__return_false', PHP_INT_MAX );
		}

		// [safe_email] shortcode — obfuscate email addresses.
		if ( in_array( 'safe_email', $flags, true ) ) {
			add_shortcode( 'safe_email', [ $this, 'shortcode_safe_email' ] );
		}
	}

	/* ----------------------------------------------------------
	 * Shortcodes
	 * ---------------------------------------------------------- */

	/**
	 * [safe_email address="user@example.com"]
	 * Renders an obfuscated mailto link using WP's antispambot().
	 */
	public function shortcode_safe_email( $atts ): string {
		$atts  = shortcode_atts( [ 'address' => '' ], $atts, 'safe_email' );
		$email = sanitize_email( $atts['address'] );
		if ( ! $email ) {
			return '';
		}
		$encoded = antispambot( $email );
		return '<a href="mailto:' . esc_attr( $encoded ) . '">' . esc_html( $encoded ) . '</a>';
	}
}
