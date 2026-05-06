<?php
/**
 * WPSK Settings — Shared settings utilities.
 *
 * @package    WPStarterKit
 * @subpackage Core
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSK_Settings
 *
 * Shared settings page helpers. Currently loaded by Core
 * for future use (e.g. export/import, settings migration).
 */
final class WPSK_Settings {

	/**
	 * Get all option keys for a module (useful for uninstall cleanup).
	 *
	 * @param WPSK_Module $module The module instance.
	 * @return string[] Array of option names.
	 */
	public static function get_module_option_keys( WPSK_Module $module ): array {
		$keys = [];
		foreach ( $module->get_settings_fields() as $field ) {
			$keys[] = $module->option_key( $field['id'] );
		}
		return $keys;
	}

	/**
	 * Delete all options for a module (for uninstall).
	 *
	 * @param WPSK_Module $module The module instance.
	 */
	public static function cleanup_module_options( WPSK_Module $module ): void {
		foreach ( self::get_module_option_keys( $module ) as $key ) {
			delete_option( $key );
		}
	}
}
