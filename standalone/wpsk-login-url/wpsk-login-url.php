<?php
/**
 * Plugin Name:       WP Starter Kit: Login URL
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-login-url
 * Description:       Hide wp-login.php behind a custom URL slug. Lightweight alternative to WPS Hide Login.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-login-url
 * Domain Path:       /modules/login-url/languages
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Custom Login URL — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-login-url' );
		echo '</p></div>';
	} );
	return;
}
define( 'WPSK_LOGIN_URL_VERSION', '1.0.0' );
require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );
require_once __DIR__ . '/modules/login-url/class-wpsk-login-url.php';
WPSK_Core::instance()->register_module( new WPSK_Login_URL() );
