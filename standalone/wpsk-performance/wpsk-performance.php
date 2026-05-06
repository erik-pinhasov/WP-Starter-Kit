<?php
/**
 * Plugin Name:       WP Starter Kit: Performance Cleanup
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-performance
 * Description:       Remove emoji, wp-embed, Google Fonts, jQuery Migrate, and other WordPress bloat. Throttle Heartbeat, limit revisions, block speculation on WooCommerce pages.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-performance
 * Domain Path:       /modules/performance/languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Performance Cleanup — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-performance' );
		echo '</p></div>';
	} );
	return;
}

define( 'WPSK_PERFORMANCE_VERSION', '1.0.0' );

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );

require_once __DIR__ . '/modules/performance/class-wpsk-performance.php';
WPSK_Core::instance()->register_module( new WPSK_Performance() );
