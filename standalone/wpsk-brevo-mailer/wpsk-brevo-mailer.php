<?php
/**
 * Plugin Name:       WP Starter Kit: Brevo Mailer
 * Plugin URI:        https://github.com/erik-pinhasov/wpsk-brevo-mailer
 * Description:       Route all WordPress emails through Brevo (formerly Sendinblue) transactional API. Reliable delivery with zero configuration beyond your API key.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            ErikP
 * Author URI:        https://github.com/erik-pinhasov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsk-brevo-mailer
 * Domain Path:       /modules/brevo-mailer/languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPSK_SUITE_ACTIVE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-info is-dismissible"><p>';
		esc_html_e( 'WP Starter Kit: Brevo Mailer — the suite plugin is active, so this standalone version has been skipped. You can safely deactivate it.', 'wpsk-brevo-mailer' );
		echo '</p></div>';
	} );
	return;
}

define( 'WPSK_BREVO_MAILER_VERSION', '1.0.0' );

require_once __DIR__ . '/core/class-wpsk-core.php';
WPSK_Core::instance()->init( __FILE__, false );

require_once __DIR__ . '/modules/brevo-mailer/class-wpsk-brevo-mailer.php';
WPSK_Core::instance()->register_module( new WPSK_Brevo_Mailer() );
