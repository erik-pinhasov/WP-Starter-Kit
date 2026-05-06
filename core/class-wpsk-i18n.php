<?php
/**
 * WPSK I18n — Translation loader.
 *
 * @package    WPStarterKit
 * @subpackage Core
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSK_I18n
 *
 * Loads the suite-level 'wpsk' text domain.
 * Module-specific text domains are loaded by WPSK_Module::boot().
 */
final class WPSK_I18n {

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Load the suite text domain.
	 *
	 * @param string $plugin_path Absolute path to the plugin root.
	 */
	public function init( string $plugin_path ): void {
		add_action( 'init', function () use ( $plugin_path ) {
			$locale = determine_locale();
			$mofile = $plugin_path . 'languages/wpsk-' . $locale . '.mo';
			if ( file_exists( $mofile ) ) {
				load_textdomain( 'wpsk', $mofile );
			}
		} );
	}
}
