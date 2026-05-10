<?php
/**
 * WPSK Module — Abstract base class for all modules.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'WPSK_Module' ) ) { return; }

abstract class WPSK_Module {

	protected $plugin_path = '';
	protected $plugin_url  = '';

	/* ----------------------------------------------------------
	 * Identity (implement in each module)
	 * ---------------------------------------------------------- */

	abstract public function get_id(): string;
	abstract public function get_name(): string;
	abstract public function get_description(): string;
	abstract protected function init(): void;
	abstract public function get_settings_fields(): array;

	/* ----------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------- */

	public function boot( string $plugin_path, string $plugin_url ): void {
		$this->plugin_path = $plugin_path;
		$this->plugin_url  = $plugin_url;

		$domain = 'wpsk-' . $this->get_id();
		$locale = determine_locale();
		$mofile = $this->plugin_path . 'modules/' . $this->get_id() . '/languages/' . $domain . '-' . $locale . '.mo';
		if ( file_exists( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );

		if ( ! WPSK_Core::instance()->is_suite() ) {
			add_action( 'admin_menu', [ $this, 'register_standalone_menu' ] );
		}

		$this->init();
	}

	/* ----------------------------------------------------------
	 * Settings helpers
	 * ---------------------------------------------------------- */

	public function option_key( string $field_id ): string {
		return 'wpsk_' . str_replace( '-', '_', $this->get_id() ) . '_' . $field_id;
	}

	public function get_option( string $field_id ) {
		$fields  = $this->get_settings_fields();
		$default = '';
		foreach ( $fields as $field ) {
			if ( $field['id'] === $field_id ) {
				$default = $field['default'] ?? '';
				break;
			}
		}
		return get_option( $this->option_key( $field_id ), $default );
	}

	/* ----------------------------------------------------------
	 * Settings registration
	 * ---------------------------------------------------------- */

	public function register_settings(): void {
		$section_id = 'wpsk_' . $this->get_id() . '_section';
		$page_slug  = 'wpsk-' . $this->get_id();

		add_settings_section( $section_id, '', '__return_false', $page_slug );

		foreach ( $this->get_settings_fields() as $field ) {
			$option_key = $this->option_key( $field['id'] );
			$type       = $field['type'] ?? 'text';

			$sanitize = 'sanitize_text_field';
			if ( 'checkbox' === $type ) {
				$sanitize = function ( $v ) { return $v ? '1' : '0'; };
			} elseif ( 'checkboxes' === $type ) {
				$sanitize = function ( $v ) { return is_array( $v ) ? array_map( 'sanitize_key', $v ) : []; };
			} elseif ( 'number' === $type ) {
				$sanitize = 'absint';
			} elseif ( 'textarea' === $type ) {
				$sanitize = 'sanitize_textarea_field';
			}

			register_setting( $page_slug, $option_key, [
				'sanitize_callback' => $sanitize,
				'default'           => $field['default'] ?? '',
			] );

			add_settings_field(
				$option_key,
				$this->build_field_label( $field ),
				function () use ( $field, $option_key ) {
					$this->render_field( $field, $option_key );
				},
				$page_slug,
				$section_id
			);
		}
	}

	/**
	 * Build field label with optional importance badge.
	 */
	protected function build_field_label( array $field ): string {
		$label = esc_html( $field['label'] );
		if ( ! empty( $field['importance'] ) ) {
			$level = $field['importance']; // high | medium | low
			$text  = [
				'high'   => __( 'Recommended', 'wpsk' ),
				'medium' => __( 'Optional', 'wpsk' ),
				'low'    => __( 'Advanced', 'wpsk' ),
			];
			$label .= ' <span class="wpsk-importance wpsk-importance-' . esc_attr( $level ) . '">'
				. esc_html( $text[ $level ] ?? $level ) . '</span>';
		}
		return $label;
	}

	/**
	 * Render a single field. Null-safe for all get_option calls.
	 */
	protected function render_field( array $field, string $option_key ): void {
		$type    = $field['type'] ?? 'text';
		$value   = get_option( $option_key, $field['default'] ?? '' );
		$desc    = $field['description'] ?? '';
		$ph      = $field['placeholder'] ?? '';

		// Null-safety: ensure $value is never null for string operations.
		if ( null === $value ) {
			$value = $field['default'] ?? '';
		}

		switch ( $type ) {
			case 'text':
			case 'password':
			case 'url':
				printf(
					'<input type="%s" name="%s" value="%s" placeholder="%s" class="regular-text" />',
					esc_attr( $type ),
					esc_attr( $option_key ),
					esc_attr( (string) $value ),
					esc_attr( $ph )
				);
				break;

			case 'number':
				printf(
					'<input type="number" name="%s" value="%s" class="small-text" min="%s" max="%s" />',
					esc_attr( $option_key ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? '0' ) ),
					esc_attr( (string) ( $field['max'] ?? '' ) )
				);
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
					esc_attr( $option_key ),
					checked( $value, '1', false ),
					esc_html( $field['checkbox_label'] ?? __( 'Enable', 'wpsk' ) )
				);
				break;

			case 'checkboxes':
				$selected = is_array( $value ) ? $value : [];
				foreach ( ( $field['options'] ?? [] ) as $opt_val => $opt_label ) {
					printf(
						'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="%s[]" value="%s" %s /> %s</label>',
						esc_attr( $option_key ),
						esc_attr( $opt_val ),
						checked( in_array( $opt_val, $selected, true ), true, false ),
						esc_html( $opt_label )
					);
				}
				break;

			case 'select':
				printf( '<select name="%s">', esc_attr( $option_key ) );
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

			case 'textarea':
				printf(
					'<textarea name="%s" rows="5" class="large-text" placeholder="%s">%s</textarea>',
					esc_attr( $option_key ),
					esc_attr( $ph ),
					esc_textarea( (string) $value )
				);
				break;

			case 'html':
				// Custom HTML block (for help boxes, etc.)
				echo wp_kses_post( $field['html'] ?? '' );
				break;
		}

		if ( $desc ) {
			echo '<p class="wpsk-field-description">' . wp_kses_post( $desc ) . '</p>';
		}
	}

	/* ----------------------------------------------------------
	 * Settings page rendering
	 * ---------------------------------------------------------- */

	public function register_standalone_menu(): void {
		add_options_page(
			$this->get_name(),
			$this->get_name(),
			'manage_options',
			'wpsk-' . $this->get_id(),
			[ $this, 'render_settings_page' ]
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page_slug = 'wpsk-' . $this->get_id();

		// Show setup banner if just enabled.
		$show_setup = isset( $_GET['wpsk_setup'] ) && '1' === $_GET['wpsk_setup'];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $this->get_name() ) . '</h1>';

		if ( $show_setup ) {
			echo '<div class="wpsk-setup-banner">';
			echo '<strong>👋 ' . esc_html__( 'Welcome! Let\'s set up this module.', 'wpsk' ) . '</strong> ';
			echo esc_html__( 'Fill in the settings below to get started.', 'wpsk' );
			echo '</div>';
		}

		echo '<p>' . esc_html( $this->get_description() ) . '</p>';

		// Module-specific help section.
		$help = $this->get_help_html();
		if ( $help ) {
			echo '<div class="wpsk-help-box">' . wp_kses_post( $help ) . '</div>';
		}

		echo '<form method="post" action="options.php">';
		settings_fields( $page_slug );
		do_settings_sections( $page_slug );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Override in modules to show contextual help above the settings form.
	 * Return HTML string or empty string.
	 */
	public function get_help_html(): string {
		return '';
	}
}
