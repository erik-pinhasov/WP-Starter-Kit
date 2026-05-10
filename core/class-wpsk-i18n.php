<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'WPSK_I18n' ) ) { return; }

final class WPSK_I18n {
	private static $instance = null;
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	private function __construct() {}

	public function init( string $plugin_path ): void {
		add_action( 'init', function () use ( $plugin_path ) {
			load_plugin_textdomain( 'wpsk', false, basename( $plugin_path ) . '/languages' );
		} );
	}
}
