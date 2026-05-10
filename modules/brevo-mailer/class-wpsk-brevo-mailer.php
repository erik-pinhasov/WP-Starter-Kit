<?php
/**
 * WPSK Brevo Mailer — Route WordPress emails through Brevo API.
 *
 * Fix: Debug log now writes to wp-content/uploads/wpsk-brevo-debug.log
 * using wp_upload_dir() for a guaranteed-writable path.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Brevo_Mailer extends WPSK_Module {

	public function get_id(): string {
		return 'brevo-mailer';
	}

	public function get_name(): string {
		return __( 'Brevo Mailer', 'wpsk-brevo-mailer' );
	}

	public function get_description(): string {
		return __( 'Route all WordPress emails through Brevo (formerly Sendinblue) transactional API for reliable delivery.', 'wpsk-brevo-mailer' );
	}

	public function get_settings_fields(): array {
		// Build the safe_email shortcode example for the help box.
		$from_email = $this->get_option( 'from_email' );
		$from_name  = $this->get_option( 'from_name' );

		return [
			[
				'id'          => 'api_key',
				'label'       => __( 'Brevo API Key', 'wpsk-brevo-mailer' ),
				'type'        => 'password',
				'description' => __( 'Your Brevo v3 API key for transactional emails.', 'wpsk-brevo-mailer' ),
				'default'     => '',
				'placeholder' => 'xkeysib-...',
				'importance'  => 'high',
			],
			[
				'id'          => 'from_email',
				'label'       => __( 'From Email', 'wpsk-brevo-mailer' ),
				'type'        => 'text',
				'description' => __( 'Must match a verified sender in your Brevo account.', 'wpsk-brevo-mailer' ),
				'default'     => get_option( 'admin_email' ),
				'placeholder' => 'noreply@yourdomain.com',
				'importance'  => 'high',
			],
			[
				'id'          => 'from_name',
				'label'       => __( 'From Name', 'wpsk-brevo-mailer' ),
				'type'        => 'text',
				'description' => __( 'Display name for outgoing emails.', 'wpsk-brevo-mailer' ),
				'default'     => get_bloginfo( 'name' ),
				'placeholder' => 'My Website',
			],
			[
				'id'          => 'enable_log',
				'label'       => __( 'Debug Log', 'wpsk-brevo-mailer' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					__( 'Log email attempts to %s for troubleshooting.', 'wpsk-brevo-mailer' ),
					'<code>wp-content/uploads/wpsk-brevo-debug.log</code>'
				),
				'default'     => '0',
				'importance'  => 'low',
			],
			[
				'id'          => 'test_email',
				'label'       => __( 'Send Test Email', 'wpsk-brevo-mailer' ),
				'type'        => 'html',
				'default'     => '',
				'html'        => $this->render_test_email_button(),
			],
		];
	}

	public function get_help_html(): string {
		$safe_email = '';
		$from       = $this->get_option( 'from_email' );
		if ( $from ) {
			$safe_email = '<br><br><strong>📧 ' . esc_html__( 'Safe Email shortcode:', 'wpsk-brevo-mailer' ) . '</strong><br>'
				. esc_html__( 'Use this in your content to display an obfuscated email link:', 'wpsk-brevo-mailer' )
				. '<div class="wpsk-copy-box">'
				. '<input type="text" readonly value="[safe_email address=&quot;' . esc_attr( $from ) . '&quot;]" id="wpsk-safe-email-shortcode" />'
				. '<button type="button" class="button" onclick="var i=document.getElementById(\'wpsk-safe-email-shortcode\');i.select();document.execCommand(\'copy\');this.textContent=\'' . esc_js( __( 'Copied!', 'wpsk-brevo-mailer' ) ) . '\';setTimeout(function(){this.textContent=\'' . esc_js( __( 'Copy', 'wpsk-brevo-mailer' ) ) . '\';}.bind(this),2000);">' . esc_html__( 'Copy', 'wpsk-brevo-mailer' ) . '</button>'
				. '</div>';
		}

		return '<strong>🔑 ' . esc_html__( 'Getting your API key:', 'wpsk-brevo-mailer' ) . '</strong><br>'
			. sprintf(
				__( '1. Sign up or log in at <a href="%s" target="_blank">app.brevo.com</a><br>2. Go to <strong>Settings → SMTP & API → API Keys</strong><br>3. Create a new API key (or copy existing)<br>4. Paste it above', 'wpsk-brevo-mailer' ),
				'https://app.brevo.com'
			)
			. '<br><br><strong>' . esc_html__( 'Important:', 'wpsk-brevo-mailer' ) . '</strong> '
			. esc_html__( 'The "From Email" must match a verified sender in your Brevo account. Go to Settings → Senders & IP to verify your email address.', 'wpsk-brevo-mailer' )
			. $safe_email;
	}

	protected function init(): void {
		$api_key = $this->get_option( 'api_key' );

		if ( empty( $api_key ) ) {
			add_action( 'admin_notices', [ $this, 'notice_missing_key' ] );
			return;
		}

		// Override wp_mail to use Brevo API.
		add_filter( 'pre_wp_mail', [ $this, 'send_via_brevo' ], 10, 2 );

		// AJAX test email.
		add_action( 'wp_ajax_wpsk_brevo_test', [ $this, 'ajax_test_email' ] );
	}

	/* ── Email sending ──────────────────────────────────────── */

	public function send_via_brevo( $null, $atts ): ?bool {
		$api_key    = $this->get_option( 'api_key' );
		$from_email = $this->get_option( 'from_email' ) ?: get_option( 'admin_email' );
		$from_name  = $this->get_option( 'from_name' ) ?: get_bloginfo( 'name' );

		$to      = $atts['to'] ?? '';
		$subject = $atts['subject'] ?? '';
		$message = $atts['message'] ?? '';
		$headers = $atts['headers'] ?? '';

		// Parse recipients.
		$recipients = [];
		$to_list = is_array( $to ) ? $to : explode( ',', $to );
		foreach ( $to_list as $addr ) {
			$addr = trim( $addr );
			if ( $addr ) {
				$recipients[] = [ 'email' => $addr ];
			}
		}

		if ( empty( $recipients ) ) {
			$this->log( 'ERROR: No recipients for: ' . $subject );
			return null; // Let WordPress handle it.
		}

		// Determine content type.
		$is_html = false;
		if ( is_string( $headers ) ) {
			$is_html = stripos( $headers, 'text/html' ) !== false;
		} elseif ( is_array( $headers ) ) {
			$is_html = (bool) array_filter( $headers, function ( $h ) {
				return stripos( (string) $h, 'text/html' ) !== false;
			} );
		}

		$body = [
			'sender'  => [ 'name' => $from_name, 'email' => $from_email ],
			'to'      => $recipients,
			'subject' => $subject,
		];

		if ( $is_html ) {
			$body['htmlContent'] = $message;
		} else {
			$body['textContent'] = $message;
		}

		$response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
			'timeout' => 15,
			'headers' => [
				'api-key'      => $api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'ERROR: ' . $response->get_error_message() . ' | Subject: ' . $subject );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$resp_body = wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 ) {
			$this->log( 'OK: ' . $subject . ' → ' . implode( ', ', array_column( $recipients, 'email' ) ) );
			return true;
		}

		$this->log( 'FAIL (' . $code . '): ' . $subject . ' | Response: ' . $resp_body );
		return null;
	}

	/* ── Debug log ──────────────────────────────────────────── */

	private function log( string $message ): void {
		if ( '1' !== $this->get_option( 'enable_log' ) ) {
			return;
		}

		// FIX: Use wp_upload_dir() for a guaranteed writable location.
		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/wpsk-brevo-debug.log';

		// Create file if it doesn't exist.
		if ( ! file_exists( $log_file ) ) {
			// Also create an .htaccess to protect it.
			$htaccess = $upload_dir['basedir'] . '/.htaccess';
			if ( ! file_exists( $htaccess ) || strpos( file_get_contents( $htaccess ), 'wpsk-brevo-debug' ) === false ) {
				file_put_contents( $htaccess, "\n<Files wpsk-brevo-debug.log>\nOrder Allow,Deny\nDeny from all\n</Files>\n", FILE_APPEND );
			}
		}

		$entry = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
		error_log( $entry, 3, $log_file );
	}

	/* ── Test email ─────────────────────────────────────────── */

	private function render_test_email_button(): string {
		$nonce = wp_create_nonce( 'wpsk_brevo_test' );
		return '<button type="button" class="button" id="wpsk-brevo-test-btn" data-nonce="' . esc_attr( $nonce ) . '">'
			. esc_html__( 'Send Test Email', 'wpsk-brevo-mailer' ) . '</button>'
			. ' <span id="wpsk-brevo-test-result" style="margin-left:10px"></span>'
			. '<script>document.getElementById("wpsk-brevo-test-btn").addEventListener("click",function(){'
			. 'var btn=this,res=document.getElementById("wpsk-brevo-test-result");'
			. 'btn.disabled=true;res.textContent="' . esc_js( __( 'Sending...', 'wpsk-brevo-mailer' ) ) . '";'
			. 'fetch(ajaxurl+"?action=wpsk_brevo_test&_wpnonce="+btn.dataset.nonce,{method:"POST"})'
			. '.then(function(r){return r.json();})'
			. '.then(function(d){res.textContent=d.data||"Done";btn.disabled=false;})'
			. '.catch(function(e){res.textContent="Error: "+e.message;btn.disabled=false;});'
			. '});</script>';
	}

	public function ajax_test_email(): void {
		check_ajax_referer( 'wpsk_brevo_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wpsk-brevo-mailer' ) );
		}

		$to = get_option( 'admin_email' );
		$result = wp_mail(
			$to,
			__( 'WP Starter Kit — Brevo Test Email', 'wpsk-brevo-mailer' ),
			sprintf( __( 'This is a test email sent via Brevo at %s.', 'wpsk-brevo-mailer' ), gmdate( 'Y-m-d H:i:s' ) )
		);

		if ( $result ) {
			wp_send_json_success( sprintf( __( '✓ Test email sent to %s', 'wpsk-brevo-mailer' ), $to ) );
		} else {
			// Check the log for details.
			$upload_dir = wp_upload_dir();
			$log_file   = $upload_dir['basedir'] . '/wpsk-brevo-debug.log';
			$log_hint   = file_exists( $log_file )
				? sprintf( __( 'Check the log at %s', 'wpsk-brevo-mailer' ), $log_file )
				: __( 'Enable Debug Log to see error details.', 'wpsk-brevo-mailer' );
			wp_send_json_error( __( '✗ Failed to send test email.', 'wpsk-brevo-mailer' ) . ' ' . $log_hint );
		}
	}

	public function notice_missing_key(): void {
		$page_url = WPSK_Core::instance()->is_suite()
			? admin_url( 'admin.php?page=wpsk-brevo-mailer' )
			: admin_url( 'options-general.php?page=wpsk-brevo-mailer' );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Brevo Mailer: API key is required for email delivery.', 'wpsk-brevo-mailer' ),
			esc_url( $page_url ),
			esc_html__( 'Configure now →', 'wpsk-brevo-mailer' )
		);
	}
}
