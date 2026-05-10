<?php
/**
 * WPSK Performance — Remove WordPress bloat and optimize loading.
 *
 * Renamed from "Performance Cleanup" to "Performance Optimizer"
 * to avoid confusion about what the plugin does.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Performance extends WPSK_Module {

	public function get_id(): string {
		return 'performance';
	}

	public function get_name(): string {
		return __( 'Performance Optimizer', 'wpsk-performance' );
	}

	public function get_description(): string {
		return __( 'Remove unnecessary WordPress scripts, optimize loading, and reduce page weight for faster sites.', 'wpsk-performance' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'             => 'disable_emojis',
				'label'          => __( 'Remove Emoji Scripts', 'wpsk-performance' ),
				'type'           => 'checkbox',
				'description'    => __( 'Removes the emoji detection script (wp-emoji-release.min.js) from every page. Saves ~16 KB. Emojis still work — browsers render them natively.', 'wpsk-performance' ),
				'default'        => '1',
				'importance'     => 'high',
			],
			[
				'id'          => 'disable_embeds',
				'label'       => __( 'Remove oEmbed/wp-embed', 'wpsk-performance' ),
				'type'        => 'checkbox',
				'description' => __( 'Removes wp-embed.min.js. Disable if you don\'t embed other WordPress posts in your content.', 'wpsk-performance' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'disable_xmlrpc',
				'label'       => __( 'Disable XML-RPC', 'wpsk-performance' ),
				'type'        => 'checkbox',
				'description' => __( 'Disables the XML-RPC API. This is a common brute-force target and most sites don\'t need it. Only keep it if you use the WordPress mobile app or Jetpack.', 'wpsk-performance' ),
				'default'     => '1',
				'importance'  => 'high',
			],
			[
				'id'          => 'disable_google_fonts',
				'label'       => __( 'Remove Google Fonts', 'wpsk-performance' ),
				'type'        => 'checkbox',
				'description' => __( 'Dequeue Google Fonts loaded by the theme/plugins. Use this if you self-host your fonts (recommended for GDPR and performance).', 'wpsk-performance' ),
				'default'     => '0',
				'importance'  => 'medium',
			],
			[
				'id'          => 'disable_jquery_migrate',
				'label'       => __( 'Remove jQuery Migrate', 'wpsk-performance' ),
				'type'        => 'checkbox',
				'description' => __( 'Removes the jQuery Migrate compatibility script. Safe for modern themes, but may break very old plugins.', 'wpsk-performance' ),
				'default'     => '0',
				'importance'  => 'low',
			],
			[
				'id'          => 'heartbeat',
				'label'       => __( 'Heartbeat Throttle', 'wpsk-performance' ),
				'type'        => 'select',
				'default'     => '60',
				'options'     => [
					'15'  => __( '15 seconds (default)', 'wpsk-performance' ),
					'30'  => __( '30 seconds', 'wpsk-performance' ),
					'60'  => __( '60 seconds (recommended)', 'wpsk-performance' ),
					'120' => __( '120 seconds', 'wpsk-performance' ),
					'0'   => __( 'Disable on frontend', 'wpsk-performance' ),
				],
				'description' => __( 'Reduce the frequency of WordPress Heartbeat API calls. Saves server resources.', 'wpsk-performance' ),
				'importance'  => 'medium',
			],
			[
				'id'          => 'limit_revisions',
				'label'       => __( 'Post Revisions Limit', 'wpsk-performance' ),
				'type'        => 'number',
				'default'     => '0',
				'description' => __( 'Max revisions per post. 0 = WordPress default (unlimited). Set to 5-10 to save database space.', 'wpsk-performance' ),
				'min'         => 0,
				'max'         => 100,
				'importance'  => 'low',
			],
			[
				'id'          => 'clean_head',
				'label'       => __( 'Clean wp_head', 'wpsk-performance' ),
				'type'        => 'checkbox',
				'description' => __( 'Remove RSD link, WLW manifest, WordPress generator tag, shortlinks, and feed links from the HTML head.', 'wpsk-performance' ),
				'default'     => '1',
				'importance'  => 'high',
			],
		];
	}

	public function get_help_html(): string {
		return '<strong>' . esc_html__( 'What this does:', 'wpsk-performance' ) . '</strong> '
			. esc_html__( 'Each option removes unnecessary code that WordPress loads by default. Green "Recommended" options are safe for almost all sites. Yellow "Optional" ones depend on your setup. Red "Advanced" ones should only be changed if you know what they do.', 'wpsk-performance' );
	}

	protected function init(): void {
		// Emojis.
		if ( '1' === $this->get_option( 'disable_emojis' ) ) {
			add_action( 'init', [ $this, 'disable_emojis' ] );
		}

		// Embeds.
		if ( '1' === $this->get_option( 'disable_embeds' ) ) {
			add_action( 'wp_footer', function () {
				wp_dequeue_script( 'wp-embed' );
			} );
		}

		// XML-RPC.
		if ( '1' === $this->get_option( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		// Google Fonts.
		if ( '1' === $this->get_option( 'disable_google_fonts' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_google_fonts' ], 100 );
		}

		// jQuery Migrate.
		if ( '1' === $this->get_option( 'disable_jquery_migrate' ) ) {
			add_action( 'wp_default_scripts', function ( $scripts ) {
				if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
					$scripts->registered['jquery']->deps = array_diff(
						$scripts->registered['jquery']->deps,
						[ 'jquery-migrate' ]
					);
				}
			} );
		}

		// Heartbeat.
		$heartbeat = $this->get_option( 'heartbeat' );
		if ( '0' === $heartbeat ) {
			add_action( 'init', function () {
				if ( ! is_admin() ) {
					wp_deregister_script( 'heartbeat' );
				}
			}, 1 );
		} elseif ( $heartbeat && '15' !== $heartbeat ) {
			add_filter( 'heartbeat_settings', function ( $settings ) use ( $heartbeat ) {
				$settings['interval'] = (int) $heartbeat;
				return $settings;
			} );
		}

		// Revisions limit.
		$revisions = (int) $this->get_option( 'limit_revisions' );
		if ( $revisions > 0 && ! defined( 'WP_POST_REVISIONS' ) ) {
			define( 'WP_POST_REVISIONS', $revisions );
		}

		// Clean head.
		if ( '1' === $this->get_option( 'clean_head' ) ) {
			add_action( 'after_setup_theme', [ $this, 'clean_wp_head' ] );
		}
	}

	public function disable_emojis(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : $plugins;
		} );
	}

	public function dequeue_google_fonts(): void {
		global $wp_styles;
		if ( empty( $wp_styles->registered ) ) return;
		foreach ( $wp_styles->registered as $handle => $style ) {
			$src = $style->src ?? '';
			if ( $src && ( strpos( (string) $src, 'fonts.googleapis.com' ) !== false || strpos( (string) $src, 'fonts.gstatic.com' ) !== false ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
	}

	public function clean_wp_head(): void {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_under_10' );
		add_filter( 'the_generator', '__return_empty_string' );
	}
}
