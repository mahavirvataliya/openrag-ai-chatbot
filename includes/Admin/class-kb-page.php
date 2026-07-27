<?php
/**
 * Knowledge Base admin page — documents, links, WP content tabs.
 *
 * @package WPOpenRag\Admin
 */

namespace WPOpenRag\Admin;

use WPOpenRag\Database\Schema;
use WPOpenRag\Plugin;
use WPOpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KB_Page {

	/**
	 * @var Plugin
	 */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_init', array( $this, 'handle_wp_index_save' ) );
	}

	/**
	 * Save the WP content indexing settings (post types + auto-index).
	 *
	 * @return void
	 */
	public function handle_wp_index_save() {
		if ( ! isset( $_POST['wporag_action'] ) || 'save_wp_index' !== $_POST['wporag_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'wporag_save_wp_index' );

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		$auto       = empty( $_POST['auto_index'] ) ? '0' : '1';

		$indexing = \WPOpenRag\Settings::group( 'indexing' );
		$indexing['post_types'] = $post_types;
		$indexing['auto_index'] = $auto;
		\WPOpenRag\Settings::save_group( 'indexing', $indexing );

		add_settings_error( 'wporag', 'saved', __( 'Indexing settings saved.', 'wp-openrag' ), 'updated' );
	}

	public function render() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'documents'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'documents' => __( 'Documents & Files', 'wp-openrag' ),
			'links'     => __( 'URLs', 'wp-openrag' ),
			'wp'        => __( 'WordPress Content', 'wp-openrag' ),
		);

		$base_url = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-kb' );
		?>
		<div class="wrap wporag-admin-wrap">
			<h1><?php esc_html_e( 'Knowledge Base', 'wp-openrag' ); ?></h1>
			<?php settings_errors( 'wporag' ); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base_url ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php
			switch ( $tab ) {
				case 'links':
					$this->render_links_tab();
					break;
				case 'wp':
					$this->render_wp_tab();
					break;
				case 'documents':
				default:
					$this->render_documents_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	protected function render_documents_tab() {
		global $wpdb;
		$schema = new Schema();
		$rows   = $wpdb->get_results( "SELECT * FROM `" . $schema->table( 'documents' ) . "` WHERE type IN ('pdf','docx','txt') ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB
		$upload = wp_upload_dir();
		?>
		<div class="wporag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Add Document', 'wp-openrag' ); ?></h2>
				<form id="wporag-add-doc" class="wporag-form">
					<p>
						<label><?php esc_html_e( 'Title (optional)', 'wp-openrag' ); ?></label>
						<input type="text" name="title" class="regular-text" />
					</p>
					<p>
						<label><?php esc_html_e( 'File URL (PDF/DOCX/TXT/MD)', 'wp-openrag' ); ?></label>
						<input type="url" name="source_url" class="regular-text" placeholder="https://example.com/doc.pdf" />
					</p>
					<p class="wporag-or">— <?php esc_html_e( 'or', 'wp-openrag' ); ?> —</p>
					<p>
						<label><?php esc_html_e( 'Upload File', 'wp-openrag' ); ?></label>
						<button type="button" class="button" id="wporag-upload-btn"><?php esc_html_e( 'Choose file', 'wp-openrag' ); ?></button>
						<input type="hidden" name="file_path" id="wporag-file-path" />
						<input type="hidden" name="mime_type" id="wporag-file-mime" />
						<span id="wporag-file-name"></span>
					</p>
					<p>
						<label><input type="checkbox" name="queue" value="1" checked /> <?php esc_html_e( 'Process immediately in the background', 'wp-openrag' ); ?></label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add & Process', 'wp-openrag' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<h2><?php esc_html_e( 'Documents', 'wp-openrag' ); ?></h2>
				<table class="widefat striped" id="wporag-doc-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Chunks', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-openrag' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No documents yet.', 'wp-openrag' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr data-id="<?php echo esc_attr( $row->id ); ?>">
								<td><?php echo esc_html( $row->title ?: basename( $row->source_url ?: $row->file_path ) ); ?></td>
								<td><?php echo esc_html( strtoupper( $row->type ) ); ?></td>
								<td><?php echo esc_html( $row->chunk_count ); ?></td>
								<td><?php echo '<span class="wporag-status wporag-status-' . esc_attr( $row->status ) . '">' . esc_html( $row->status ) . '</span>'; ?></td>
								<td>
									<button class="button button-small wporag-reindex" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Reindex', 'wp-openrag' ); ?></button>
									<button class="button button-small wporag-view-chunks" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Chunks', 'wp-openrag' ); ?></button>
									<button class="button button-small button-link-delete wporag-delete" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Delete', 'wp-openrag' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	protected function render_links_tab() {
		global $wpdb;
		$schema = new Schema();
		$rows   = $wpdb->get_results( "SELECT * FROM `" . $schema->table( 'documents' ) . "` WHERE type = 'url' ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB
		?>
		<div class="wporag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Add URLs', 'wp-openrag' ); ?></h2>
				<form id="wporag-add-urls" class="wporag-form">
					<p>
						<label><?php esc_html_e( 'One URL per line (or CSV: title,url)', 'wp-openrag' ); ?></label>
						<textarea name="urls" rows="8" class="large-text code" placeholder="https://example.com/page-1&#10;My Page,https://example.com/page-2"></textarea>
					</p>
					<p>
						<label><input type="checkbox" name="queue" value="1" checked /> <?php esc_html_e( 'Process in background', 'wp-openrag' ); ?></label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add URLs', 'wp-openrag' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<h2><?php esc_html_e( 'Indexed URLs', 'wp-openrag' ); ?></h2>
				<table class="widefat striped" id="wporag-link-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'URL', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Chunks', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-openrag' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-openrag' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No URLs yet.', 'wp-openrag' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr data-id="<?php echo esc_attr( $row->id ); ?>">
								<td><a href="<?php echo esc_url( $row->source_url ); ?>" target="_blank"><?php echo esc_html( $row->title ?: $row->source_url ); ?></a></td>
								<td><?php echo esc_html( $row->chunk_count ); ?></td>
								<td><?php echo '<span class="wporag-status wporag-status-' . esc_attr( $row->status ) . '">' . esc_html( $row->status ) . '</span>'; ?></td>
								<td>
									<button class="button button-small wporag-reindex" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Reindex', 'wp-openrag' ); ?></button>
									<button class="button button-small button-link-delete wporag-delete" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Delete', 'wp-openrag' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	protected function render_wp_tab() {
		$indexing  = Settings::group( 'indexing' );
		$post_types = (array) ( $indexing['post_types'] ?? array( 'post', 'page' ) );
		$types     = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="wporag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Index WordPress Content', 'wp-openrag' ); ?></h2>
				<p><?php esc_html_e( 'Choose which content types to make available to the chatbot. Indexed posts become retrievable knowledge and their permalinks are used as citation sources.', 'wp-openrag' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'wporag_save_wp_index' ); ?>
					<input type="hidden" name="wporag_action" value="save_wp_index" />
					<fieldset>
						<legend><?php esc_html_e( 'Post types to index', 'wp-openrag' ); ?></legend>
						<?php foreach ( $types as $slug => $obj ) : ?>
							<label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $post_types, true ) ); ?> /> <?php echo esc_html( $obj->labels->name ); ?></label><br/>
						<?php endforeach; ?>
					</fieldset>
					<p>
						<label><input type="checkbox" name="auto_index" value="1" <?php checked( ! empty( $indexing['auto_index'] ) ); ?> /> <?php esc_html_e( 'Auto-index on publish/update', 'wp-openrag' ); ?></label>
					</p>
					<p><button class="button button-primary"><?php esc_html_e( 'Save', 'wp-openrag' ); ?></button></p>
				</form>

				<h3><?php esc_html_e( 'Bulk index now', 'wp-openrag' ); ?></h3>
				<button class="button" id="wporag-index-now"><?php esc_html_e( 'Queue all published content for indexing', 'wp-openrag' ); ?></button>
				<span id="wporag-index-now-msg"></span>
			</div>

			<div>
				<h2><?php esc_html_e( 'How indexing works', 'wp-openrag' ); ?></h2>
				<ul class="wporag-help">
					<li><?php esc_html_e( 'Posts/pages are read, their rendered content is extracted, and split into overlapping chunks.', 'wp-openrag' ); ?></li>
					<li><?php esc_html_e( 'Each chunk is embedded by your active embedding provider and stored in the vector store.', 'wp-openrag' ); ?></li>
					<li><?php esc_html_e( 'Background jobs are processed by Action Scheduler — one post per job to avoid timeouts.', 'wp-openrag' ); ?></li>
					<li><?php esc_html_e( 'When a post is trashed, its chunks are removed automatically.', 'wp-openrag' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
