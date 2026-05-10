<?php
/**
 * WPSK Security Headers — Send security HTTP headers and provide
 * email obfuscation shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Security_Headers extends WPSK_Module {

	public function get_id(): string {
		return 'security-headers';
	}

	public function get_name(): string {
		return __( 'Security Hardening', 'wpsk-security-headers' );
	}

	public function get_description(): string {
		return __( 'Add security HTTP headers, hide WordPress version, block author enumeration, and obfuscate emails.', 'wpsk-security-headers' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'x_content_type',
				'label'       => __( 'X-Content-Type-Options', 'wpsk-security-headers' ),
				'type'        => 'checkbox',
				'description' => __( 'Prevents MIME-type sniffing. Blocks attacks that exploit content type confusion.', 'wpsk-security-headers' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'x_frame_options',
				'label'       => __( 'X-Frame-Options', 'wpsk-security-headers' ),
				'type'        => 'select',
				'default'     => 'SAMEORIGIN',
				'options'     => [
					''           => __( 'Disabled', 'wpsk-security-headers' ),
					'DENY'       => 'DENY',
					'SAMEORIGIN' => 'SAMEORIGIN',
				],
				'description' => __( 'Controls whether your site can be embedded in iframes. SAMEORIGIN is recommended.', 'wpsk-security-headers' ),
				'importance'  => 'high',
			],
			[
				'id'          => 'referrer_policy',
				'label'       => __( 'Referrer-Policy', 'wpsk-security-headers' ),
				'type'        => 'select',
				'default'     => 'strict-origin-when-cross-origin',
				'options'     => [
					''                                   => __( 'Disabled', 'wpsk-security-headers' ),
					'no-referrer'                        => 'no-referrer',
					'strict-origin'                      => 'strict-origin',
					'strict-origin-when-cross-origin'    => 'strict-origin-when-cross-origin',
					'same-origin'                        => 'same-origin',
				],
				'importance' => 'medium',
			],
			[
				'id'          => 'hide_version',
				'label'       => __( 'Hide WordPress Version', 'wpsk-security-headers' ),
				'type'        => 'checkbox',
				'description' => __( 'Remove the WordPress version number from the page source and RSS feeds.', 'wpsk-security-headers' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'block_author_enum',
				'label'       => __( 'Block Author Enumeration', 'wpsk-security-headers' ),
				'type'        => 'checkbox',
				'description' => __( 'Prevent username discovery via ?author=N URLs. Reduces brute-force attack surface.', 'wpsk-security-headers' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'disable_rest_users',
				'label'       => __( 'Hide REST API Users', 'wpsk-security-headers' ),
				'type'        => 'checkbox',
				'description' => __( 'Block public access to /wp-json/wp/v2/users endpoint.', 'wpsk-security-headers' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'safe_email',
				'label'       => __( 'Email Obfuscation Shortcode', 'wpsk-security-headers' ),
				'type'        => 'html',
				'default'     => '',
				'html'        => $this->render_safe_email_help(),
			],
		];
	}

	/**
	 * Render the safe_email help section with copy box.
	 */
	private function render_safe_email_help(): string {
		$admin_email = get_option( 'admin_email' );
		$shortcode   = '[safe_email address="' . esc_attr( $admin_email ) . '"]';

		return '<p class="description" style="margin-bottom:8px">'
			. esc_html__( 'Use this shortcode in your content to display an email address that\'s protected from spam bots. The email is encoded so crawlers can\'t read it, but visitors see a normal clickable link.', 'wpsk-security-headers' )
			. '</p>'
			. '<div class="wpsk-copy-box">'
			. '<input type="text" readonly value="' . esc_attr( $shortcode ) . '" id="wpsk-safe-email-input" onclick="this.select()" />'
			. '<button type="button" class="button" onclick="'
			. 'var i=document.getElementById(\'wpsk-safe-email-input\');i.select();document.execCommand(\'copy\');'
			. 'this.textContent=\'' . esc_js( __( 'Copied!', 'wpsk-security-headers' ) ) . '\';'
			. 'var b=this;setTimeout(function(){b.textContent=\'' . esc_js( __( 'Copy', 'wpsk-security-headers' ) ) . '\';},2000);'
			. '">' . esc_html__( 'Copy', 'wpsk-security-headers' ) . '</button>'
			. '</div>'
			. '<p class="description" style="margin-top:6px">'
			. esc_html__( 'You can change the email address in the shortcode. Example:', 'wpsk-security-headers' )
			. ' <code>[safe_email address="info@example.com" text="Contact Us"]</code>'
			. '</p>';
	}

	public function get_help_html(): string {
		return '<strong>' . esc_html__( 'What are security headers?', 'wpsk-security-headers' ) . '</strong> '
			. esc_html__( 'HTTP security headers tell browsers how to handle your site\'s content. They prevent common attacks like clickjacking, MIME sniffing, and XSS. Most hosting providers don\'t set these by default.', 'wpsk-security-headers' )
			. '<br><br>'
			. sprintf(
				esc_html__( 'You can test your headers at %s after saving.', 'wpsk-security-headers' ),
				'<a href="https://securityheaders.com/" target="_blank">securityheaders.com</a>'
			);
	}

	protected function init(): void {
		// Security headers.
		add_action( 'send_headers', [ $this, 'send_headers' ] );

		// Hide WP version.
		if ( '1' === $this->get_option( 'hide_version' ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			remove_action( 'wp_head', 'wp_generator' );
		}

		// Block author enumeration.
		if ( '1' === $this->get_option( 'block_author_enum' ) ) {
			add_action( 'template_redirect', [ $this, 'block_author_enum' ] );
		}

		// REST API users.
		if ( '1' === $this->get_option( 'disable_rest_users' ) ) {
			add_filter( 'rest_endpoints', [ $this, 'disable_rest_users' ] );
		}

		// Safe email shortcode.
		add_shortcode( 'safe_email', [ $this, 'shortcode_safe_email' ] );
	}

	public function send_headers(): void {
		if ( headers_sent() ) return;

		if ( '1' === $this->get_option( 'x_content_type' ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		$x_frame = $this->get_option( 'x_frame_options' );
		if ( $x_frame ) {
			header( 'X-Frame-Options: ' . $x_frame );
		}

		$referrer = $this->get_option( 'referrer_policy' );
		if ( $referrer ) {
			header( 'Referrer-Policy: ' . $referrer );
		}
	}

	public function block_author_enum(): void {
		if ( is_author() || ( isset( $_GET['author'] ) && preg_match( '/^\d+$/', $_GET['author'] ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public function disable_rest_users( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'] );
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	public function shortcode_safe_email( $atts ): string {
		$atts = shortcode_atts( [
			'address' => '',
			'text'    => '',
		], $atts, 'safe_email' );

		$email = sanitize_email( $atts['address'] );
		if ( empty( $email ) ) return '';

		$display = $atts['text'] ?: $email;
		return '<a href="' . antispambot( 'mailto:' . $email, 1 ) . '">' . antispambot( $display ) . '</a>';
	}
}
