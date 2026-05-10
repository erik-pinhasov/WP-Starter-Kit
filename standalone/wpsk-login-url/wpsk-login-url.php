<?php
/**
 * Plugin Name:       WP Starter Kit: Custom Login URL
 * Plugin URI:        https://github.com/YOUR_USERNAME/wpsk-login-url
 * Description:       Hide wp-login.php behind a custom URL.
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://github.com/YOUR_USERNAME
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-login-url
 * Domain Path:       /modules/login-url/languages
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// If the suite is active, skip this standalone plugin.
if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Custom Login URL — the full suite is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-login-url' );
		echo '</p></div>';
	} );
	return;
}

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );

require_once __DIR__ . '/modules/login-url/class-wpsk-login-url.php';
WPSK_Core::instance()->register_module( new WPSK_Login_URL() );
