<?php
/**
 * WPSK Media Organizer — Folder-based media library management.
 *
 * @package    WPStarterKit
 * @subpackage Modules\MediaOrganizer
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			[
				'id'          => 'bulk_download',
				'label'       => __( 'Bulk Download', 'wpsk-media-organizer' ),
				'type'        => 'checkbox',
				'description' => __( 'Allow downloading selected media files as a ZIP archive.', 'wpsk-media-organizer' ),
				'default'     => '1',
			],
		];
	}

	protected function init(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_filter( 'parent_file', [ $this, 'fix_admin_menu' ] );

		add_filter( 'attachment_fields_to_edit', [ $this, 'add_folder_field' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_folder_field' ], 10, 2 );

		add_action( 'wp_ajax_wpsk_assign_folder', [ $this, 'ajax_assign_folder' ] );
		add_action( 'wp_ajax_wpsk_set_upload_folder', [ $this, 'ajax_set_upload_folder' ] );

		if ( '1' === $this->get_option( 'auto_assign' ) ) {
			add_action( 'add_attachment', [ $this, 'auto_assign_folder' ] );
		}

		add_filter( 'media_row_actions', [ $this, 'add_quick_assign' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ $this, 'add_filter_dropdown' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_by_folder' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'enqueue_grid_scripts' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'grid_filter' ] );

		add_filter( 'bulk_actions-upload', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'bulk_notice' ] );

		add_action( 'post-upload-ui', [ $this, 'upload_folder_selector' ] );
	}

	/* ── Taxonomy ────────────────────────────────────────────── */

	public function register_taxonomy(): void {
		register_taxonomy( 'media_folder', 'attachment', [
			'hierarchical'          => true,
			'update_count_callback' => '_update_generic_term_count',
			'labels'                => [
				'name'          => __( 'Media Folders', 'wpsk-media-organizer' ),
				'singular_name' => __( 'Folder', 'wpsk-media-organizer' ),
				'search_items'  => __( 'Search Folders', 'wpsk-media-organizer' ),
				'all_items'     => __( 'All Folders', 'wpsk-media-organizer' ),
				'parent_item'   => __( 'Parent Folder', 'wpsk-media-organizer' ),
				'edit_item'     => __( 'Edit Folder', 'wpsk-media-organizer' ),
				'update_item'   => __( 'Update Folder', 'wpsk-media-organizer' ),
				'add_new_item'  => __( 'Add New Folder', 'wpsk-media-organizer' ),
				'new_item_name' => __( 'New Folder Name', 'wpsk-media-organizer' ),
				'menu_name'     => __( 'Folders', 'wpsk-media-organizer' ),
				'not_found'     => __( 'No folders found', 'wpsk-media-organizer' ),
			],
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'show_in_quick_edit' => true,
			'query_var'          => false,
			'public'             => false,
			'rewrite'            => false,
		] );
	}

	public function fix_admin_menu( string $parent ): string {
		global $current_screen;
		if ( $current_screen && 'media_folder' === ( $current_screen->taxonomy ?? '' ) ) {
			return 'upload.php';
		}
		return $parent;
	}

	/* ── Attachment edit sidebar ─────────────────────────────── */

	public function add_folder_field( array $fields, \WP_Post $post ): array {
		$terms = $this->get_folders();
		if ( empty( $terms ) ) { return $fields; }
		$current = wp_get_object_terms( $post->ID, 'media_folder', [ 'fields' => 'ids' ] );
		$cur_id  = ! empty( $current ) ? (int) $current[0] : 0;

		$html = '<select name="attachments[' . $post->ID . '][media_folder]" style="width:100%">';
		$html .= '<option value="0">' . esc_html__( '— No folder —', 'wpsk-media-organizer' ) . '</option>';
		foreach ( $terms as $t ) {
			$html .= sprintf( '<option value="%d"%s>%s</option>', $t->term_id, selected( $t->term_id, $cur_id, false ), esc_html( $t->name ) );
		}
		$html .= '</select>';

		$fields['media_folder'] = [
			'label' => __( 'Folder', 'wpsk-media-organizer' ),
			'input' => 'html',
			'html'  => $html,
		];
		return $fields;
	}

	public function save_folder_field( array $post, array $att ): array {
		if ( isset( $att['media_folder'] ) ) {
			$id = absint( $att['media_folder'] );
			wp_set_object_terms( $post['ID'], $id > 0 ? [ $id ] : [], 'media_folder' );
		}
		return $post;
	}

	/* ── AJAX ────────────────────────────────────────────────── */

	public function ajax_assign_folder(): void {
		check_ajax_referer( 'wpsk_media_folder', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error(); }
		$att = absint( $_POST['attachment_id'] ?? 0 );
		$fid = absint( $_POST['folder_id'] ?? 0 );
		if ( ! $att || 'attachment' !== get_post_type( $att ) ) { wp_send_json_error(); }
		wp_set_object_terms( $att, $fid > 0 ? [ $fid ] : [], 'media_folder' );
		wp_send_json_success();
	}

	public function ajax_set_upload_folder(): void {
		check_ajax_referer( 'wpsk_media_folder', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error(); }
		update_user_meta( get_current_user_id(), '_wpsk_upload_folder', absint( $_POST['folder_id'] ?? 0 ) );
		wp_send_json_success();
	}

	public function auto_assign_folder( int $id ): void {
		$folder = (int) get_user_meta( get_current_user_id(), '_wpsk_upload_folder', true );
		if ( $folder > 0 && term_exists( $folder, 'media_folder' ) ) {
			wp_set_object_terms( $id, [ $folder ], 'media_folder' );
		}
	}

	/* ── List view ───────────────────────────────────────────── */

	public function add_quick_assign( array $actions, \WP_Post $post ): array {
		if ( ! current_user_can( 'upload_files' ) ) { return $actions; }
		$terms = $this->get_folders();
		if ( empty( $terms ) ) { return $actions; }

		$cur    = wp_get_object_terms( $post->ID, 'media_folder', [ 'fields' => 'ids' ] );
		$cur_id = ! empty( $cur ) ? (int) $cur[0] : 0;

		$opts = '<option value="0">' . esc_html__( '— None —', 'wpsk-media-organizer' ) . '</option>';
		foreach ( $terms as $t ) {
			$opts .= '<option value="' . $t->term_id . '"' . selected( $t->term_id, $cur_id, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		$actions['wpsk_folder'] = '<span class="wpsk-qf"><a href="#" class="wpsk-qf-toggle">' . esc_html__( 'Folder', 'wpsk-media-organizer' ) . ' ▾</a><span class="wpsk-qf-dd" style="display:none"><select class="wpsk-qf-sel" data-att="' . $post->ID . '">' . $opts . '</select><span class="wpsk-qf-ok" style="display:none;color:#00a32a;margin-inline-start:4px">✓</span></span></span>';
		return $actions;
	}

	public function add_filter_dropdown( string $post_type ): void {
		if ( 'attachment' !== $post_type ) { return; }
		$terms    = $this->get_folders();
		$selected = sanitize_text_field( $_GET['media_folder'] ?? '' );
		$total    = (int) wp_count_posts( 'attachment' )->inherit;

		echo '<select name="media_folder" id="wpsk-folder-filter">';
		printf( '<option value="">%s (%d)</option>', esc_html__( 'All Folders', 'wpsk-media-organizer' ), $total );
		foreach ( $terms as $t ) {
			printf( '<option value="%s"%s>%s (%d)</option>', esc_attr( $t->slug ), selected( $t->slug, $selected, false ), esc_html( $t->name ), $t->count );
		}
		echo '</select>';
	}

	public function filter_by_folder( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'attachment' !== $q->get( 'post_type' ) ) { return; }
		$folder = sanitize_text_field( $_GET['media_folder'] ?? '' );
		if ( '' === $folder ) { return; }
		$q->set( 'tax_query', [ [ 'taxonomy' => 'media_folder', 'field' => 'slug', 'terms' => $folder ] ] );
	}

	/* ── Grid view ───────────────────────────────────────────── */

	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'upload.php' === $hook ) {
			$this->enqueue_grid_scripts();
			$this->enqueue_list_js();
		}
	}

	public function enqueue_grid_scripts(): void {
		static $done = false;
		if ( $done ) { return; }
		$done = true;

		$terms   = $this->get_folders();
		$options = [];
		foreach ( $terms as $t ) {
			$options[] = [ 'id' => $t->term_id, 'text' => $t->name, 'parent' => $t->parent, 'count' => $t->count ];
		}
		$cur_folder  = (int) get_user_meta( get_current_user_id(), '_wpsk_upload_folder', true );
		$nonce       = wp_create_nonce( 'wpsk_media_folder' );
		$total       = (int) wp_count_posts( 'attachment' )->inherit;
		$all_label   = esc_js( __( 'All Folders', 'wpsk-media-organizer' ) );
		$none_label  = esc_js( __( 'No Folder', 'wpsk-media-organizer' ) );
		$upl_label   = esc_js( __( 'Upload to folder:', 'wpsk-media-organizer' ) );
		$none_opt    = esc_js( __( '— None —', 'wpsk-media-organizer' ) );
		$json        = wp_json_encode( $options, JSON_UNESCAPED_UNICODE );

		$js = <<<JS
(function(){
if(typeof wp==="undefined"||!wp.media||!wp.media.view)return;
var f={$json},n="{$nonce}",uf={$cur_folder},tc={$total};
var O=wp.media.view.AttachmentFilters.All;
wp.media.view.AttachmentFilters.All=O.extend({createFilters:function(){
O.prototype.createFilters.call(this);
this.filters.mf_all={text:"{$all_label} ("+tc+")",props:{media_folder:""},priority:60};
this.filters.mf_none={text:"{$none_label}",props:{media_folder:"none"},priority:61};
for(var i=0;i<f.length;i++)this.filters["mf_"+f[i].id]={text:f[i].text+" ("+f[i].count+")",props:{media_folder:f[i].id},priority:62+i};
}});
function inject(){
if(document.getElementById("wpsk-uf-sel"))return;
var b=document.querySelector(".media-toolbar-secondary");if(!b)return;
var w=document.createElement("div");w.style.cssText="display:inline-flex;align-items:center;gap:6px;margin-inline-start:12px";
var l=document.createElement("label");l.style.cssText="font-weight:600;font-size:13px;white-space:nowrap";l.textContent="{$upl_label}";w.appendChild(l);
var s=document.createElement("select");s.id="wpsk-uf-sel";s.style.cssText="min-width:140px";
var o0=document.createElement("option");o0.value="0";o0.textContent="{$none_opt}";s.appendChild(o0);
for(var i=0;i<f.length;i++){var o=document.createElement("option");o.value=f[i].id;o.textContent=f[i].text;if(f[i].id===uf)o.selected=true;s.appendChild(o);}
s.addEventListener("change",function(){uf=parseInt(this.value)||0;var fd=new FormData();fd.append("action","wpsk_set_upload_folder");fd.append("nonce",n);fd.append("folder_id",uf);fetch(ajaxurl,{method:"POST",body:fd});});
w.appendChild(s);b.appendChild(w);}
var _B=wp.media.view.AttachmentsBrowser;
wp.media.view.AttachmentsBrowser=_B.extend({createToolbar:function(){_B.prototype.createToolbar.call(this);setTimeout(inject,150);}});
setTimeout(inject,500);
})();
JS;

		wp_add_inline_script( 'media-views', $js );
	}

	private function enqueue_list_js(): void {
		$nonce = esc_js( wp_create_nonce( 'wpsk_media_folder' ) );
		$js = <<<JS
document.addEventListener("DOMContentLoaded",function(){
var n="{$nonce}";
var fs=document.getElementById("wpsk-folder-filter");
if(fs)fs.addEventListener("change",function(){var u=new URL(location.href);if(this.value)u.searchParams.set("media_folder",this.value);else u.searchParams.delete("media_folder");u.searchParams.delete("paged");location.href=u.toString();});
document.addEventListener("click",function(e){var t=e.target.closest(".wpsk-qf-toggle");if(!t)return;e.preventDefault();var d=t.nextElementSibling;d.style.display=d.style.display==="none"?"inline":"none";});
document.addEventListener("change",function(e){if(!e.target.classList.contains("wpsk-qf-sel"))return;var s=e.target,ok=s.nextElementSibling;
var fd=new FormData();fd.append("action","wpsk_assign_folder");fd.append("nonce",n);fd.append("attachment_id",s.dataset.att);fd.append("folder_id",s.value);
fetch(ajaxurl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){ok.style.display="inline";setTimeout(function(){ok.style.display="none";},2000);}});});
});
JS;
		wp_add_inline_script( 'jquery', $js );
	}

	public function grid_filter( array $query ): array {
		$folder = sanitize_text_field( $_REQUEST['query']['media_folder'] ?? '' );
		if ( '' === $folder ) { return $query; }
		if ( 'none' === $folder ) {
			$query['tax_query'] = [ [ 'taxonomy' => 'media_folder', 'operator' => 'NOT EXISTS' ] ];
		} else {
			$query['tax_query'] = [ [ 'taxonomy' => 'media_folder', 'field' => 'term_id', 'terms' => absint( $folder ) ] ];
		}
		return $query;
	}

	/* ── Bulk actions ────────────────────────────────────────── */

	public function register_bulk_actions( array $actions ): array {
		foreach ( $this->get_folders() as $t ) {
			$actions[ 'wpsk_move_' . $t->term_id ] = sprintf( __( 'Move to: %s', 'wpsk-media-organizer' ), $t->name );
		}
		$actions['wpsk_move_0'] = __( 'Remove from folder', 'wpsk-media-organizer' );
		if ( '1' === $this->get_option( 'bulk_download' ) ) {
			$actions['wpsk_download'] = __( 'Download as ZIP', 'wpsk-media-organizer' );
		}
		return $actions;
	}

	public function handle_bulk_actions( string $url, string $action, array $ids ): string {
		if ( str_starts_with( $action, 'wpsk_move_' ) ) {
			$fid = absint( str_replace( 'wpsk_move_', '', $action ) );
			foreach ( $ids as $id ) {
				wp_set_object_terms( (int) $id, $fid > 0 ? [ $fid ] : [], 'media_folder' );
			}
			return add_query_arg( 'wpsk_moved', count( $ids ), $url );
		}
		if ( 'wpsk_download' === $action && ! empty( $ids ) && class_exists( 'ZipArchive' ) ) {
			$tmp = wp_tempnam( 'wpsk-dl' );
			$zip = new \ZipArchive();
			if ( true === $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				foreach ( $ids as $id ) {
					$path = get_attached_file( (int) $id );
					if ( $path && file_exists( $path ) ) { $zip->addFile( $path, basename( $path ) ); }
				}
				$zip->close();
				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="media-' . gmdate( 'Y-m-d-His' ) . '.zip"' );
				header( 'Content-Length: ' . filesize( $tmp ) );
				readfile( $tmp );
				@unlink( $tmp );
				exit;
			}
		}
		return $url;
	}

	public function bulk_notice(): void {
		if ( empty( $_GET['wpsk_moved'] ) ) { return; }
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf( esc_html__( '%d files moved successfully.', 'wpsk-media-organizer' ), (int) $_GET['wpsk_moved'] )
		);
	}

	/* ── Upload page selector ────────────────────────────────── */

	public function upload_folder_selector(): void {
		if ( ! current_user_can( 'upload_files' ) ) { return; }
		$terms = $this->get_folders();
		if ( empty( $terms ) ) { return; }
		$cur   = (int) get_user_meta( get_current_user_id(), '_wpsk_upload_folder', true );
		$nonce = wp_create_nonce( 'wpsk_media_folder' );

		echo '<div style="margin:16px 0;display:flex;align-items:center;gap:8px">';
		echo '<label for="wpsk-uf-new" style="font-weight:600;font-size:14px">' . esc_html__( 'Upload to folder:', 'wpsk-media-organizer' ) . '</label>';
		echo '<select id="wpsk-uf-new" style="min-width:160px"><option value="0">' . esc_html__( '— None —', 'wpsk-media-organizer' ) . '</option>';
		foreach ( $terms as $t ) {
			printf( '<option value="%d"%s>%s</option>', $t->term_id, selected( $t->term_id, $cur, false ), esc_html( $t->name ) );
		}
		echo '</select><span id="wpsk-uf-ok" style="display:none;color:#00a32a">✓</span></div>';
		$n = esc_js( $nonce );
		echo "<script>(function(){var s=document.getElementById('wpsk-uf-new'),o=document.getElementById('wpsk-uf-ok');if(!s)return;s.addEventListener('change',function(){var fd=new FormData();fd.append('action','wpsk_set_upload_folder');fd.append('nonce','{$n}');fd.append('folder_id',this.value);fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){o.style.display='inline';setTimeout(function(){o.style.display='none';},2000);}});});})();</script>";
	}

	/* ── Helper ──────────────────────────────────────────────── */

	private function get_folders(): array {
		static $cache = null;
		if ( null !== $cache ) { return $cache; }
		$terms = get_terms( [ 'taxonomy' => 'media_folder', 'hide_empty' => false, 'orderby' => 'name' ] );
		$cache = is_wp_error( $terms ) ? [] : $terms;
		return $cache;
	}
}
