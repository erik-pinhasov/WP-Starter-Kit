<?php
/**
 * Plugin Name:       WP Starter Kit: Security Headers
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-security-headers
 * Description:       HTTP security headers (CSP, HSTS, Permissions-Policy) and WordPress hardening — block author enumeration, hide REST users, disable XML-RPC, and more.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-security-headers
 * Domain Path:       /modules/security-headers/languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Security Headers — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-security-headers' );
		echo '</p></div>';
	} );
	return;
}

define( 'WPSK_SECURITY_HEADERS_VERSION', '1.0.0' );

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );

require_once __DIR__ . '/modules/security-headers/class-wpsk-security-headers.php';
WPSK_Core::instance()->register_module( new WPSK_Security_Headers() );
