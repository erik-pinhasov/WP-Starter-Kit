<?php
/**
 * WPSK Media Replace — Replace media files in-place.
 *
 * Fix: After replacement, redirect includes a cache-busting timestamp
 * and the attachment's modified date is updated to force browser cache invalidation.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Media_Replace extends WPSK_Module {

	public function get_id(): string {
		return 'media-replace';
	}

	public function get_name(): string {
		return __( 'Media Replace', 'wpsk-media-replace' );
	}

	public function get_description(): string {
		return __( 'Replace media files in-place — keep the same URL, ID, and references.', 'wpsk-media-replace' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'search_replace',
				'label'       => __( 'URL Search & Replace', 'wpsk-media-replace' ),
				'type'        => 'checkbox',
				'description' => __( 'When replacing with a different file type, update old URLs in all posts and post meta.', 'wpsk-media-replace' ),
				'default'     => '1',
				'importance'  => 'medium',
			],
		];
	}

	public function get_help_html(): string {
		return '<strong>' . esc_html__( 'How to use:', 'wpsk-media-replace' ) . '</strong> '
			. esc_html__( 'Go to Media Library → click any image → look for the "Replace Media" button in the attachment details panel. Upload a new file to replace the old one while keeping the same URL and all references intact.', 'wpsk-media-replace' );
	}

	protected function init(): void {
		// Add "Replace" button to attachment edit screen.
		add_action( 'attachment_submitbox_misc_actions', [ $this, 'render_replace_button' ], 90 );

		// Add "Replace" link in media list view.
		add_filter( 'media_row_actions', [ $this, 'add_row_action' ], 10, 2 );

		// Handle the upload.
		add_action( 'admin_post_wpsk_replace_media', [ $this, 'handle_replace' ] );

		// Render the upload form page.
		add_action( 'admin_action_wpsk_replace_media_form', [ $this, 'render_upload_form' ] );
	}

	/* ── UI ──────────────────────────────────────────────────── */

	public function render_replace_button(): void {
		global $post;
		if ( ! $post || 'attachment' !== $post->post_type ) return;

		$url = admin_url( 'admin.php?action=wpsk_replace_media_form&id=' . $post->ID );
		echo '<div class="misc-pub-section">';
		echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( '🔄 Replace Media', 'wpsk-media-replace' ) . '</a>';
		echo '</div>';
	}

	public function add_row_action( $actions, $post ) {
		if ( 'attachment' !== $post->post_type ) return $actions;

		$url = admin_url( 'admin.php?action=wpsk_replace_media_form&id=' . $post->ID );
		$actions['wpsk_replace'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Replace', 'wpsk-media-replace' ) . '</a>';
		return $actions;
	}

	/* ── Upload form ────────────────────────────────────────── */

	public function render_upload_form(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Unauthorized' );
		}

		$id   = absint( $_GET['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			wp_die( 'Invalid attachment' );
		}

		$thumb = wp_get_attachment_image( $id, 'medium' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Replace Media', 'wpsk-media-replace' ); ?></h1>
			<p><?php printf( esc_html__( 'Replacing: %s', 'wpsk-media-replace' ), '<strong>' . esc_html( basename( get_attached_file( $id ) ) ) . '</strong>' ); ?></p>
			<?php if ( $thumb ) : ?>
				<div style="margin-bottom:16px;max-width:300px;border:1px solid #ddd;padding:4px;border-radius:4px">
					<?php echo $thumb; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpsk_replace_media_' . $id ); ?>
				<input type="hidden" name="action" value="wpsk_replace_media" />
				<input type="hidden" name="attachment_id" value="<?php echo esc_attr( $id ); ?>" />

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'New File', 'wpsk-media-replace' ); ?></th>
						<td>
							<input type="file" name="replacement_file" required accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip" />
							<p class="description"><?php esc_html_e( 'Upload the new file. It will replace the current file while keeping the same URL.', 'wpsk-media-replace' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Upload & Replace', 'wpsk-media-replace' ) ); ?>
			</form>
		</div>
		<?php
	}

	/* ── Handle replacement ─────────────────────────────────── */

	public function handle_replace(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Unauthorized' );
		}

		$id = absint( $_POST['attachment_id'] ?? 0 );
		check_admin_referer( 'wpsk_replace_media_' . $id );

		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			wp_die( 'Invalid attachment' );
		}

		if ( empty( $_FILES['replacement_file']['tmp_name'] ) ) {
			wp_die( __( 'No file uploaded.', 'wpsk-media-replace' ) );
		}

		$old_file = get_attached_file( $id );
		$old_url  = wp_get_attachment_url( $id );

		// Handle the upload.
		$uploaded = wp_handle_upload( $_FILES['replacement_file'], [ 'test_form' => false ] );
		if ( isset( $uploaded['error'] ) ) {
			wp_die( $uploaded['error'] );
		}

		$new_file = $uploaded['file'];
		$new_url  = $uploaded['url'];
		$new_type = $uploaded['type'];

		// Delete old file and its thumbnails.
		$meta = wp_get_attachment_metadata( $id );
		if ( $meta && ! empty( $meta['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			$old_dir    = dirname( $old_file );
			foreach ( $meta['sizes'] as $size ) {
				$thumb_path = $old_dir . '/' . $size['file'];
				if ( file_exists( $thumb_path ) ) {
					@unlink( $thumb_path );
				}
			}
		}
		if ( file_exists( $old_file ) && $old_file !== $new_file ) {
			@unlink( $old_file );
		}

		// Move new file to old location (same directory) if possible, to keep URL.
		$old_dir      = dirname( $old_file );
		$new_basename = basename( $new_file );
		$target_path  = $old_dir . '/' . $new_basename;

		// If file extension changed, we need to update the path.
		if ( pathinfo( $old_file, PATHINFO_EXTENSION ) === pathinfo( $new_file, PATHINFO_EXTENSION ) ) {
			// Same extension: use the old filename.
			$target_path = $old_file;
		}

		if ( $new_file !== $target_path ) {
			@rename( $new_file, $target_path );
			$new_file = $target_path;
		}

		// Update attachment metadata.
		update_attached_file( $id, $new_file );

		// Update post.
		wp_update_post( [
			'ID'             => $id,
			'post_mime_type' => $new_type,
			'post_modified'  => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', true ),
		] );

		// Regenerate thumbnails.
		if ( wp_attachment_is_image( $id ) ) {
			$new_meta = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $new_meta );
		}

		// URL search & replace if enabled and URL changed.
		$final_url = wp_get_attachment_url( $id );
		if ( '1' === $this->get_option( 'search_replace' ) && $old_url !== $final_url ) {
			$this->replace_urls( $old_url, $final_url );
		}

		// Clean caches.
		clean_post_cache( $id );
		clean_attachment_cache( $id );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			global $wpdb;
			$pids = $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
				$id
			) );
			foreach ( $pids as $pid ) {
				wc_delete_product_transients( (int) $pid );
				clean_post_cache( (int) $pid );
			}
		}

		// FIX: Add cache-busting parameter to redirect URL to prevent browser
		// from showing the old cached image.
		$redirect_url = admin_url( 'post.php?post=' . $id . '&action=edit&message=1&_cache=' . time() );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function replace_urls( string $old_url, string $new_url ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
			$old_url, $new_url, '%' . $wpdb->esc_like( $old_url ) . '%'
		) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
			$old_url, $new_url, '%' . $wpdb->esc_like( $old_url ) . '%'
		) );
	}
}
