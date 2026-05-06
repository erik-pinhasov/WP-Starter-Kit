<?php
/**
 * WPSK Brevo Mailer — Route wp_mail() through Brevo transactional API.
 *
 * Replaces WP Mail SMTP, Fluent SMTP, Post SMTP, etc.
 * Uses the pre_wp_mail filter (WP 5.9+) so the default PHPMailer
 * path is never touched when a valid API key is configured.
 *
 * @package    WPStarterKit
 * @subpackage Modules\BrevoMailer
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Brevo_Mailer extends WPSK_Module {

	/* ----------------------------------------------------------
	 * Module identity
	 * ---------------------------------------------------------- */

	public function get_id(): string {
		return 'brevo-mailer';
	}

	public function get_name(): string {
		return __( 'Brevo Mailer', 'wpsk-brevo-mailer' );
	}

	public function get_description(): string {
		return __( 'Route all WordPress emails through Brevo (formerly Sendinblue) transactional API for reliable delivery.', 'wpsk-brevo-mailer' );
	}

	/* ----------------------------------------------------------
	 * Settings definition
	 * ---------------------------------------------------------- */

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'api_key',
				'label'       => __( 'Brevo API Key', 'wpsk-brevo-mailer' ),
				'type'        => 'password',
				'description' => __( 'Your Brevo API key (v3). Find it in Brevo dashboard → SMTP & API → API Keys.', 'wpsk-brevo-mailer' ),
				'default'     => '',
				'placeholder' => 'xkeysib-...',
			],
			[
				'id'          => 'from_email',
				'label'       => __( 'From Email', 'wpsk-brevo-mailer' ),
				'type'        => 'text',
				'description' => __( 'Sender email address. Must be verified in your Brevo account.', 'wpsk-brevo-mailer' ),
				'default'     => '',
				'placeholder' => 'info@example.com',
			],
			[
				'id'          => 'from_name',
				'label'       => __( 'From Name', 'wpsk-brevo-mailer' ),
				'type'        => 'text',
				'description' => __( 'Sender display name shown in recipients\' inbox.', 'wpsk-brevo-mailer' ),
				'default'     => '',
				'placeholder' => get_bloginfo( 'name' ),
			],
			[
				'id'          => 'force_from',
				'label'       => __( 'Force From Address', 'wpsk-brevo-mailer' ),
				'type'        => 'checkbox',
				'description' => __( 'Always use the From Email above, even when other plugins set a different sender. Recommended for consistent deliverability.', 'wpsk-brevo-mailer' ),
				'default'     => '1',
			],
			[
				'id'          => 'log_errors',
				'label'       => __( 'Log Errors', 'wpsk-brevo-mailer' ),
				'type'        => 'checkbox',
				'description' => __( 'Write failed email attempts to the WordPress error log (wp-content/debug.log).', 'wpsk-brevo-mailer' ),
				'default'     => '1',
			],
		];
	}

	/* ----------------------------------------------------------
	 * Init — hook into WP
	 * ---------------------------------------------------------- */

	protected function init(): void {
		$api_key = $this->get_option( 'api_key' );

		// No API key — show admin notice, don't intercept mail.
		if ( '' === $api_key ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', [ $this, 'notice_missing_key' ] );
			}
			return;
		}

		// Intercept wp_mail via pre_wp_mail (WP 5.9+).
		add_filter( 'pre_wp_mail', [ $this, 'handle_mail' ], 10, 2 );

		// Test email AJAX handler.
		add_action( 'wp_ajax_wpsk_brevo_test_email', [ $this, 'ajax_test_email' ] );
	}

	/* ----------------------------------------------------------
	 * Mail interception
	 * ---------------------------------------------------------- */

	/**
	 * Intercept wp_mail() and send via Brevo transactional API.
	 *
	 * @param  null  $null Short-circuit value (always null on entry).
	 * @param  array $atts The wp_mail() arguments.
	 * @return bool|null   True on success, false on failure, null to fall through.
	 */
	public function handle_mail( $null, array $atts ) {
		$api_key = $this->get_option( 'api_key' );
		if ( '' === $api_key ) {
			return null; // Fall through to default mailer.
		}

		/* ── Unpack wp_mail arguments ───────────────────────── */

		$to          = $atts['to'];
		$subject     = $atts['subject'];
		$message     = $atts['message'];
		$headers     = $atts['headers'] ?? [];
		$attachments = $atts['attachments'] ?? [];

		/* ── Normalize $to ──────────────────────────────────── */

		if ( ! is_array( $to ) ) {
			$to = array_map( 'trim', explode( ',', $to ) );
		}

		$recipients = [];
		foreach ( $to as $addr ) {
			$parsed = self::parse_address( $addr );
			if ( $parsed ) {
				$recipients[] = $parsed;
			}
		}

		if ( empty( $recipients ) ) {
			$this->log_error( 'No valid recipients.', $atts );
			do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', 'Brevo mailer: no valid recipients.', $atts ) );
			return false;
		}

		/* ── Normalize headers ──────────────────────────────── */

		if ( is_string( $headers ) ) {
			$headers = array_filter(
				array_map( 'trim', explode( "\n", str_replace( "\r\n", "\n", $headers ) ) )
			);
		}
		if ( ! is_array( $headers ) ) {
			$headers = [];
		}

		/* ── Parse headers ──────────────────────────────────── */

		$content_type = 'text/plain';
		$cc           = [];
		$bcc          = [];
		$reply_to     = null;

		// Configured sender defaults.
		$from_email = $this->get_option( 'from_email' );
		$from_name  = $this->get_option( 'from_name' );
		if ( '' === $from_email ) {
			$from_email = get_bloginfo( 'admin_email' );
		}
		if ( '' === $from_name ) {
			$from_name = get_bloginfo( 'name' );
		}

		$force_from = '1' === $this->get_option( 'force_from' );

		foreach ( $headers as $header ) {
			if ( false === strpos( $header, ':' ) ) {
				continue;
			}

			[ $h_name, $h_value ] = array_map( 'trim', explode( ':', $header, 2 ) );

			switch ( strtolower( $h_name ) ) {

				case 'content-type':
					if ( false !== stripos( $h_value, 'text/html' ) ) {
						$content_type = 'text/html';
					}
					break;

				case 'from':
					if ( ! $force_from ) {
						$parsed = self::parse_address( $h_value );
						if ( $parsed ) {
							$from_email = $parsed['email'];
							if ( ! empty( $parsed['name'] ) ) {
								$from_name = $parsed['name'];
							}
						}
					}
					break;

				case 'cc':
					foreach ( array_map( 'trim', explode( ',', $h_value ) ) as $addr ) {
						$parsed = self::parse_address( $addr );
						if ( $parsed ) {
							$cc[] = $parsed;
						}
					}
					break;

				case 'bcc':
					foreach ( array_map( 'trim', explode( ',', $h_value ) ) as $addr ) {
						$parsed = self::parse_address( $addr );
						if ( $parsed ) {
							$bcc[] = $parsed;
						}
					}
					break;

				case 'reply-to':
					$reply_to = self::parse_address( $h_value );
					break;
			}
		}

		/* ── Build Brevo payload ────────────────────────────── */

		$payload = [
			'sender'  => [ 'name' => $from_name, 'email' => $from_email ],
			'to'      => $recipients,
			'subject' => $subject,
		];

		if ( 'text/html' === $content_type ) {
			$payload['htmlContent'] = $message;
		} else {
			$payload['textContent'] = $message;
		}

		if ( ! empty( $cc ) ) {
			$payload['cc'] = $cc;
		}
		if ( ! empty( $bcc ) ) {
			$payload['bcc'] = $bcc;
		}
		if ( $reply_to ) {
			$payload['replyTo'] = $reply_to;
		}

		/* ── Attachments ────────────────────────────────────── */

		if ( ! empty( $attachments ) ) {
			$att_arr = [];
			foreach ( (array) $attachments as $filepath ) {
				if ( ! is_string( $filepath ) || ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
					continue;
				}
				$att_arr[] = [
					'name'    => basename( $filepath ),
					'content' => base64_encode( file_get_contents( $filepath ) ), // phpcs:ignore
				];
			}
			if ( ! empty( $att_arr ) ) {
				$payload['attachment'] = $att_arr;
			}
		}

		/* ── Send via Brevo API ─────────────────────────────── */

		$response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
			'timeout' => 15,
			'headers' => [
				'api-key'      => $api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => wp_json_encode( $payload ),
		] );

		/* ── Handle response ────────────────────────────────── */

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'wp_remote_post error: ' . $response->get_error_message(), compact( 'to', 'subject' ) );
			do_action( 'wp_mail_failed', new \WP_Error(
				'wp_mail_failed',
				$response->get_error_message(),
				compact( 'to', 'subject', 'message', 'headers', 'attachments' )
			) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		// Non-2xx — log and fire failure action.
		$body = wp_remote_retrieve_body( $response );
		$this->log_error( 'Brevo API HTTP ' . $code . ': ' . $body, compact( 'to', 'subject' ) );
		do_action( 'wp_mail_failed', new \WP_Error(
			'wp_mail_failed',
			'Brevo API HTTP ' . $code,
			[ 'response' => $body, 'to' => $to, 'subject' => $subject ]
		) );

		return false;
	}

	/* ----------------------------------------------------------
	 * Test email (AJAX)
	 * ---------------------------------------------------------- */

	/**
	 * Send a test email via AJAX.
	 */
	public function ajax_test_email(): void {
		check_ajax_referer( 'wpsk_brevo_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wpsk-brevo-mailer' ) ] );
		}

		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'wpsk-brevo-mailer' ) ] );
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Test email from %s', 'wpsk-brevo-mailer' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: %1$s: date/time, %2$s: site URL */
			__( "This is a test email sent via WP Starter Kit: Brevo Mailer.\n\nTimestamp: %1\$s\nSite: %2\$s\n\nIf you received this, your Brevo mailer is working correctly.", 'wpsk-brevo-mailer' ),
			current_time( 'Y-m-d H:i:s' ),
			home_url()
		);

		$result = wp_mail( $to, $subject, $body );

		if ( $result ) {
			wp_send_json_success( [
				'message' => sprintf(
					/* translators: %s: recipient email */
					__( 'Test email sent successfully to %s.', 'wpsk-brevo-mailer' ),
					$to
				),
			] );
		}

		wp_send_json_error( [
			'message' => __( 'Failed to send test email. Check the error log for details.', 'wpsk-brevo-mailer' ),
		] );
	}

	/* ----------------------------------------------------------
	 * Settings page override — add test email section
	 * ---------------------------------------------------------- */

	/**
	 * Render settings page with the standard fields + a test email form.
	 */
	public function render_settings_page(): void {
		$page = 'wpsk_' . $this->get_id();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_name() ); ?></h1>
			<p class="description"><?php echo esc_html( $this->get_description() ); ?></p>

			<form method="post" action="options.php">
				<?php
				settings_fields( $page );
				do_settings_sections( $page );
				submit_button();
				?>
			</form>

			<?php if ( '' !== $this->get_option( 'api_key' ) ) : ?>
			<hr>
			<h2><?php esc_html_e( 'Send Test Email', 'wpsk-brevo-mailer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Verify your configuration by sending a test email.', 'wpsk-brevo-mailer' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsk-test-to"><?php esc_html_e( 'Recipient', 'wpsk-brevo-mailer' ); ?></label></th>
					<td>
						<input type="email" id="wpsk-test-to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="you@example.com">
						<button type="button" id="wpsk-test-send" class="button button-secondary"><?php esc_html_e( 'Send Test', 'wpsk-brevo-mailer' ); ?></button>
						<span id="wpsk-test-result" style="margin-left:12px"></span>
					</td>
				</tr>
			</table>

			<script>
			(function(){
				var btn = document.getElementById('wpsk-test-send');
				var input = document.getElementById('wpsk-test-to');
				var result = document.getElementById('wpsk-test-result');
				btn.addEventListener('click', function(){
					btn.disabled = true;
					result.textContent = '<?php echo esc_js( __( 'Sending...', 'wpsk-brevo-mailer' ) ); ?>';
					result.style.color = '';
					var fd = new FormData();
					fd.append('action', 'wpsk_brevo_test_email');
					fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wpsk_brevo_test' ) ); ?>');
					fd.append('to', input.value);
					fetch(ajaxurl, { method:'POST', body:fd })
						.then(function(r){ return r.json(); })
						.then(function(d){
							result.textContent = d.data ? d.data.message : 'Unknown error';
							result.style.color = d.success ? '#00a32a' : '#d63638';
						})
						.catch(function(e){
							result.textContent = e.message || 'Request failed';
							result.style.color = '#d63638';
						})
						.finally(function(){ btn.disabled = false; });
				});
			})();
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ----------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------- */

	/**
	 * Parse "Name <email>" or bare "email" into array for Brevo.
	 *
	 * @return array{email:string,name?:string}|null
	 */
	public static function parse_address( string $raw ): ?array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( preg_match( '/^(.+?)\s*<([^>]+)>$/', $raw, $m ) ) {
			$email = sanitize_email( $m[2] );
			$name  = trim( $m[1], ' "\'' );
			return $email ? [ 'email' => $email, 'name' => $name ] : null;
		}

		$email = sanitize_email( $raw );
		return $email ? [ 'email' => $email ] : null;
	}

	/**
	 * Log an error to the WP debug log.
	 *
	 * @param string $message Error description.
	 * @param mixed  $context Additional data.
	 */
	private function log_error( string $message, $context = null ): void {
		if ( '1' !== $this->get_option( 'log_errors' ) ) {
			return;
		}
		$entry = '[WPSK Brevo Mailer] ' . $message;
		if ( $context ) {
			$entry .= ' | Context: ' . wp_json_encode( $context );
		}
		error_log( $entry );
	}

	/**
	 * Admin notice when API key is missing.
	 */
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
