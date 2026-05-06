<?php
/**
 * Plugin Name:       WP Starter Kit: Media Replace
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-media-replace
 * Description:       Replace media files in-place — keep the same URL, ID, and references. Drag & drop upload UI.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-media-replace
 * Domain Path:       /modules/media-replace/languages
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Media Replace — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-media-replace' );
		echo '</p></div>';
	} );
	return;
}
define( 'WPSK_MEDIA_REPLACE_VERSION', '1.0.0' );
require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );
require_once __DIR__ . '/modules/media-replace/class-wpsk-media-replace.php';
WPSK_Core::instance()->register_module( new WPSK_Media_Replace() );
