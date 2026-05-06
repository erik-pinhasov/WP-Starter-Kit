<?php
/**
 * WPSK Media Replace — Replace media files in-place.
 *
 * Keeps the same attachment ID and URL, regenerates thumbnails,
 * and optionally updates database references if the extension changes.
 *
 * @package    WPStarterKit
 * @subpackage Modules\MediaReplace
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
				'id'          => 'update_urls',
				'label'       => __( 'Update Database URLs', 'wpsk-media-replace' ),
				'type'        => 'checkbox',
				'description' => __( 'When the file extension changes, search & replace old URLs in posts and post meta.', 'wpsk-media-replace' ),
				'default'     => '1',
			],
		];
	}

	protected function init(): void {
		// "Replace" link in media list view.
		add_filter( 'media_row_actions', [ $this, 'add_row_action' ], 10, 2 );

		// "Replace" button in attachment edit sidebar.
		add_action( 'attachment_submitbox_misc_actions', [ $this, 'add_sidebar_button' ] );

		// Hidden admin page for the replacement UI.
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	/* ── Row action link ─────────────────────────────────────── */

	public function add_row_action( array $actions, \WP_Post $post ): array {
		if ( current_user_can( 'upload_files' ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=wpsk-replace-media&attachment_id=' . $post->ID ),
				'wpsk_replace_' . $post->ID
			);
			$actions['wpsk_replace'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Replace File', 'wpsk-media-replace' ) . '</a>';
		}
		return $actions;
	}

	/* ── Sidebar button ──────────────────────────────────────── */

	public function add_sidebar_button(): void {
		global $post;
		if ( ! $post || ! current_user_can( 'upload_files' ) ) { return; }
		$url = wp_nonce_url(
			admin_url( 'admin.php?page=wpsk-replace-media&attachment_id=' . $post->ID ),
			'wpsk_replace_' . $post->ID
		);
		echo '<div class="misc-pub-section">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-secondary" style="width:100%;text-align:center">' . esc_html__( 'Replace Media File', 'wpsk-media-replace' ) . '</a>';
		echo '</div>';
	}

	/* ── Admin page ──────────────────────────────────────────── */

	public function register_page(): void {
		$hook = add_submenu_page(
			null,
			__( 'Replace Media File', 'wpsk-media-replace' ),
			'',
			'upload_files',
			'wpsk-replace-media',
			[ $this, 'render_page' ]
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, [ $this, 'handle_upload' ] );
		}
	}

	/**
	 * Process POST before headers are sent.
	 */
	public function handle_upload(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_FILES['replacement'] ) ) { return; }
		$id = absint( $_GET['attachment_id'] ?? 0 );
		if ( ! $id ) { wp_die( esc_html__( 'Missing attachment ID.', 'wpsk-media-replace' ) ); }
		check_admin_referer( 'wpsk_replace_do_' . $id, '_wpsk_replace_nonce' );
		$this->process_replace( $id );
	}

	/**
	 * Render the replacement page (GET).
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) { wp_die( esc_html__( 'Permission denied.', 'wpsk-media-replace' ) ); }
		$id = absint( $_GET['attachment_id'] ?? 0 );
		if ( ! $id ) { wp_die( esc_html__( 'Missing attachment ID.', 'wpsk-media-replace' ) ); }
		check_admin_referer( 'wpsk_replace_' . $id );

		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			wp_die( esc_html__( 'Attachment not found.', 'wpsk-media-replace' ) );
		}

		$file_url  = wp_get_attachment_url( $id );
		$file_path = get_attached_file( $id );
		$file_name = basename( $file_path );
		$mime      = get_post_mime_type( $id );
		$is_image  = wp_attachment_is_image( $id );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Replace Media File', 'wpsk-media-replace' ); ?></h1>

			<div style="display:flex;gap:2em;margin:2em 0;align-items:flex-start;flex-wrap:wrap">
				<?php if ( $is_image ) : ?>
				<div>
					<strong><?php esc_html_e( 'Current file:', 'wpsk-media-replace' ); ?></strong><br>
					<img src="<?php echo esc_url( $file_url ); ?>" style="max-width:300px;max-height:300px;margin-top:.5em;border:1px solid #ddd;border-radius:4px">
				</div>
				<?php endif; ?>

				<div>
					<p><strong><?php esc_html_e( 'Filename:', 'wpsk-media-replace' ); ?></strong> <?php echo esc_html( $file_name ); ?></p>
					<p><strong><?php esc_html_e( 'Type:', 'wpsk-media-replace' ); ?></strong> <?php echo esc_html( $mime ); ?></p>
					<p><strong><?php esc_html_e( 'Date:', 'wpsk-media-replace' ); ?></strong> <?php echo esc_html( get_the_date( 'Y-m-d H:i', $id ) ); ?></p>
					<?php if ( file_exists( $file_path ) ) : ?>
					<p><strong><?php esc_html_e( 'Size:', 'wpsk-media-replace' ); ?></strong> <?php echo esc_html( size_format( filesize( $file_path ) ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpsk_replace_do_' . $id, '_wpsk_replace_nonce' ); ?>

				<div id="wpsk-dropzone">
					<div id="wpsk-dz-empty">
						<span class="dashicons dashicons-upload" style="font-size:48px;width:48px;height:48px;color:#2271b1"></span>
						<p style="font-size:15px;margin:12px 0 4px"><strong><?php esc_html_e( 'Drag file here', 'wpsk-media-replace' ); ?></strong></p>
						<p style="color:#646970;margin:0"><?php esc_html_e( 'or', 'wpsk-media-replace' ); ?></p>
						<label for="replacement" class="button button-primary" style="margin-top:12px;cursor:pointer"><?php esc_html_e( 'Choose File', 'wpsk-media-replace' ); ?></label>
						<input type="file" name="replacement" id="replacement" required style="position:absolute;left:-9999px">
					</div>
					<div id="wpsk-dz-preview" style="display:none">
						<img id="wpsk-pv-img" style="max-width:200px;max-height:200px;border-radius:4px">
						<p id="wpsk-pv-name" style="font-weight:600;margin:8px 0 0"></p>
						<p id="wpsk-pv-size" style="color:#646970;margin:4px 0 0"></p>
						<button type="button" id="wpsk-pv-clear" class="button" style="margin-top:12px"><?php esc_html_e( 'Choose different file', 'wpsk-media-replace' ); ?></button>
					</div>
				</div>

				<p class="submit">
					<input type="submit" id="wpsk-replace-submit" class="button button-primary" value="<?php esc_attr_e( 'Replace File', 'wpsk-media-replace' ); ?>" disabled>
				</p>
			</form>

			<p>
				<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">← <?php esc_html_e( 'Back to attachment', 'wpsk-media-replace' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Media Library', 'wpsk-media-replace' ); ?></a>
			</p>
		</div>

		<style>
			#wpsk-dropzone{border:2px dashed #c3c4c7;border-radius:8px;padding:40px;text-align:center;transition:border-color .2s,background .2s;max-width:500px;cursor:pointer}
			#wpsk-dropzone.drag-over{border-color:#2271b1;background:#f0f6fc}
			#wpsk-dropzone.has-file{border-style:solid;border-color:#2271b1;background:#f0f6fc;cursor:default}
		</style>
		<script>
		(function(){
			var dz=document.getElementById("wpsk-dropzone"),inp=document.getElementById("replacement"),
			    empty=document.getElementById("wpsk-dz-empty"),pv=document.getElementById("wpsk-dz-preview"),
			    pvImg=document.getElementById("wpsk-pv-img"),pvName=document.getElementById("wpsk-pv-name"),
			    pvSize=document.getElementById("wpsk-pv-size"),btnC=document.getElementById("wpsk-pv-clear"),
			    btnS=document.getElementById("wpsk-replace-submit");
			function fmt(b){if(b<1024)return b+" B";if(b<1048576)return(b/1024).toFixed(1)+" KB";return(b/1048576).toFixed(1)+" MB";}
			function show(f){pvName.textContent=f.name;pvSize.textContent=fmt(f.size);if(f.type.startsWith("image/")){var r=new FileReader();r.onload=function(e){pvImg.src=e.target.result;pvImg.style.display="";};r.readAsDataURL(f);}else{pvImg.style.display="none";}empty.style.display="none";pv.style.display="";dz.classList.add("has-file");btnS.disabled=false;}
			function clear(){inp.value="";empty.style.display="";pv.style.display="none";dz.classList.remove("has-file");btnS.disabled=true;}
			["dragenter","dragover"].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.add("drag-over");});});
			["dragleave","drop"].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove("drag-over");});});
			dz.addEventListener("drop",function(e){if(e.dataTransfer.files.length){inp.files=e.dataTransfer.files;show(e.dataTransfer.files[0]);}});
			dz.addEventListener("click",function(e){if(e.target.closest("button,label,input")||dz.classList.contains("has-file"))return;inp.click();});
			inp.addEventListener("change",function(){if(this.files.length)show(this.files[0]);});
			btnC.addEventListener("click",clear);
		})();
		</script>
		<?php
	}

	/* ── File replacement logic ──────────────────────────────── */

	private function process_replace( int $id ): void {
		$file = $_FILES['replacement'];
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_die( esc_html__( 'Upload error.', 'wpsk-media-replace' ) . ' (' . (int) $file['error'] . ')' );
		}

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( ! $filetype['type'] ) {
			wp_die( esc_html__( 'File type not allowed.', 'wpsk-media-replace' ) );
		}

		$old_path = get_attached_file( $id );
		$old_dir  = dirname( $old_path );
		$old_name = pathinfo( $old_path, PATHINFO_FILENAME );
		$old_ext  = strtolower( pathinfo( $old_path, PATHINFO_EXTENSION ) );
		$new_ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		$new_path = $old_dir . '/' . $old_name . '.' . $new_ext;

		// Delete old thumbnails.
		$old_meta = wp_get_attachment_metadata( $id );
		if ( is_array( $old_meta ) && ! empty( $old_meta['sizes'] ) ) {
			foreach ( $old_meta['sizes'] as $info ) {
				$thumb = $old_dir . '/' . $info['file'];
				if ( file_exists( $thumb ) ) { @unlink( $thumb ); }
			}
		}
		$scaled = $old_dir . '/' . $old_name . '-scaled.' . $old_ext;
		if ( file_exists( $scaled ) ) { @unlink( $scaled ); }

		// Remove old file if path changed.
		if ( file_exists( $old_path ) && realpath( $old_path ) !== realpath( $new_path ) ) {
			@unlink( $old_path );
		}

		if ( ! move_uploaded_file( $file['tmp_name'], $new_path ) ) {
			wp_die( esc_html__( 'Could not save file.', 'wpsk-media-replace' ) );
		}

		$stat = stat( $old_dir );
		if ( $stat ) { @chmod( $new_path, $stat['mode'] & 0000666 ); }

		// Update post record.
		wp_update_post( [
			'ID'             => $id,
			'post_mime_type' => $filetype['type'],
			'post_modified'  => current_time( 'mysql' ),
		] );

		update_attached_file( $id, $new_path );

		// Regenerate thumbnails.
		$new_meta = wp_generate_attachment_metadata( $id, $new_path );
		wp_update_attachment_metadata( $id, $new_meta );

		// URL search & replace if extension changed.
		if ( $old_ext !== $new_ext && '1' === $this->get_option( 'update_urls' ) ) {
			$uploads = wp_get_upload_dir();
			$old_url = trailingslashit( $uploads['baseurl'] ) . _wp_relative_upload_path( $old_dir . '/' . $old_name . '.' . $old_ext );
			$new_url = wp_get_attachment_url( $id );
			if ( $old_url !== $new_url ) {
				$this->replace_urls( $old_url, $new_url );
			}
		}

		// Clear caches.
		clean_post_cache( $id );
		clean_attachment_cache( $id );

		// WooCommerce product transients.
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

		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&message=1' ) );
		exit;
	}

	/**
	 * Search & replace URLs in post_content and postmeta.
	 */
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
