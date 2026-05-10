<?php
/**
 * WPSK Media Organizer — Folder-based media library management.
 *
 * Fix: Folder media counts now include all descendants (recursive),
 * not just direct children.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPSK_Media_Organizer extends WPSK_Module {

	public function get_id(): string {
		return 'media-organizer';
	}

	public function get_name(): string {
		return __( 'Media Organizer', 'wpsk-media-organizer' );
	}

	public function get_description(): string {
		return __( 'Organize your media library into folders with filtering and bulk actions.', 'wpsk-media-organizer' );
	}

	public function get_settings_fields(): array {
		return [
			[
				'id'          => 'auto_assign',
				'label'       => __( 'Auto-Assign Uploads', 'wpsk-media-organizer' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically place new uploads into the user\'s selected default folder.', 'wpsk-media-organizer' ),
				'default'     => '1',
			],
		];
	}

	protected function init(): void {
		// Register taxonomy.
		add_action( 'init', [ $this, 'register_taxonomy' ] );

		// Admin UI.
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'restrict_manage_posts', [ $this, 'add_filter_dropdown' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_wpsk_assign_folder', [ $this, 'ajax_assign_folder' ] );
		add_action( 'wp_ajax_wpsk_create_folder', [ $this, 'ajax_create_folder' ] );
		add_action( 'wp_ajax_wpsk_delete_folder', [ $this, 'ajax_delete_folder' ] );
		add_action( 'wp_ajax_wpsk_rename_folder', [ $this, 'ajax_rename_folder' ] );

		// Auto-assign on upload.
		if ( '1' === $this->get_option( 'auto_assign' ) ) {
			add_action( 'add_attachment', [ $this, 'auto_assign' ] );
		}

		// Add folder column to media list.
		add_filter( 'manage_media_columns', [ $this, 'add_folder_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_folder_column' ], 10, 2 );

		// Quick-assign dropdown in attachment edit.
		add_action( 'attachment_submitbox_misc_actions', [ $this, 'render_quick_assign' ] );
	}

	/* ── Taxonomy ───────────────────────────────────────────── */

	public function register_taxonomy(): void {
		register_taxonomy( 'media_folder', 'attachment', [
			'label'             => __( 'Folders', 'wpsk-media-organizer' ),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
		] );
	}

	/* ── Admin UI ───────────────────────────────────────────── */

	public function admin_init(): void {
		// Add sidebar panel to media library.
		add_action( 'admin_footer-upload.php', [ $this, 'render_sidebar_panel' ] );
	}

	public function add_filter_dropdown(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		$folders  = $this->get_folders();
		$selected = $_GET['media_folder'] ?? '';

		echo '<select name="media_folder" style="max-width:200px">';
		echo '<option value="">' . esc_html__( 'All Folders', 'wpsk-media-organizer' ) . '</option>';
		echo '<option value="__uncategorized"' . selected( $selected, '__uncategorized', false ) . '>' . esc_html__( 'Uncategorized', 'wpsk-media-organizer' ) . '</option>';

		foreach ( $folders as $folder ) {
			$indent = str_repeat( '—', $this->get_term_depth( $folder ) );
			$count  = $this->get_recursive_count( $folder->term_id );
			echo '<option value="' . esc_attr( $folder->slug ) . '"' . selected( $selected, $folder->slug, false ) . '>'
				. esc_html( $indent . ' ' . $folder->name . ' (' . $count . ')' )
				. '</option>';
		}
		echo '</select>';
	}

	/**
	 * FIX: Get the total media count for a folder INCLUDING all subfolders.
	 * The default term->count only counts direct children.
	 */
	private function get_recursive_count( int $term_id ): int {
		$term_ids = [ $term_id ];

		// Get all descendant term IDs.
		$children = get_term_children( $term_id, 'media_folder' );
		if ( ! is_wp_error( $children ) ) {
			$term_ids = array_merge( $term_ids, $children );
		}

		// Count attachments in all these terms.
		$query = new \WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => 'media_folder',
					'terms'    => $term_ids,
					'field'    => 'term_id',
					'operator' => 'IN',
				],
			],
		] );

		return $query->post_count;
	}

	private function get_term_depth( $term ): int {
		$depth = 0;
		while ( $term->parent ) {
			$depth++;
			$term = get_term( $term->parent, 'media_folder' );
			if ( ! $term || is_wp_error( $term ) ) break;
		}
		return $depth;
	}

	/* ── Folder column ──────────────────────────────────────── */

	public function add_folder_column( $columns ) {
		$columns['media_folder'] = __( 'Folder', 'wpsk-media-organizer' );
		return $columns;
	}

	public function render_folder_column( $column_name, $post_id ): void {
		if ( 'media_folder' !== $column_name ) return;

		$terms = wp_get_post_terms( $post_id, 'media_folder' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<span style="color:#999">' . esc_html__( 'Uncategorized', 'wpsk-media-organizer' ) . '</span>';
			return;
		}

		echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
	}

	/* ── Quick-assign on attachment edit ─────────────────────── */

	public function render_quick_assign(): void {
		global $post;
		if ( ! $post || 'attachment' !== $post->post_type ) return;

		$folders      = $this->get_folders();
		$current      = wp_get_post_terms( $post->ID, 'media_folder', [ 'fields' => 'ids' ] );
		$current_id   = ! empty( $current ) && ! is_wp_error( $current ) ? $current[0] : 0;
		$nonce        = wp_create_nonce( 'wpsk_assign_folder' );

		echo '<div class="misc-pub-section">';
		echo '<label><strong>' . esc_html__( 'Folder:', 'wpsk-media-organizer' ) . '</strong></label> ';
		echo '<select id="wpsk-quick-folder" style="max-width:160px">';
		echo '<option value="0">' . esc_html__( 'None', 'wpsk-media-organizer' ) . '</option>';
		foreach ( $folders as $f ) {
			$indent = str_repeat( '—', $this->get_term_depth( $f ) );
			echo '<option value="' . esc_attr( $f->term_id ) . '"' . selected( $current_id, $f->term_id, false ) . '>'
				. esc_html( $indent . ' ' . $f->name ) . '</option>';
		}
		echo '</select>';
		echo ' <span id="wpsk-folder-saved" style="color:green;display:none">✓</span>';

		// Inline JS for quick assign.
		echo '<script>(function(){var s=document.getElementById("wpsk-quick-folder");'
			. 'var o=document.getElementById("wpsk-folder-saved");'
			. 's.addEventListener("change",function(){'
			. 'var fd=new FormData();fd.append("action","wpsk_assign_folder");'
			. 'fd.append("_wpnonce","' . esc_js( $nonce ) . '");'
			. 'fd.append("post_id",' . (int) $post->ID . ');'
			. 'fd.append("folder_id",this.value);'
			. 'fetch(ajaxurl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(d){'
			. 'if(d.success){o.style.display="inline";setTimeout(function(){o.style.display="none";},2000);}'
			. '});});})();</script>';
		echo '</div>';
	}

	/* ── AJAX handlers ──────────────────────────────────────── */

	public function ajax_assign_folder(): void {
		check_ajax_referer( 'wpsk_assign_folder' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$post_id   = absint( $_POST['post_id'] ?? 0 );
		$folder_id = absint( $_POST['folder_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		if ( $folder_id ) {
			wp_set_post_terms( $post_id, [ $folder_id ], 'media_folder' );
		} else {
			wp_set_post_terms( $post_id, [], 'media_folder' );
		}

		wp_send_json_success();
	}

	public function ajax_create_folder(): void {
		check_ajax_referer( 'wpsk_create_folder' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$name   = sanitize_text_field( $_POST['name'] ?? '' );
		$parent = absint( $_POST['parent'] ?? 0 );

		if ( empty( $name ) ) {
			wp_send_json_error( __( 'Folder name is required.', 'wpsk-media-organizer' ) );
		}

		$result = wp_insert_term( $name, 'media_folder', [ 'parent' => $parent ] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( [
			'term_id' => $result['term_id'],
			'name'    => $name,
		] );
	}

	public function ajax_delete_folder(): void {
		check_ajax_referer( 'wpsk_delete_folder' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$term_id = absint( $_POST['term_id'] ?? 0 );
		if ( ! $term_id ) {
			wp_send_json_error( 'Invalid folder ID' );
		}

		// Remove folder assignment from all attachments first.
		$atts = get_posts( [
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[ 'taxonomy' => 'media_folder', 'terms' => $term_id, 'field' => 'term_id' ],
			],
		] );
		foreach ( $atts as $att_id ) {
			wp_remove_object_terms( $att_id, $term_id, 'media_folder' );
		}

		$result = wp_delete_term( $term_id, 'media_folder' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	public function ajax_rename_folder(): void {
		check_ajax_referer( 'wpsk_rename_folder' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$term_id = absint( $_POST['term_id'] ?? 0 );
		$name    = sanitize_text_field( $_POST['name'] ?? '' );

		if ( ! $term_id || empty( $name ) ) {
			wp_send_json_error( 'Invalid input' );
		}

		$result = wp_update_term( $term_id, 'media_folder', [ 'name' => $name ] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/* ── Auto-assign ────────────────────────────────────────── */

	public function auto_assign( int $post_id ): void {
		$default = get_user_meta( get_current_user_id(), 'wpsk_default_folder', true );
		if ( $default ) {
			wp_set_post_terms( $post_id, [ (int) $default ], 'media_folder' );
		}
	}

	/* ── Sidebar panel (media library) ──────────────────────── */

	public function render_sidebar_panel(): void {
		$folders = $this->get_folders();
		$nonce_create = wp_create_nonce( 'wpsk_create_folder' );
		$nonce_delete = wp_create_nonce( 'wpsk_delete_folder' );
		$nonce_rename = wp_create_nonce( 'wpsk_rename_folder' );

		?>
		<script>
		(function(){
			// Add a "Manage Folders" button to the media toolbar.
			var toolbar = document.querySelector('.media-toolbar');
			if (!toolbar) return;

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'button';
			btn.textContent = '<?php echo esc_js( __( '📁 Manage Folders', 'wpsk-media-organizer' ) ); ?>';
			btn.style.marginLeft = '8px';
			toolbar.appendChild(btn);

			btn.addEventListener('click', function() {
				var dialog = document.getElementById('wpsk-folder-dialog');
				if (dialog) {
					dialog.style.display = dialog.style.display === 'none' ? 'block' : 'none';
				}
			});
		})();
		</script>
		<div id="wpsk-folder-dialog" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border:1px solid #ccc;padding:20px;border-radius:8px;z-index:999999;min-width:300px;box-shadow:0 4px 20px rgba(0,0,0,.2)">
			<h3 style="margin-top:0"><?php esc_html_e( 'Manage Folders', 'wpsk-media-organizer' ); ?></h3>
			<div style="margin-bottom:12px">
				<input type="text" id="wpsk-new-folder-name" placeholder="<?php esc_attr_e( 'New folder name', 'wpsk-media-organizer' ); ?>" style="width:200px" />
				<button type="button" class="button button-primary" onclick="wpskCreateFolder()"><?php esc_html_e( 'Create', 'wpsk-media-organizer' ); ?></button>
			</div>
			<ul id="wpsk-folder-list" style="max-height:300px;overflow-y:auto">
				<?php foreach ( $folders as $f ) :
					$indent = str_repeat( '&nbsp;&nbsp;', $this->get_term_depth( $f ) );
					$count  = $this->get_recursive_count( $f->term_id );
				?>
				<li data-id="<?php echo esc_attr( $f->term_id ); ?>" style="padding:4px 0;border-bottom:1px solid #eee">
					<?php echo $indent; ?>
					<span class="wpsk-folder-name"><?php echo esc_html( $f->name ); ?></span>
					<small style="color:#999">(<?php echo esc_html( $count ); ?>)</small>
					<button type="button" class="button-link" onclick="wpskDeleteFolder(<?php echo esc_attr( $f->term_id ); ?>)" style="color:#b32d2e;margin-left:8px"><?php esc_html_e( 'Delete', 'wpsk-media-organizer' ); ?></button>
				</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="button" onclick="document.getElementById('wpsk-folder-dialog').style.display='none'" style="margin-top:12px"><?php esc_html_e( 'Close', 'wpsk-media-organizer' ); ?></button>
		</div>
		<script>
		function wpskCreateFolder() {
			var name = document.getElementById('wpsk-new-folder-name').value.trim();
			if (!name) return;
			var fd = new FormData();
			fd.append('action', 'wpsk_create_folder');
			fd.append('_wpnonce', '<?php echo esc_js( $nonce_create ); ?>');
			fd.append('name', name);
			fetch(ajaxurl, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
				if (d.success) { location.reload(); } else { alert(d.data); }
			});
		}
		function wpskDeleteFolder(id) {
			if (!confirm('<?php echo esc_js( __( 'Delete this folder? Media files will not be deleted.', 'wpsk-media-organizer' ) ); ?>')) return;
			var fd = new FormData();
			fd.append('action', 'wpsk_delete_folder');
			fd.append('_wpnonce', '<?php echo esc_js( $nonce_delete ); ?>');
			fd.append('term_id', id);
			fetch(ajaxurl, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
				if (d.success) { location.reload(); } else { alert(d.data); }
			});
		}
		</script>
		<?php
	}

	/* ── Helper ──────────────────────────────────────────────── */

	private function get_folders(): array {
		static $cache = null;
		if ( null !== $cache ) return $cache;
		$terms = get_terms( [
			'taxonomy'   => 'media_folder',
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
		$cache = is_wp_error( $terms ) ? [] : $terms;
		return $cache;
	}
}
