<?php
/**
 * WPSK Module — Abstract base class for all feature modules.
 *
 * @package    WPStarterKit
 * @subpackage Core
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSK_Module
 *
 * Every module extends this and implements:
 *   get_id(), get_name(), get_description(), init(), get_settings_fields()
 */
abstract class WPSK_Module {

	/** @var string Plugin root path (with trailing slash). */
	protected $plugin_path = '';

	/** @var string Plugin root URL (with trailing slash). */
	protected $plugin_url = '';

	/* ----------------------------------------------------------
	 * Required — each module MUST implement these.
	 * ---------------------------------------------------------- */

	/** Unique slug, e.g. 'turnstile'. */
	abstract public function get_id(): string;

	/** Human-readable name (English default). */
	abstract public function get_name(): string;

	/** One-line description (English default). */
	abstract public function get_description(): string;

	/**
	 * Hook everything — runs only when the module is active.
	 * This is where the module adds its actions/filters.
	 */
	abstract protected function init(): void;

	/**
	 * Return an array of settings field definitions.
	 *
	 * Each field: [
	 *   'id'          => string,       // option key suffix (full key = wpsk_{module_id}_{id})
	 *   'label'       => string,       // translated label
	 *   'type'        => string,       // text | password | checkbox | checkboxes | select | textarea | number
	 *   'description' => string,       // help text below the field
	 *   'default'     => mixed,        // default value
	 *   'options'     => array,        // for select / checkboxes: [ value => label ]
	 *   'placeholder' => string,       // for text / textarea
	 * ]
	 *
	 * @return array[]
	 */
	abstract public function get_settings_fields(): array;

	/* ----------------------------------------------------------
	 * Boot (called by Core after registration + enable check).
	 * ---------------------------------------------------------- */

	public function boot( string $plugin_path, string $plugin_url ): void {
		$this->plugin_path = $plugin_path;
		$this->plugin_url  = $plugin_url;

		// Load module-specific translations.
		$domain = 'wpsk-' . $this->get_id();
		$locale = determine_locale();
		$mofile = $this->plugin_path . 'modules/' . $this->get_id() . '/languages/' . $domain . '-' . $locale . '.mo';
		if ( file_exists( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}

		// Register settings early.
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// If running standalone (not suite), register own settings page.
		if ( ! WPSK_Core::instance()->is_suite() ) {
			add_action( 'admin_menu', [ $this, 'register_standalone_menu' ] );
		}

		// Module-specific hooks.
		$this->init();
	}

	/* ----------------------------------------------------------
	 * Settings helpers
	 * ---------------------------------------------------------- */

	/**
	 * Build the full option name for a field.
	 */
	public function option_key( string $field_id ): string {
		return 'wpsk_' . $this->get_id() . '_' . $field_id;
	}

	/**
	 * Get a single option value with its default.
	 */
	public function get_option( string $field_id ) {
		$fields = $this->get_settings_fields();
		$default = '';
		foreach ( $fields as $field ) {
			if ( $field['id'] === $field_id ) {
				$default = $field['default'] ?? '';
				break;
			}
		}
		return get_option( $this->option_key( $field_id ), $default );
	}

	/**
	 * Register all settings for this module with the WP Settings API.
	 */
	public function register_settings(): void {
		$page    = 'wpsk_' . $this->get_id();
		$section = $page . '_main';

		add_settings_section(
			$section,
			'',
			'__return_empty_string',
			$page
		);

		foreach ( $this->get_settings_fields() as $field ) {
			$key = $this->option_key( $field['id'] );

			register_setting( $page, $key, [
				'type'              => $this->wp_type( $field['type'] ),
				'sanitize_callback' => $this->get_sanitizer( $field ),
				'default'           => $field['default'] ?? '',
			] );

			add_settings_field(
				$key,
				$field['label'],
				[ $this, 'render_field' ],
				$page,
				$section,
				[ 'field' => $field ]
			);
		}
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Contains 'field' key with the field definition.
	 */
	public function render_field( array $args ): void {
		$field = $args['field'];
		$key   = $this->option_key( $field['id'] );
		$value = get_option( $key, $field['default'] ?? '' );
		$desc  = $field['description'] ?? '';
		$ph    = $field['placeholder'] ?? '';

		switch ( $field['type'] ) {

			case 'text':
			case 'password':
			case 'number':
				printf(
					'<input type="%s" id="%s" name="%s" value="%s" class="regular-text" placeholder="%s">',
					esc_attr( $field['type'] ),
					esc_attr( $key ),
					esc_attr( $key ),
					esc_attr( $value ),
					esc_attr( $ph )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" class="large-text" rows="4" placeholder="%s">%s</textarea>',
					esc_attr( $key ),
					esc_attr( $key ),
					esc_attr( $ph ),
					esc_textarea( $value )
				);
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
					esc_attr( $key ),
					esc_attr( $key ),
					checked( $value, '1', false ),
					esc_html( $desc )
				);
				return; // Description is inline for checkbox.

			case 'checkboxes':
				$values = is_array( $value ) ? $value : [];
				foreach ( ( $field['options'] ?? [] ) as $opt_val => $opt_label ) {
					printf(
						'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>',
						esc_attr( $key ),
						esc_attr( $opt_val ),
						checked( in_array( (string) $opt_val, $values, true ), true, false ),
						esc_html( $opt_label )
					);
				}
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $key ), esc_attr( $key ) );
				foreach ( ( $field['options'] ?? [] ) as $opt_val => $opt_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt_val ),
						selected( $value, $opt_val, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;
		}

		if ( '' !== $desc && 'checkbox' !== $field['type'] ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Render the full settings page for this module.
	 */
	public function render_settings_page(): void {
		$page = 'wpsk_' . $this->get_id();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_name() ); ?></h1>
			<p class="description"><?php echo esc_html( $this->get_description() ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( $page );
				do_settings_sections( $page );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register a standalone settings page (when not running as part of the suite).
	 */
	public function register_standalone_menu(): void {
		add_options_page(
			$this->get_name(),
			$this->get_name(),
			'manage_options',
			'wpsk-' . $this->get_id(),
			[ $this, 'render_settings_page' ]
		);
	}

	/* ----------------------------------------------------------
	 * Internal helpers
	 * ---------------------------------------------------------- */

	/**
	 * Map field type to WP register_setting type.
	 */
	private function wp_type( string $field_type ): string {
		if ( 'checkboxes' === $field_type ) {
			return 'array';
		}
		if ( 'number' === $field_type ) {
			return 'integer';
		}
		if ( 'checkbox' === $field_type ) {
			return 'string';
		}
		return 'string';
	}

	/**
	 * Build a sanitizer callback for a field.
	 */
	private function get_sanitizer( array $field ): callable {
		switch ( $field['type'] ) {
			case 'checkboxes':
				return function ( $value ) {
					return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
				};
			case 'textarea':
				return 'sanitize_textarea_field';
			case 'number':
				return 'absint';
			default:
				return 'sanitize_text_field';
		}
	}
}
