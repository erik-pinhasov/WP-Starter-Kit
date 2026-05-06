<?php
/**
 * WPSK Accessibility — Frontend accessibility toolbar.
 *
 * Features: font scaling, high contrast, readable font, greyscale,
 * link underline/highlight, keyboard focus, stop animations, skip-to-content.
 *
 * @package    WPStarterKit
 * @subpackage Modules\Accessibility
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Accessibility extends WPSK_Module {

	public function get_id(): string {
		return 'accessibility';
	}

	public function get_name(): string {
		return __( 'Accessibility Toolbar', 'wpsk-accessibility' );
	}

	public function get_description(): string {
		return __( 'Add a frontend accessibility toolbar with font scaling, contrast modes, and keyboard navigation aids.', 'wpsk-accessibility' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'      => 'features',
				'label'   => __( 'Toolbar Features', 'wpsk-accessibility' ),
				'type'    => 'checkboxes',
				'default' => [ 'font_scale', 'readable_font', 'high_contrast', 'greyscale', 'underline_links', 'highlight_links', 'keyboard_focus', 'stop_animations' ],
				'options' => [
					'font_scale'      => __( 'Font size controls (zoom in/out)', 'wpsk-accessibility' ),
					'readable_font'   => __( 'Readable font (switch to Arial/sans-serif)', 'wpsk-accessibility' ),
					'high_contrast'   => __( 'High contrast mode', 'wpsk-accessibility' ),
					'greyscale'       => __( 'Greyscale mode', 'wpsk-accessibility' ),
					'underline_links' => __( 'Underline all links', 'wpsk-accessibility' ),
					'highlight_links' => __( 'Highlight links with yellow background', 'wpsk-accessibility' ),
					'keyboard_focus'  => __( 'Enhanced keyboard focus indicator', 'wpsk-accessibility' ),
					'stop_animations' => __( 'Stop animations', 'wpsk-accessibility' ),
				],
			],
			[
				'id'      => 'position',
				'label'   => __( 'Toolbar Position', 'wpsk-accessibility' ),
				'type'    => 'select',
				'default' => 'bottom-left',
				'options' => [
					'bottom-left'  => __( 'Bottom left', 'wpsk-accessibility' ),
					'bottom-right' => __( 'Bottom right', 'wpsk-accessibility' ),
					'top-left'     => __( 'Top left', 'wpsk-accessibility' ),
					'top-right'    => __( 'Top right', 'wpsk-accessibility' ),
				],
			],
			[
				'id'          => 'statement_url',
				'label'       => __( 'Accessibility Statement URL', 'wpsk-accessibility' ),
				'type'        => 'text',
				'description' => __( 'Link to your accessibility statement page. Leave empty to hide the link.', 'wpsk-accessibility' ),
				'default'     => '',
				'placeholder' => '/accessibility-statement/',
			],
			[
				'id'          => 'icon_color',
				'label'       => __( 'Toggle Button Color', 'wpsk-accessibility' ),
				'type'        => 'text',
				'description' => __( 'CSS color for the toolbar toggle button background. Default: #0056b3', 'wpsk-accessibility' ),
				'default'     => '#0056b3',
				'placeholder' => '#0056b3',
			],
		];
	}

	protected function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 9999 );
		add_action( 'wp_footer', [ $this, 'render_toolbar' ] );
		add_action( 'wp_head', [ $this, 'output_styles' ], 9999 );
	}

	/* ── Assets ──────────────────────────────────────────────── */

	public function enqueue_assets(): void {
		$js_path = $this->plugin_path . 'modules/accessibility/assets/wpsk-a11y.js';
		$js_url  = $this->plugin_url . 'modules/accessibility/assets/wpsk-a11y.js';
		$ver     = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

		wp_enqueue_script( 'wpsk-a11y', $js_url, [], $ver, true );

		add_filter( 'script_loader_tag', function ( string $tag, string $handle ): string {
			if ( 'wpsk-a11y' === $handle ) {
				return str_replace( '<script ', '<script defer data-no-optimize="1" data-no-minify="1" ', $tag );
			}
			return $tag;
		}, 20, 2 );

		// Pass feature config to JS.
		$features = $this->get_option( 'features' );
		wp_localize_script( 'wpsk-a11y', 'wpskA11yConfig', [
			'features'     => is_array( $features ) ? $features : [],
			'statementUrl' => esc_url( $this->get_option( 'statement_url' ) ),
			'i18n'         => [
				'openMenu'    => __( 'Open accessibility menu', 'wpsk-accessibility' ),
				'closeMenu'   => __( 'Close accessibility menu', 'wpsk-accessibility' ),
				'menuTitle'   => __( 'Accessibility Menu', 'wpsk-accessibility' ),
				'zoomIn'      => __( 'Increase text', 'wpsk-accessibility' ),
				'zoomOut'     => __( 'Decrease text', 'wpsk-accessibility' ),
				'readable'    => __( 'Readable font', 'wpsk-accessibility' ),
				'contrast'    => __( 'High contrast', 'wpsk-accessibility' ),
				'greyscale'   => __( 'Greyscale', 'wpsk-accessibility' ),
				'underline'   => __( 'Underline links', 'wpsk-accessibility' ),
				'highlight'   => __( 'Highlight links', 'wpsk-accessibility' ),
				'keyboard'    => __( 'Keyboard focus', 'wpsk-accessibility' ),
				'animations'  => __( 'Stop animations', 'wpsk-accessibility' ),
				'reset'       => __( 'Reset settings', 'wpsk-accessibility' ),
				'statement'   => __( 'Accessibility statement', 'wpsk-accessibility' ),
				'skipToMain'  => __( 'Skip to main content', 'wpsk-accessibility' ),
			],
		] );
	}

	/* ── Toolbar HTML ────────────────────────────────────────── */

	public function render_toolbar(): void {
		$features = $this->get_option( 'features' );
		if ( ! is_array( $features ) || empty( $features ) ) { return; }

		$statement = esc_url( $this->get_option( 'statement_url' ) );
		$is_rtl    = is_rtl();
		$dir       = $is_rtl ? 'rtl' : 'ltr';

		echo '<div id="wpsk-a11y-widget" dir="' . esc_attr( $dir ) . '">';
		echo '<button id="wpsk-a11y-toggle" type="button" aria-label="' . esc_attr__( 'Open accessibility menu', 'wpsk-accessibility' ) . '" aria-expanded="false" aria-controls="wpsk-a11y-panel">♿</button>';
		echo '<div id="wpsk-a11y-panel" role="dialog" aria-modal="true" aria-labelledby="wpsk-a11y-title" aria-hidden="true" tabindex="-1">';

		// Header.
		echo '<div class="wpsk-a11y-header"><h3 id="wpsk-a11y-title">♿ ' . esc_html__( 'Accessibility Menu', 'wpsk-accessibility' ) . '</h3>';
		echo '<button id="wpsk-a11y-close" type="button" aria-label="' . esc_attr__( 'Close accessibility menu', 'wpsk-accessibility' ) . '">✕</button></div>';

		// Body.
		echo '<div class="wpsk-a11y-body">';

		if ( in_array( 'font_scale', $features, true ) ) {
			echo '<div class="wpsk-a11y-row">';
			echo '<button class="wpsk-a11y-action" id="wpsk-a11y-zoom-in" type="button" aria-label="' . esc_attr__( 'Increase text', 'wpsk-accessibility' ) . '"><span class="wpsk-a11y-icon">+</span> ' . esc_html__( 'Increase text', 'wpsk-accessibility' ) . '</button>';
			echo '<button class="wpsk-a11y-action" id="wpsk-a11y-zoom-out" type="button" aria-label="' . esc_attr__( 'Decrease text', 'wpsk-accessibility' ) . '"><span class="wpsk-a11y-icon">−</span> ' . esc_html__( 'Decrease text', 'wpsk-accessibility' ) . '</button>';
			echo '</div>';
		}

		$toggles = [
			'readable_font'   => [ 'Aa', __( 'Readable font', 'wpsk-accessibility' ), 'wpsk-a11y-readable-font' ],
			'high_contrast'   => [ '◑', __( 'High contrast', 'wpsk-accessibility' ), 'wpsk-a11y-high-contrast' ],
			'greyscale'       => [ '◐', __( 'Greyscale', 'wpsk-accessibility' ), 'wpsk-a11y-greyscale' ],
			'underline_links' => [ '⎁', __( 'Underline links', 'wpsk-accessibility' ), 'wpsk-a11y-underline-links' ],
			'highlight_links' => [ '☀', __( 'Highlight links', 'wpsk-accessibility' ), 'wpsk-a11y-highlight-links' ],
			'keyboard_focus'  => [ '⌨', __( 'Keyboard focus', 'wpsk-accessibility' ), 'wpsk-a11y-keyboard-focus' ],
			'stop_animations' => [ '⏸', __( 'Stop animations', 'wpsk-accessibility' ), 'wpsk-a11y-no-animations' ],
		];

		foreach ( $toggles as $key => $t ) {
			if ( in_array( $key, $features, true ) ) {
				echo '<button class="wpsk-a11y-toggle-btn" type="button" data-class="' . esc_attr( $t[2] ) . '"><span class="wpsk-a11y-icon">' . $t[0] . '</span> ' . esc_html( $t[1] ) . '</button>';
			}
		}

		echo '<button id="wpsk-a11y-reset" type="button">↻ ' . esc_html__( 'Reset settings', 'wpsk-accessibility' ) . '</button>';

		if ( '' !== $statement ) {
			echo '<a id="wpsk-a11y-statement" href="' . esc_url( $statement ) . '">' . esc_html__( 'Accessibility statement', 'wpsk-accessibility' ) . ' ›</a>';
		}

		echo '</div></div></div>';
	}

	/* ── CSS ─────────────────────────────────────────────────── */

	public function output_styles(): void {
		$pos   = $this->get_option( 'position' );
		$color = sanitize_hex_color( $this->get_option( 'icon_color' ) ) ?: '#0056b3';

		// Position mapping.
		$widget_pos = 'left:4px;bottom:160px;';
		$panel_pos  = 'left:52px;bottom:80px;';
		switch ( $pos ) {
			case 'bottom-right':
				$widget_pos = 'right:4px;bottom:160px;';
				$panel_pos  = 'right:52px;bottom:80px;';
				break;
			case 'top-left':
				$widget_pos = 'left:4px;top:80px;';
				$panel_pos  = 'left:52px;top:80px;';
				break;
			case 'top-right':
				$widget_pos = 'right:4px;top:80px;';
				$panel_pos  = 'right:52px;top:80px;';
				break;
		}

		echo '<style data-no-optimize="1">';
		// Skip link.
		echo '.wpsk-a11y-skip{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;background:#000;color:#fff;text-decoration:none;z-index:9999999}';
		echo '.wpsk-a11y-skip:focus{position:fixed;width:auto;height:auto;clip:auto;white-space:normal;overflow:visible;top:10px;inset-inline-start:10px;padding:10px 15px;outline:#ffbf47 solid 3px;border-radius:8px}';

		// Widget container.
		echo '#wpsk-a11y-widget{position:fixed;' . $widget_pos . 'z-index:1000000;line-height:1}';

		// Toggle button.
		echo '#wpsk-a11y-toggle{width:40px;height:40px;padding:0;margin:0;border:none!important;border-radius:50%!important;display:block!important;cursor:pointer;background:' . esc_attr( $color ) . '!important;color:#fff;font-size:22px;line-height:40px;text-align:center;transition:box-shadow .2s,transform .2s}';
		echo '#wpsk-a11y-toggle:focus-visible,#wpsk-a11y-toggle:hover{box-shadow:0 6px 20px rgba(0,0,0,.25);transform:scale(1.06)}';

		// Panel.
		echo '#wpsk-a11y-panel{position:fixed;' . $panel_pos . 'width:240px;max-width:calc(100vw - 70px);max-height:calc(100vh - 120px);overflow-y:auto;background:#fff;color:#1a1a1a;border:none;box-shadow:0 10px 40px rgba(0,0,0,.22);border-radius:16px;padding:0;opacity:0;pointer-events:none;visibility:hidden;transition:opacity .25s}';
		echo '#wpsk-a11y-panel.open{opacity:1;pointer-events:auto;visibility:visible}';

		// Header.
		echo '.wpsk-a11y-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:linear-gradient(135deg,' . esc_attr( $color ) . ' 0,#003d82 100%);border-radius:16px 16px 0 0}';
		echo '.wpsk-a11y-header h3{margin:0;font-size:18px;color:#fff!important;letter-spacing:.2px}';
		echo '#wpsk-a11y-close{background:none;border:none;font-size:18px;cursor:pointer;color:#fff;padding:2px;line-height:1}';

		// Body.
		echo '.wpsk-a11y-body{padding:14px 16px 16px}';
		echo '.wpsk-a11y-row{display:flex;gap:8px;margin-bottom:10px}';

		// Action buttons (zoom).
		echo '.wpsk-a11y-action{flex:1;background:#eef4fb;border:1.5px solid #c5d9f0;color:' . esc_attr( $color ) . ';padding:10px 6px;border-radius:10px;cursor:pointer;text-align:center;font-size:14px;transition:.15s}';
		echo '.wpsk-a11y-action:hover{background:#d9e8f7}';
		echo '.wpsk-a11y-action .wpsk-a11y-icon{display:inline-block;width:20px;height:20px;line-height:20px;background:' . esc_attr( $color ) . ';color:#fff;border-radius:50%;font-size:14px;vertical-align:middle;text-align:center;margin-inline-end:4px}';

		// Toggle buttons.
		echo '.wpsk-a11y-toggle-btn{display:block;width:100%;background:#f7f9fc;border:1.5px solid #dce4ef;color:#2c3e50;padding:10px 14px;margin-bottom:6px;border-radius:10px;cursor:pointer;font-size:14px;text-align:start;transition:.15s;border-inline-start:3px solid #5b9bd5}';
		echo '.wpsk-a11y-toggle-btn:hover{background:#e8f0fa}';
		echo '.wpsk-a11y-toggle-btn.active{background:' . esc_attr( $color ) . ';color:#fff;border-color:#004494}';
		echo '.wpsk-a11y-toggle-btn .wpsk-a11y-icon{display:inline-block;width:22px;height:22px;line-height:22px;background:#e0ecf7;color:' . esc_attr( $color ) . ';border-radius:6px;font-size:14px;text-align:center;vertical-align:middle;margin-inline-end:8px;transition:.15s}';
		echo '.wpsk-a11y-toggle-btn.active .wpsk-a11y-icon{background:rgba(255,255,255,.2);color:#fff}';

		// Reset & statement.
		echo '#wpsk-a11y-reset{display:block;width:100%;background:#fff5f5;border:1.5px solid #f0c0c5;color:#c0392b;padding:10px;margin-top:10px;border-radius:10px;text-align:center;font-size:14px;cursor:pointer}';
		echo '#wpsk-a11y-reset:hover{background:#ffe8e8}';
		echo '#wpsk-a11y-statement{display:block;margin-top:10px;padding:8px;color:' . esc_attr( $color ) . ';background:none;border:1.5px solid #dce4ef;border-radius:10px;font-size:12px;text-align:center;text-decoration:none}';
		echo '#wpsk-a11y-statement:hover{background:#eef4fb}';

		// Accessibility mode styles.
		echo 'html.wpsk-a11y-readable-font,html.wpsk-a11y-readable-font *:not(#wpsk-a11y-widget *):not([class*="icon"]):not([class*="fa-"]){font-family:Arial,Helvetica,sans-serif!important}';
		echo 'html.wpsk-a11y-high-contrast body,html.wpsk-a11y-high-contrast article,html.wpsk-a11y-high-contrast section,html.wpsk-a11y-high-contrast header,html.wpsk-a11y-high-contrast footer,html.wpsk-a11y-high-contrast nav,html.wpsk-a11y-high-contrast main,html.wpsk-a11y-high-contrast aside{background-color:#000!important;background-image:none!important}';
		echo 'html.wpsk-a11y-high-contrast p,html.wpsk-a11y-high-contrast span,html.wpsk-a11y-high-contrast div,html.wpsk-a11y-high-contrast h1,html.wpsk-a11y-high-contrast h2,html.wpsk-a11y-high-contrast h3,html.wpsk-a11y-high-contrast h4,html.wpsk-a11y-high-contrast h5,html.wpsk-a11y-high-contrast h6,html.wpsk-a11y-high-contrast li,html.wpsk-a11y-high-contrast td,html.wpsk-a11y-high-contrast th,html.wpsk-a11y-high-contrast label,html.wpsk-a11y-high-contrast body{color:#fff!important}';
		echo 'html.wpsk-a11y-high-contrast a:not(#wpsk-a11y-widget a){color:#ff0!important;text-decoration:underline!important}';
		echo 'html.wpsk-a11y-high-contrast input:not(#wpsk-a11y-widget input),html.wpsk-a11y-high-contrast select:not(#wpsk-a11y-widget select),html.wpsk-a11y-high-contrast textarea:not(#wpsk-a11y-widget textarea){background:#111!important;color:#fff!important;border:1px solid #888!important}';
		echo 'html.wpsk-a11y-high-contrast button:not(#wpsk-a11y-widget button),html.wpsk-a11y-high-contrast .button,html.wpsk-a11y-high-contrast .btn{background:#111!important;color:#ff0!important;border:2px solid #ff0!important}';
		echo 'html.wpsk-a11y-high-contrast #wpsk-a11y-panel{background:#1a1a1a!important}';
		echo 'html.wpsk-a11y-high-contrast #wpsk-a11y-panel .wpsk-a11y-toggle-btn,html.wpsk-a11y-high-contrast #wpsk-a11y-panel .wpsk-a11y-action{background:#222!important;color:#fff!important;border-color:#555!important}';
		echo 'html.wpsk-a11y-greyscale{filter:grayscale(100%)!important}';
		echo 'html.wpsk-a11y-greyscale #wpsk-a11y-widget{filter:none!important}';
		echo 'html.wpsk-a11y-underline-links a{text-decoration:underline!important}';
		echo 'html.wpsk-a11y-highlight-links a{background-color:#ff0!important;color:#000!important;outline:#e6c800 solid 2px!important}';
		echo 'html.wpsk-a11y-keyboard-focus :focus,html.wpsk-a11y-keyboard-focus :focus-visible{outline:#ffbf47 solid 4px!important;outline-offset:2px!important}';
		echo 'html.wpsk-a11y-no-animations *,html.wpsk-a11y-no-animations *::before,html.wpsk-a11y-no-animations *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}';

		// Responsive.
		echo '@media(max-width:768px){#wpsk-a11y-toggle{width:36px;height:36px;font-size:20px;line-height:36px}#wpsk-a11y-panel{width:280px;max-width:calc(100vw - 60px)}.wpsk-a11y-toggle-btn,.wpsk-a11y-action,#wpsk-a11y-reset{font-size:16px;padding:11px 14px}}';
		echo '</style>';
	}
}
