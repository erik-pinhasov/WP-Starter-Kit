<?php
/**
 * Plugin Name:       WP Starter Kit
 * Plugin URI:        https://github.com/YOUR_USERNAME/wp-starter-kit
 * Description:       A modular developer toolkit: Turnstile CAPTCHA, custom login URL, media organizer, accessibility toolbar, Brevo mailer, media replace, security hardening, and performance optimizer — all in one plugin with individual toggles.
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://github.com/YOUR_USERNAME
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	return;
}
define( 'WPSK_SUITE_ACTIVE', true );
define( 'WPSK_VERSION', '1.1.0' );
define( 'WPSK_PLUGIN_FILE', __FILE__ );

/* ── Boot the core ─────────────────────────────────────────── */

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, true );

/* ── Register all available modules ────────────────────────── */

$wpsk_modules_dir = __DIR__ . '/modules/';

$wpsk_module_map = [
	'turnstile'        => 'WPSK_Turnstile',
	'login-url'        => 'WPSK_Login_URL',
	'media-organizer'  => 'WPSK_Media_Organizer',
	'media-replace'    => 'WPSK_Media_Replace',
	'brevo-mailer'     => 'WPSK_Brevo_Mailer',
	'accessibility'    => 'WPSK_Accessibility',
	'security-headers' => 'WPSK_Security_Headers',
	'performance'      => 'WPSK_Performance',
];

foreach ( $wpsk_module_map as $module_slug => $class_name ) {
	$class_file = $wpsk_modules_dir . $module_slug . '/class-wpsk-' . $module_slug . '.php';
	if ( file_exists( $class_file ) ) {
		require_once $class_file;
		if ( class_exists( $class_name ) ) {
			WPSK_Core::instance()->register_module( new $class_name() );
		}
	}
}

/* ── Standalone migration notice ───────────────────────────── */
// If standalone plugins were active before the suite was installed,
// show a notice suggesting deactivation.
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) return;

	$standalone_slugs = [
		'wpsk-turnstile/wpsk-turnstile.php',
		'wpsk-login-url/wpsk-login-url.php',
		'wpsk-media-organizer/wpsk-media-organizer.php',
		'wpsk-media-replace/wpsk-media-replace.php',
		'wpsk-brevo-mailer/wpsk-brevo-mailer.php',
		'wpsk-accessibility/wpsk-accessibility.php',
		'wpsk-security-headers/wpsk-security-headers.php',
		'wpsk-performance/wpsk-performance.php',
	];

	$active_standalones = [];
	foreach ( $standalone_slugs as $slug ) {
		if ( is_plugin_active( $slug ) ) {
			$active_standalones[] = $slug;
		}
	}

	if ( ! empty( $active_standalones ) ) {
		echo '<div class="notice notice-info is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'WP Starter Kit:', 'wpsk' ) . '</strong> ';
		printf(
			esc_html__( 'The full suite is now active! You have %d standalone plugin(s) that can be safely deactivated — their settings are preserved.', 'wpsk' ),
			count( $active_standalones )
		);
		echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go to Plugins →', 'wpsk' ) . '</a>';
		echo '</p></div>';
	}
} );
