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

/**
 * Class WPSK_Core
 *
 * Singleton that boots the framework, registers modules,
 * and renders the shared admin dashboard.
 */
final class WPSK_Core {

	/** @var string Framework version. */
	const VERSION = '1.0.0';

	/** @var string Minimum PHP version. */
	const MIN_PHP = '7.4';

	/** @var string Minimum WP version. */
	const MIN_WP = '5.9';

	/** @var self|null */
	private static $instance = null;

	/** @var WPSK_Module[] Registered modules keyed by ID. */
	private $modules = [];

	/** @var string Absolute path to the plugin root (with trailing slash). */
	private $plugin_path = '';

	/** @var string URL to the plugin root (with trailing slash). */
	private $plugin_url = '';

	/** @var bool Whether running as the suite (true) or a standalone module (false). */
	private $is_suite = false;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot the framework.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param bool   $is_suite    True when loaded from the suite plugin.
	 */
	public function init( string $plugin_file, bool $is_suite = false ): void {
		$this->plugin_path = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url( $plugin_file );
		$this->is_suite    = $is_suite;

		require_once $this->plugin_path . 'core/class-wpsk-i18n.php';
		require_once $this->plugin_path . 'core/class-wpsk-module.php';
		require_once $this->plugin_path . 'core/class-wpsk-settings.php';

		WPSK_I18n::instance()->init( $this->plugin_path );

		if ( $is_suite ) {
			add_action( 'admin_menu', [ $this, 'register_suite_menu' ] );
			add_action( 'admin_init', [ $this, 'register_suite_settings' ] );
		}
	}

	/* ----------------------------------------------------------
	 * Module registry
	 * ---------------------------------------------------------- */

	/**
	 * Register a module.
	 *
	 * @return bool False if the module ID is already active (conflict).
	 */
	public function register_module( WPSK_Module $module ): bool {
		$id = $module->get_id();

		// Conflict guard — same module already running.
		if ( isset( $this->modules[ $id ] ) ) {
			add_action( 'admin_notices', function () use ( $module ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: %s: module display name */
						esc_html__( 'WP Starter Kit: "%s" is already active via the suite plugin. The standalone version has been skipped.', 'wpsk' ),
						esc_html( $module->get_name() )
					)
				);
			} );
			return false;
		}

		$this->modules[ $id ] = $module;

		// Only boot if enabled (suite) or always (standalone).
		if ( ! $this->is_suite || $this->is_module_enabled( $id ) ) {
			$module->boot( $this->plugin_path, $this->plugin_url );
		}

		return true;
	}

	/**
	 * Check if a module is enabled in suite mode.
	 */
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
	 * Suite admin dashboard
	 * ---------------------------------------------------------- */

	/**
	 * Register the top-level suite menu and per-module sub-pages.
	 */
	public function register_suite_menu(): void {
		add_menu_page(
			__( 'WP Starter Kit', 'wpsk' ),
			__( 'WP Starter Kit', 'wpsk' ),
			'manage_options',
			'wpsk-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-admin-generic',
			80
		);

		// Sub-pages for each registered module.
		foreach ( $this->modules as $module ) {
			if ( ! $this->is_module_enabled( $module->get_id() ) ) {
				continue;
			}
			add_submenu_page(
				'wpsk-dashboard',
				$module->get_name(),
				$module->get_name(),
				'manage_options',
				'wpsk-' . $module->get_id(),
				function () use ( $module ) {
					$module->render_settings_page();
				}
			);
		}
	}

	/**
	 * Register the enabled-modules option.
	 */
	public function register_suite_settings(): void {
		register_setting( 'wpsk_dashboard', 'wpsk_enabled_modules', [
			'type'              => 'array',
			'sanitize_callback' => function ( $value ) {
				return is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
			},
			'default'           => [],
		] );
	}

	/**
	 * Render the suite dashboard (module toggles).
	 */
	public function render_dashboard(): void {
		$enabled = (array) get_option( 'wpsk_enabled_modules', [] );
		?>
		<div class="wrap wpsk-dashboard">
			<h1><?php esc_html_e( 'WP Starter Kit', 'wpsk' ); ?></h1>
			<p class="wpsk-dash-subtitle">
				<?php esc_html_e( 'Enable or disable individual modules. Each module adds a specific feature to your WordPress site.', 'wpsk' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpsk_dashboard' ); ?>

				<div class="wpsk-modules-grid">
					<?php foreach ( $this->modules as $module ) : ?>
						<div class="wpsk-module-card<?php echo in_array( $module->get_id(), $enabled, true ) ? ' wpsk-active' : ''; ?>">
							<label class="wpsk-module-toggle">
								<input
									type="checkbox"
									name="wpsk_enabled_modules[]"
									value="<?php echo esc_attr( $module->get_id() ); ?>"
									<?php checked( in_array( $module->get_id(), $enabled, true ) ); ?>
								>
								<span class="wpsk-toggle-track"><span class="wpsk-toggle-thumb"></span></span>
							</label>
							<h3><?php echo esc_html( $module->get_name() ); ?></h3>
							<p><?php echo esc_html( $module->get_description() ); ?></p>
							<?php if ( in_array( $module->get_id(), $enabled, true ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsk-' . $module->get_id() ) ); ?>" class="wpsk-module-settings-link">
									<?php esc_html_e( 'Settings', 'wpsk' ); ?> →
								</a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php submit_button( __( 'Save Changes', 'wpsk' ) ); ?>
			</form>
		</div>

		<style>
			.wpsk-dash-subtitle { font-size: 14px; color: #646970; margin: 4px 0 20px; }
			.wpsk-modules-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin: 20px 0; }
			.wpsk-module-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; position: relative; transition: border-color .2s; }
			.wpsk-module-card.wpsk-active { border-color: #2271b1; }
			.wpsk-module-card h3 { margin: 12px 0 6px; font-size: 15px; }
			.wpsk-module-card p { color: #646970; font-size: 13px; margin: 0 0 8px; }
			.wpsk-module-settings-link { font-size: 13px; text-decoration: none; }
			.wpsk-module-toggle { position: absolute; top: 16px; right: 16px; cursor: pointer; }
			.wpsk-module-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
			.wpsk-toggle-track { display: inline-block; width: 36px; height: 20px; background: #ccc; border-radius: 10px; position: relative; transition: background .2s; }
			.wpsk-toggle-thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform .2s; }
			.wpsk-module-toggle input:checked + .wpsk-toggle-track { background: #2271b1; }
			.wpsk-module-toggle input:checked + .wpsk-toggle-track .wpsk-toggle-thumb { transform: translateX(16px); }
		</style>
		<?php
	}
}
