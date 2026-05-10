<?php
/**
 * WPSK Accessibility — Frontend accessibility toolbar.
 *
 * Fixes:
 * - Increase/decrease text buttons: icon now centered, text always 2 rows
 * - Toggle button: background removed, icon only
 * - Added settings option for custom logo icon URL
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
				'id'          => 'position',
				'label'       => __( 'Toolbar Position', 'wpsk-accessibility' ),
				'type'        => 'select',
				'default'     => 'left',
				'options'     => [
					'left'  => __( 'Bottom Left', 'wpsk-accessibility' ),
					'right' => __( 'Bottom Right', 'wpsk-accessibility' ),
				],
			],
			[
				'id'          => 'icon_url',
				'label'       => __( 'Custom Icon Image', 'wpsk-accessibility' ),
				'type'        => 'url',
				'description' => __( 'URL to a custom accessibility icon (recommended: 48×48 PNG/WebP). Leave empty for the default icon.', 'wpsk-accessibility' ),
				'default'     => '',
				'placeholder' => 'https://example.com/a11y-icon.webp',
			],
			[
				'id'          => 'statement_url',
				'label'       => __( 'Accessibility Statement URL', 'wpsk-accessibility' ),
				'type'        => 'url',
				'description' => __( 'Link to your accessibility statement page.', 'wpsk-accessibility' ),
				'default'     => '',
				'placeholder' => '/accessibility-statement/',
			],
			[
				'id'          => 'enable_contrast',
				'label'       => __( 'High Contrast', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable high contrast mode toggle', 'wpsk-accessibility' ),
			],
			[
				'id'          => 'enable_greyscale',
				'label'       => __( 'Greyscale', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable greyscale mode toggle', 'wpsk-accessibility' ),
			],
			[
				'id'          => 'enable_readable_font',
				'label'       => __( 'Readable Font', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable readable font toggle (Arial/Helvetica)', 'wpsk-accessibility' ),
			],
			[
				'id'          => 'enable_animations',
				'label'       => __( 'Stop Animations', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable stop animations toggle', 'wpsk-accessibility' ),
			],
			[
				'id'          => 'enable_links',
				'label'       => __( 'Highlight Links', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable highlight links toggle', 'wpsk-accessibility' ),
			],
			[
				'id'          => 'enable_keyboard',
				'label'       => __( 'Keyboard Focus', 'wpsk-accessibility' ),
				'type'        => 'checkbox',
				'default'     => '1',
				'checkbox_label' => __( 'Enable keyboard focus indicator', 'wpsk-accessibility' ),
			],
		];
	}

	protected function init(): void {
		if ( is_admin() ) { return; }

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_widget' ], 9999 );
		add_action( 'wp_head', [ $this, 'render_styles' ], 9999 );
	}

	/* ── Assets ─────────────────────────────────────────────── */

	public function enqueue_assets(): void {
		$js_file = $this->plugin_path . 'modules/accessibility/assets/wpsk-a11y.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'wpsk-a11y',
				$this->plugin_url . 'modules/accessibility/assets/wpsk-a11y.js',
				[],
				filemtime( $js_file ),
				true
			);
			// Pass config to JS.
			wp_localize_script( 'wpsk-a11y', 'wpskA11yConfig', [
				'features' => $this->get_enabled_features(),
			] );
		}
	}

	private function get_enabled_features(): array {
		$features = [];
		$checks = [
			'enable_contrast'      => 'contrast',
			'enable_greyscale'     => 'greyscale',
			'enable_readable_font' => 'readable_font',
			'enable_animations'    => 'animations',
			'enable_links'         => 'links',
			'enable_keyboard'      => 'keyboard',
		];
		foreach ( $checks as $option => $feature ) {
			if ( '1' === $this->get_option( $option ) ) {
				$features[] = $feature;
			}
		}
		return $features;
	}

	/* ── Widget HTML ────────────────────────────────────────── */

	public function render_widget(): void {
		$position      = $this->get_option( 'position' );
		$statement_url = $this->get_option( 'statement_url' );
		$features      = $this->get_enabled_features();

		$pos_class = 'right' === $position ? 'wpsk-a11y-right' : 'wpsk-a11y-left';

		echo '<div id="wpsk-a11y-widget" class="' . esc_attr( $pos_class ) . '" role="region" aria-label="' . esc_attr__( 'Accessibility Tools', 'wpsk-accessibility' ) . '">';

		// Toggle button.
		echo '<button id="wpsk-a11y-toggle" type="button" aria-expanded="false" aria-controls="wpsk-a11y-panel" title="' . esc_attr__( 'Accessibility', 'wpsk-accessibility' ) . '">';
		echo '<span class="screen-reader-text">' . esc_html__( 'Open accessibility tools', 'wpsk-accessibility' ) . '</span>';
		echo '</button>';

		// Panel.
		echo '<div id="wpsk-a11y-panel" aria-hidden="true" role="dialog" aria-label="' . esc_attr__( 'Accessibility Tools', 'wpsk-accessibility' ) . '">';

		echo '<div class="wpsk-a11y-header">';
		echo '<span>' . esc_html__( 'Accessibility', 'wpsk-accessibility' ) . '</span>';
		echo '<button type="button" class="wpsk-a11y-close" aria-label="' . esc_attr__( 'Close', 'wpsk-accessibility' ) . '">✕</button>';
		echo '</div>';

		// Font size controls — FIX: structured as flexbox column with icon centered.
		echo '<div class="wpsk-a11y-zoom-row">';
		echo '<button type="button" id="wpsk-a11y-zoom-in" class="wpsk-a11y-zoom-btn">';
		echo '<span class="wpsk-a11y-zoom-icon">＋</span>';
		echo '<span class="wpsk-a11y-zoom-label">' . esc_html__( 'Enlarge', 'wpsk-accessibility' ) . '<br>' . esc_html__( 'Text', 'wpsk-accessibility' ) . '</span>';
		echo '</button>';
		echo '<button type="button" id="wpsk-a11y-zoom-out" class="wpsk-a11y-zoom-btn">';
		echo '<span class="wpsk-a11y-zoom-icon">−</span>';
		echo '<span class="wpsk-a11y-zoom-label">' . esc_html__( 'Reduce', 'wpsk-accessibility' ) . '<br>' . esc_html__( 'Text', 'wpsk-accessibility' ) . '</span>';
		echo '</button>';
		echo '</div>';

		// Toggle features.
		$feature_labels = [
			'contrast'      => __( 'High Contrast', 'wpsk-accessibility' ),
			'greyscale'     => __( 'Greyscale', 'wpsk-accessibility' ),
			'readable_font' => __( 'Readable Font', 'wpsk-accessibility' ),
			'animations'    => __( 'Stop Animations', 'wpsk-accessibility' ),
			'links'         => __( 'Highlight Links', 'wpsk-accessibility' ),
			'keyboard'      => __( 'Keyboard Focus', 'wpsk-accessibility' ),
		];
		$feature_classes = [
			'contrast'      => 'a11y-high-contrast',
			'greyscale'     => 'a11y-greyscale',
			'readable_font' => 'a11y-readable-font',
			'animations'    => 'a11y-no-animations',
			'links'         => 'a11y-highlight-links',
			'keyboard'      => 'a11y-keyboard-focus',
		];

		foreach ( $features as $feature ) {
			if ( isset( $feature_labels[ $feature ] ) ) {
				echo '<button type="button" class="wpsk-a11y-toggle-btn" data-class="' . esc_attr( $feature_classes[ $feature ] ) . '">';
				echo esc_html( $feature_labels[ $feature ] );
				echo '</button>';
			}
		}

		// Reset button.
		echo '<button type="button" id="wpsk-a11y-reset" class="wpsk-a11y-reset-btn">' . esc_html__( 'Reset All', 'wpsk-accessibility' ) . '</button>';

		// Statement link.
		if ( $statement_url ) {
			echo '<a href="' . esc_url( $statement_url ) . '" class="wpsk-a11y-statement-link" target="_blank">' . esc_html__( 'Accessibility Statement', 'wpsk-accessibility' ) . '</a>';
		}

		echo '</div>'; // panel
		echo '</div>'; // widget
	}

	/* ── Styles ─────────────────────────────────────────────── */

	public function render_styles(): void {
		$icon_url = $this->get_option( 'icon_url' );
		$position = $this->get_option( 'position' );

		// Default icon: Unicode wheelchair symbol via SVG data URI.
		if ( empty( $icon_url ) ) {
			$icon_url = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm3.5 20a4.5 4.5 0 0 1-4.28-5.89L8.5 16H6a1 1 0 0 1 0-2h3.24l3.54-1.18.22-.07V9a1 1 0 0 1 2 0v4.5l-3.54 1.18A2.5 2.5 0 1 0 15.5 17a1 1 0 0 1 0 2v3z'/%3E%3C/svg%3E";
		}

		$pos_side = 'right' === $position ? 'right' : 'left';
		$panel_offset = 'right' === $position ? 'right:52px' : 'left:52px';

		?>
		<style data-no-optimize="1">
/* ── Widget container ── */
#wpsk-a11y-widget{position:fixed;<?php echo $pos_side; ?>:8px;bottom:120px;z-index:1000000;line-height:1.4;direction:ltr}
.wpsk-a11y-right{direction:rtl}

/* ── Toggle button — FIX: no background, transparent, icon only ── */
#wpsk-a11y-toggle{
	width:44px;height:44px;min-width:44px;min-height:44px;
	padding:0;margin:0;border:none !important;border-radius:50% !important;
	cursor:pointer;display:block !important;
	background:transparent url('<?php echo esc_url( $icon_url ); ?>') center/36px 36px no-repeat !important;
	box-shadow:none !important;
	transition:transform .2s;
}
#wpsk-a11y-toggle:hover,#wpsk-a11y-toggle:focus-visible{transform:scale(1.1)}
#wpsk-a11y-toggle *{display:none !important}

/* ── Panel ── */
#wpsk-a11y-panel{
	position:fixed;<?php echo $panel_offset; ?>;bottom:80px;
	width:260px;max-height:calc(100vh - 160px);
	background:#fff;border:1px solid #ddd;border-radius:12px;
	box-shadow:0 8px 32px rgba(0,0,0,.15);padding:16px;
	display:none;overflow-y:auto;direction:ltr;z-index:1000001;
}
#wpsk-a11y-panel[aria-hidden="false"]{display:block}

.wpsk-a11y-header{display:flex;justify-content:space-between;align-items:center;padding-bottom:10px;margin-bottom:12px;border-bottom:2px solid #0056b3;font-weight:700;font-size:15px;color:#0056b3}
.wpsk-a11y-close{background:none;border:none;font-size:18px;cursor:pointer;color:#666;padding:4px}

/* ── Zoom buttons — FIX: icon centered, text always 2 rows ── */
.wpsk-a11y-zoom-row{display:flex;gap:8px;margin-bottom:12px}
.wpsk-a11y-zoom-btn{
	flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
	padding:10px 8px;border:2px solid #0056b3;border-radius:10px;
	background:#f0f6fc;cursor:pointer;transition:background .2s;
	min-height:70px;
}
.wpsk-a11y-zoom-btn:hover{background:#d4e4f7}
.wpsk-a11y-zoom-icon{
	display:flex;align-items:center;justify-content:center;
	width:28px;height:28px;border-radius:50%;
	background:#0056b3;color:#fff;font-size:18px;font-weight:700;
	margin-bottom:6px;line-height:1;
}
.wpsk-a11y-zoom-label{
	font-size:13px;font-weight:600;color:#0056b3;text-align:center;
	line-height:1.3;
	/* FIX: Force text to always wrap into 2 rows */
	white-space:normal;word-break:keep-all;
}

/* ── Toggle buttons ── */
.wpsk-a11y-toggle-btn{
	display:block;width:100%;padding:10px 14px;margin-bottom:6px;
	border:1px solid #ddd;border-radius:8px;background:#f9f9f9;
	cursor:pointer;font-size:13px;text-align:<?php echo $pos_side; ?>;
	transition:background .2s,border-color .2s;
}
.wpsk-a11y-toggle-btn:hover{background:#e9e9e9}
.wpsk-a11y-toggle-btn.active{background:#0056b3;color:#fff;border-color:#0056b3}

/* ── Reset ── */
.wpsk-a11y-reset-btn{
	display:block;width:100%;padding:10px;margin-top:10px;
	border:2px solid #dc3545;border-radius:8px;background:#fff;
	color:#dc3545;font-weight:700;cursor:pointer;font-size:13px;
}
.wpsk-a11y-reset-btn:hover{background:#dc3545;color:#fff}

.wpsk-a11y-statement-link{display:block;text-align:center;margin-top:10px;font-size:12px;color:#0056b3}

/* ── Accessibility modes (applied to html) ── */
html.a11y-high-contrast{filter:contrast(1.5) !important}
html.a11y-high-contrast #wpsk-a11y-widget{filter:none !important}

html.a11y-greyscale{filter:grayscale(100%) !important}
html.a11y-greyscale #wpsk-a11y-widget{filter:none !important}

html.a11y-readable-font *{font-family:Arial,Helvetica,sans-serif !important}

html.a11y-highlight-links a{background-color:#ff0 !important;color:#000 !important;text-decoration:underline !important;outline:2px solid #e6c800 !important}

html.a11y-keyboard-focus *:focus,html.a11y-keyboard-focus *:focus-visible{outline:4px solid #ffbf47 !important;outline-offset:2px !important}

html.a11y-no-animations *,html.a11y-no-animations *::before,html.a11y-no-animations *::after{
	animation:none !important;transition:none !important;scroll-behavior:auto !important
}

/* ── Mobile ── */
@media(max-width:768px){
	#wpsk-a11y-widget{bottom:80px}
	#wpsk-a11y-toggle{width:38px;height:38px;background-size:30px 30px !important}
	#wpsk-a11y-panel{<?php echo $panel_offset; ?>;width:calc(100vw - 70px);max-width:280px;bottom:60px}
	.wpsk-a11y-zoom-btn{min-height:60px;padding:8px 6px}
	.wpsk-a11y-zoom-icon{width:24px;height:24px;font-size:16px}
	.wpsk-a11y-zoom-label{font-size:12px}
}
		</style>
		<?php
	}
}
