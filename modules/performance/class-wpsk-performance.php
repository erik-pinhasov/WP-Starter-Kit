<?php
/**
 * WPSK Performance Cleanup — Remove WordPress frontend bloat.
 *
 * @package    WPStarterKit
 * @subpackage Modules\Performance
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSK_Performance extends WPSK_Module {

	/* ----------------------------------------------------------
	 * Module identity
	 * ---------------------------------------------------------- */

	public function get_id(): string {
		return 'performance';
	}

	public function get_name(): string {
		return __( 'Performance Cleanup', 'wpsk-performance' );
	}

	public function get_description(): string {
		return __( 'Remove unused WordPress scripts, styles, and meta tags to improve page load speed.', 'wpsk-performance' );
	}

	/* ----------------------------------------------------------
	 * Settings definition
	 * ---------------------------------------------------------- */

	public function get_settings_fields(): array {
		return [
			[
				'id'      => 'head_cleanup',
				'label'   => __( 'Head Cleanup', 'wpsk-performance' ),
				'type'    => 'checkboxes',
				'default' => [ 'shortlink', 'adjacent', 'rest_link', 'oembed_link' ],
				'options' => [
					'shortlink'    => __( 'Remove shortlink tag', 'wpsk-performance' ),
					'adjacent'     => __( 'Remove adjacent posts (prev/next) link tags', 'wpsk-performance' ),
					'rest_link'    => __( 'Remove REST API discovery link', 'wpsk-performance' ),
					'oembed_link'  => __( 'Remove oEmbed discovery links', 'wpsk-performance' ),
				],
			],
			[
				'id'      => 'asset_cleanup',
				'label'   => __( 'Asset Cleanup', 'wpsk-performance' ),
				'type'    => 'checkboxes',
				'default' => [ 'emoji', 'wp_embed', 'classic_theme' ],
				'options' => [
					'emoji'         => __( 'Remove emoji scripts and styles', 'wpsk-performance' ),
					'wp_embed'      => __( 'Remove wp-embed script (oEmbed for others to embed your posts)', 'wpsk-performance' ),
					'classic_theme' => __( 'Remove classic-theme-styles (unused by modern themes)', 'wpsk-performance' ),
					'google_fonts'  => __( 'Strip external Google Fonts stylesheets (use self-hosted fonts instead)', 'wpsk-performance' ),
					'jquery_migrate'=> __( 'Remove jQuery Migrate — caution: may break older plugins/themes', 'wpsk-performance' ),
				],
			],
			[
				'id'      => 'woocommerce',
				'label'   => __( 'WooCommerce', 'wpsk-performance' ),
				'type'    => 'checkboxes',
				'default' => [],
				'options' => [
					'disable_speculation' => __( 'Block speculation/prefetch on cart, checkout, and account pages', 'wpsk-performance' ),
				],
				'description' => __( 'Only visible when WooCommerce is active.', 'wpsk-performance' ),
			],
			[
				'id'      => 'heartbeat',
				'label'   => __( 'Heartbeat API', 'wpsk-performance' ),
				'type'    => 'select',
				'default' => 'default',
				'options' => [
					'default'  => __( 'Default (every 15-60 seconds)', 'wpsk-performance' ),
					'slow'     => __( 'Slow down (every 120 seconds)', 'wpsk-performance' ),
					'disable'  => __( 'Disable everywhere except post editor', 'wpsk-performance' ),
				],
				'description' => __( 'The Heartbeat API handles auto-save and real-time features. Slowing or disabling it reduces admin AJAX requests.', 'wpsk-performance' ),
			],
			[
				'id'          => 'limit_revisions',
				'label'       => __( 'Limit Post Revisions', 'wpsk-performance' ),
				'type'        => 'number',
				'default'     => 0,
				'description' => __( 'Maximum number of post revisions to keep. 0 = use WordPress default (unlimited). Set to 5-10 to save database space.', 'wpsk-performance' ),
			],
		];
	}

	/* ----------------------------------------------------------
	 * Init — hook into WP
	 * ---------------------------------------------------------- */

	protected function init(): void {
		$this->init_head_cleanup();
		$this->init_asset_cleanup();
		$this->init_woocommerce();
		$this->init_heartbeat();
		$this->init_revisions();
	}

	/* ----------------------------------------------------------
	 * Head cleanup
	 * ---------------------------------------------------------- */

	private function init_head_cleanup(): void {
		$flags = $this->get_option( 'head_cleanup' );
		if ( ! is_array( $flags ) || empty( $flags ) ) {
			return;
		}

		if ( in_array( 'shortlink', $flags, true ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}

		if ( in_array( 'adjacent', $flags, true ) ) {
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		}

		if ( in_array( 'rest_link', $flags, true ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		}

		if ( in_array( 'oembed_link', $flags, true ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
	}

	/* ----------------------------------------------------------
	 * Asset cleanup
	 * ---------------------------------------------------------- */

	private function init_asset_cleanup(): void {
		$flags = $this->get_option( 'asset_cleanup' );
		if ( ! is_array( $flags ) || empty( $flags ) ) {
			return;
		}

		// Emoji removal.
		if ( in_array( 'emoji', $flags, true ) ) {
			add_action( 'init', function () {
				remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
				remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
				remove_action( 'wp_print_styles', 'print_emoji_styles' );
				remove_action( 'admin_print_styles', 'print_emoji_styles' );
				remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
				remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
				remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			} );

			add_filter( 'tiny_mce_plugins', function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : $plugins;
			} );

			add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
				if ( 'dns-prefetch' === $relation_type ) {
					$urls = array_filter( $urls, function ( $url ) {
						return false === strpos( (string) $url, 'https://s.w.org/images/core/emoji' );
					} );
				}
				return $urls;
			}, 10, 2 );
		}

		// wp-embed removal.
		if ( in_array( 'wp_embed', $flags, true ) ) {
			add_action( 'wp_enqueue_scripts', function () {
				wp_deregister_script( 'wp-embed' );
			}, 100 );
		}

		// Classic theme styles.
		if ( in_array( 'classic_theme', $flags, true ) ) {
			add_action( 'wp_enqueue_scripts', function () {
				wp_dequeue_style( 'classic-theme-styles' );
			}, 100 );
		}

		// Google Fonts stripping.
		if ( in_array( 'google_fonts', $flags, true ) ) {
			add_action( 'wp_enqueue_scripts', function () {
				global $wp_styles;
				if ( ! isset( $wp_styles->queue ) ) {
					return;
				}
				foreach ( $wp_styles->queue as $handle ) {
					if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
						continue;
					}
					$src = $wp_styles->registered[ $handle ]->src;
					if (
						is_string( $src ) &&
						(
							false !== strpos( $src, 'fonts.googleapis.com' ) ||
							false !== strpos( $src, 'fonts.gstatic.com' )
						)
					) {
						wp_dequeue_style( $handle );
						wp_deregister_style( $handle );
					}
				}
			}, 100 );

			add_filter( 'style_loader_tag', function ( string $html ): string {
				if (
					false !== strpos( $html, 'fonts.googleapis.com' ) ||
					false !== strpos( $html, 'fonts.gstatic.com' )
				) {
					return '';
				}
				return $html;
			} );
		}

		// jQuery Migrate.
		if ( in_array( 'jquery_migrate', $flags, true ) ) {
			add_action( 'wp_default_scripts', function ( $scripts ) {
				if ( is_admin() ) {
					return; // Keep in admin to avoid breaking things.
				}
				if ( isset( $scripts->registered['jquery'] ) ) {
					$scripts->registered['jquery']->deps = array_diff(
						$scripts->registered['jquery']->deps,
						[ 'jquery-migrate' ]
					);
				}
			} );
		}
	}

	/* ----------------------------------------------------------
	 * WooCommerce optimizations
	 * ---------------------------------------------------------- */

	private function init_woocommerce(): void {
		$flags = $this->get_option( 'woocommerce' );
		if ( ! is_array( $flags ) || empty( $flags ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( in_array( 'disable_speculation', $flags, true ) ) {

			// Send anti-speculation headers on sensitive pages.
			add_action( 'send_headers', function () {
				if ( ! function_exists( 'is_cart' ) ) {
					return;
				}
				if ( is_cart() || is_checkout() || is_account_page() ) {
					header( 'Speculation-Rules: none', true );
					header( 'X-DNS-Prefetch-Control: off', true );
				}
			}, 1 );

			// Remove speculation rules and mark sensitive links.
			add_action( 'wp_footer', function () {
				?>
				<script>
				(function(){
					'use strict';
					var B=['/cart','/checkout','/my-account'];
					function blocked(h){
						if(!h)return false;
						try{var p=new URL(h,location.origin).pathname;return B.some(function(b){return p.indexOf(b)!==-1});}
						catch(e){return false;}
					}
					document.querySelectorAll('script[type="speculationrules"]').forEach(function(s){s.remove();});
					document.querySelectorAll('a[href]').forEach(function(a){
						if(blocked(a.href)){a.rel='nofollow';a.setAttribute('data-no-instant','1');}
					});
					new MutationObserver(function(ms){
						ms.forEach(function(m){m.addedNodes.forEach(function(n){
							if(n.nodeType!==1)return;
							if(n.tagName==='SCRIPT'&&n.type==='speculationrules'){n.remove();return;}
							if(n.tagName==='A'&&blocked(n.href)){n.rel='nofollow';n.setAttribute('data-no-instant','1');}
							if(n.querySelectorAll){
								n.querySelectorAll('script[type="speculationrules"]').forEach(function(s){s.remove();});
								n.querySelectorAll('a[href]').forEach(function(a){if(blocked(a.href)){a.rel='nofollow';a.setAttribute('data-no-instant','1');}});
							}
						});});
					}).observe(document.documentElement,{childList:true,subtree:true});
				})();
				</script>
				<?php
			}, 9999 );
		}
	}

	/* ----------------------------------------------------------
	 * Heartbeat throttle
	 * ---------------------------------------------------------- */

	private function init_heartbeat(): void {
		$mode = $this->get_option( 'heartbeat' );
		if ( 'default' === $mode || '' === $mode ) {
			return;
		}

		if ( 'disable' === $mode ) {
			add_action( 'init', function () {
				// Keep heartbeat in post editor for autosave.
				global $pagenow;
				if ( is_admin() && in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
					return;
				}
				wp_deregister_script( 'heartbeat' );
			}, 1 );
		}

		if ( 'slow' === $mode ) {
			add_filter( 'heartbeat_settings', function ( $settings ) {
				$settings['interval'] = 120;
				return $settings;
			} );
		}
	}

	/* ----------------------------------------------------------
	 * Revision limiter
	 * ---------------------------------------------------------- */

	private function init_revisions(): void {
		$limit = (int) $this->get_option( 'limit_revisions' );
		if ( $limit < 1 ) {
			return;
		}

		add_filter( 'wp_revisions_to_keep', function () use ( $limit ) {
			return $limit;
		} );
	}
}
