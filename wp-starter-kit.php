<?php
/**
 * Plugin Name:       WP Starter Kit
 * Plugin URI:        https://github.com/erik-pinhasov/wp-starter-kit
 * Description:       A modular developer toolkit: Turnstile CAPTCHA, custom login URL, media organizer, accessibility toolbar, Brevo mailer, media replace, security headers, and performance cleanup — all in one plugin with individual toggles.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent loading if a standalone module already booted the core.
if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	return;
}
define( 'WPSK_SUITE_ACTIVE', true );
define( 'WPSK_VERSION', '1.0.0' );
define( 'WPSK_PLUGIN_FILE', __FILE__ );

/* ── Boot the core ─────────────────────────────────────────── */

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, true );

/* ── Register all available modules ────────────────────────── */

$wpsk_modules_dir = __DIR__ . '/modules/';

// 1. Turnstile
if ( file_exists( $wpsk_modules_dir . 'turnstile/class-wpsk-turnstile.php' ) ) {
	require_once $wpsk_modules_dir . 'turnstile/class-wpsk-turnstile.php';
	WPSK_Core::instance()->register_module( new WPSK_Turnstile() );
}

// 2. Login URL
if ( file_exists( $wpsk_modules_dir . 'login-url/class-wpsk-login-url.php' ) ) {
	require_once $wpsk_modules_dir . 'login-url/class-wpsk-login-url.php';
	WPSK_Core::instance()->register_module( new WPSK_Login_URL() );
}

// 3. Media Organizer
if ( file_exists( $wpsk_modules_dir . 'media-organizer/class-wpsk-media-organizer.php' ) ) {
	require_once $wpsk_modules_dir . 'media-organizer/class-wpsk-media-organizer.php';
	WPSK_Core::instance()->register_module( new WPSK_Media_Organizer() );
}

// 4. Accessibility
if ( file_exists( $wpsk_modules_dir . 'accessibility/class-wpsk-accessibility.php' ) ) {
	require_once $wpsk_modules_dir . 'accessibility/class-wpsk-accessibility.php';
	WPSK_Core::instance()->register_module( new WPSK_Accessibility() );
}

// 5. Brevo Mailer
if ( file_exists( $wpsk_modules_dir . 'brevo-mailer/class-wpsk-brevo-mailer.php' ) ) {
	require_once $wpsk_modules_dir . 'brevo-mailer/class-wpsk-brevo-mailer.php';
	WPSK_Core::instance()->register_module( new WPSK_Brevo_Mailer() );
}

// 6. Media Replace
if ( file_exists( $wpsk_modules_dir . 'media-replace/class-wpsk-media-replace.php' ) ) {
	require_once $wpsk_modules_dir . 'media-replace/class-wpsk-media-replace.php';
	WPSK_Core::instance()->register_module( new WPSK_Media_Replace() );
}

// 7. Security Headers
if ( file_exists( $wpsk_modules_dir . 'security-headers/class-wpsk-security-headers.php' ) ) {
	require_once $wpsk_modules_dir . 'security-headers/class-wpsk-security-headers.php';
	WPSK_Core::instance()->register_module( new WPSK_Security_Headers() );
}

// 8. Performance Cleanup
if ( file_exists( $wpsk_modules_dir . 'performance/class-wpsk-performance.php' ) ) {
	require_once $wpsk_modules_dir . 'performance/class-wpsk-performance.php';
	WPSK_Core::instance()->register_module( new WPSK_Performance() );
}
