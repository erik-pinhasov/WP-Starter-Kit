<?php
/**
 * WPSK Core — Bootstrap and module registry.
 *
 * @package    WPStarterKit
 * @subpackage Core
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard: multiple standalone plugins each bundle this file at different paths.
// require_once deduplicates by path, not by class, so without this check
// the second standalone plugin causes "Cannot declare class WPSK_Core".
if ( class_exists( 'WPSK_Core' ) ) {
	return;
}

/**
 * Singleton that boots the framework, registers modules,
 * and renders the shared admin dashboard.
 */
final class WPSK_Core {

	const VERSION = '1.0.0';
	const MIN_PHP = '7.4';
	const MIN_WP  = '5.9';

	/** @var self|null */
	private static $instance = null;

	/** @var WPSK_Module[] Registered modules keyed by ID. */
	private $modules = [];

	/** @var string */
	private $plugin_path = '';

	/** @var string */
	private $plugin_url = '';

	/** @var bool */
	private $is_suite = false;

	/** @var bool Track whether init() has already run. */
	private $initialized = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot the framework. Safe to call multiple times (idempotent after first).
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param bool   $is_suite    True when loaded from the suite plugin.
	 */
	public function init( string $plugin_file, bool $is_suite = false ): void {
		// If suite boots after a standalone already initialized, upgrade to suite mode.
		if ( $this->initialized ) {
			if ( $is_suite && ! $this->is_suite ) {
				$this->is_suite    = true;
				$this->plugin_path = plugin_dir_path( $plugin_file );
				$this->plugin_url  = plugin_dir_url( $plugin_file );
				add_action( 'admin_menu', [ $this, 'register_suite_menu' ] );
				add_action( 'admin_init', [ $this, 'register_suite_settings' ] );
			}
			return;
		}

		$this->initialized = true;
		$this->plugin_path = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url( $plugin_file );
		$this->is_suite    = $is_suite;

		// Load dependencies (with class_exists guards in each file).
		require_once $this->plugin_path . 'core/class-wpsk-i18n.php';
		require_once $this->plugin_path . 'core/class-wpsk-module.php';
		require_once $this->plugin_path . 'core/class-wpsk-settings.php';

		WPSK_I18n::instance()->init( $this->plugin_path );

		if ( $is_suite ) {
			add_action( 'admin_menu', [ $this, 'register_suite_menu' ] );
			add_action( 'admin_init', [ $this, 'register_suite_settings' ] );
		}

		// Shared admin CSS for all WPSK pages.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_css' ] );
	}

	/* ----------------------------------------------------------
	 * Module registry
	 * ---------------------------------------------------------- */

	public function register_module( WPSK_Module $module ): bool {
		$id = $module->get_id();

		if ( isset( $this->modules[ $id ] ) ) {
			add_action( 'admin_notices', function () use ( $module ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					sprintf(
						esc_html__( 'WP Starter Kit: "%s" is already active via another plugin. The duplicate has been skipped.', 'wpsk' ),
						esc_html( $module->get_name() )
					)
				);
			} );
			return false;
		}

		$this->modules[ $id ] = $module;

		if ( ! $this->is_suite || $this->is_module_enabled( $id ) ) {
			$module->boot( $this->plugin_path, $this->plugin_url );
		}

		return true;
	}

	public function is_module_enabled( string $id ): bool {
		if ( ! $this->is_suite ) {
			return true;
		}
		$enabled = (array) get_option( 'wpsk_enabled_modules', [] );
		return in_array( $id, $enabled, true );
	}

	/** @return WPSK_Module[] */
	public function get_modules(): array {
		return $this->modules;
	}

	public function get_plugin_path(): string {
		return $this->plugin_path;
	}

	public function get_plugin_url(): string {
		return $this->plugin_url;
	}

	public function is_suite(): bool {
		return $this->is_suite;
	}

	/* ----------------------------------------------------------
	 * Admin CSS (shared across all WPSK settings pages)
	 * ---------------------------------------------------------- */

	public function enqueue_admin_css( string $hook ): void {
		// Only load on WPSK pages.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$is_wpsk_page = false;
		if ( strpos( $screen->id ?? '', 'wpsk' ) !== false ) {
			$is_wpsk_page = true;
		}
		if ( ! $is_wpsk_page ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', $this->get_admin_css() );
	}

	private function get_admin_css(): string {
		return '
			.wpsk-field-description { color: #646970; font-style: italic; margin-top: 4px; }
			.wpsk-help-box { background: #f0f6fc; border: 1px solid #c3d5e8; border-radius: 4px; padding: 12px 16px; margin: 8px 0 16px; font-size: 13px; line-height: 1.6; }
			.wpsk-help-box a { font-weight: 500; }
			.wpsk-help-box code { background: #e2ecf5; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
			.wpsk-importance { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 3px; margin-left: 8px; vertical-align: middle; }
			.wpsk-importance-high { background: #fce4e4; color: #9b1c1c; }
			.wpsk-importance-medium { background: #fef3cd; color: #856404; }
			.wpsk-importance-low { background: #d4edda; color: #155724; }
			.wpsk-copy-box { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
			.wpsk-copy-box input[type="text"] { flex: 1; font-family: monospace; font-size: 13px; }
			.wpsk-copy-box button { white-space: nowrap; }
			.wpsk-setup-banner { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 8px 0; }
			.wpsk-setup-banner a { font-weight: 600; }
		';
	}

	/* ----------------------------------------------------------
	 * Suite admin dashboard
	 * ---------------------------------------------------------- */

	public function register_suite_menu(): void {
		add_menu_page(
			__( 'WP Starter Kit', 'wpsk' ),
			__( 'Starter Kit', 'wpsk' ),
			'manage_options',
			'wpsk-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-admin-generic',
			80
		);

		foreach ( $this->modules as $module ) {
			add_submenu_page(
				'wpsk-dashboard',
				$module->get_name(),
				$module->get_name(),
				'manage_options',
				'wpsk-' . $module->get_id(),
				[ $module, 'render_settings_page' ]
			);
		}
	}

	public function register_suite_settings(): void {
		register_setting( 'wpsk_dashboard', 'wpsk_enabled_modules', [
			'type'              => 'array',
			'sanitize_callback' => function ( $value ) {
				return is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
			},
		] );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle module enable/disable.
		if ( isset( $_POST['wpsk_dashboard_nonce'] ) && wp_verify_nonce( $_POST['wpsk_dashboard_nonce'], 'wpsk_dashboard' ) ) {
			$enabled = isset( $_POST['wpsk_enabled_modules'] ) ? array_map( 'sanitize_key', (array) $_POST['wpsk_enabled_modules'] ) : [];
			update_option( 'wpsk_enabled_modules', $enabled );

			// Redirect to settings page if module was just enabled.
			if ( ! empty( $_POST['wpsk_just_enabled'] ) ) {
				$just_enabled = sanitize_key( $_POST['wpsk_just_enabled'] );
				wp_safe_redirect( admin_url( 'admin.php?page=wpsk-' . $just_enabled . '&wpsk_setup=1' ) );
				exit;
			}

			echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'wpsk' ) . '</p></div>';
		}

		$enabled = (array) get_option( 'wpsk_enabled_modules', [] );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Starter Kit', 'wpsk' ) . '</h1>';
		echo '<p>' . esc_html__( 'Enable or disable modules below. After enabling, you\'ll be taken to the module\'s settings page.', 'wpsk' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'wpsk_dashboard', 'wpsk_dashboard_nonce' );
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th style="width:40px">' . esc_html__( 'On', 'wpsk' ) . '</th><th>' . esc_html__( 'Module', 'wpsk' ) . '</th><th>' . esc_html__( 'Description', 'wpsk' ) . '</th><th style="width:100px">' . esc_html__( 'Settings', 'wpsk' ) . '</th></tr></thead><tbody>';

		foreach ( $this->modules as $module ) {
			$id      = $module->get_id();
			$checked = in_array( $id, $enabled, true ) ? 'checked' : '';
			echo '<tr>';
			echo '<td><input type="checkbox" name="wpsk_enabled_modules[]" value="' . esc_attr( $id ) . '" ' . $checked . ' onchange="if(this.checked){document.querySelector(\'[name=wpsk_just_enabled]\').value=\'' . esc_js( $id ) . '\';}else{document.querySelector(\'[name=wpsk_just_enabled]\').value=\'\';}" /></td>';
			echo '<td><strong>' . esc_html( $module->get_name() ) . '</strong></td>';
			echo '<td>' . esc_html( $module->get_description() ) . '</td>';
			echo '<td>';
			if ( $checked ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=wpsk-' . $id ) ) . '">' . esc_html__( 'Configure', 'wpsk' ) . '</a>';
			} else {
				echo '<span style="color:#999">' . esc_html__( 'Enable first', 'wpsk' ) . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<input type="hidden" name="wpsk_just_enabled" value="" />';
		submit_button();
		echo '</form></div>';
	}
}
