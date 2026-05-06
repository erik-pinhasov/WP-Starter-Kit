<?php
/**
 * Plugin Name:       WP Starter Kit: Turnstile
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-turnstile
 * Description:       Protect WordPress and WooCommerce forms with Cloudflare Turnstile CAPTCHA. No bloat, no dependencies — just paste your keys and go.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-turnstile
 * Domain Path:       /modules/turnstile/languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the suite is already running, it handles this module — bail out.
if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Turnstile — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-turnstile' );
		echo '</p></div>';
	} );
	return;
}

define( 'WPSK_TURNSTILE_VERSION', '1.0.0' );

/* ── Boot the core ─────────────────────────────────────────── */

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );

/* ── Register the Turnstile module ─────────────────────────── */

require_once __DIR__ . '/modules/turnstile/class-wpsk-turnstile.php';
WPSK_Core::instance()->register_module( new WPSK_Turnstile() );
