<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'WPSK_Settings' ) ) { return; }

final class WPSK_Settings {
	public static function get_module_option_keys( WPSK_Module $module ): array {
		$keys = [];
		foreach ( $module->get_settings_fields() as $field ) {
			$keys[] = $module->option_key( $field['id'] );
		}
		return $keys;
	}

	public static function cleanup_module_options( WPSK_Module $module ): void {
		foreach ( self::get_module_option_keys( $module ) as $key ) {
			delete_option( $key );
		}
	}
}
