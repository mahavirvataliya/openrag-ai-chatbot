<?php
/**
 * Knowledge Base admin page — documents, links, WP content tabs.
 *
 * @package OpenRag\Admin
 */

namespace OpenRag\Admin;

use OpenRag\Database\Schema;
use OpenRag\Plugin;
use OpenRag\Settings;

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
		if ( ! isset( $_POST['openrag_action'] ) || 'save_wp_index' !== $_POST['openrag_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'openrag_save_wp_index' );

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		$auto       = empty( $_POST['auto_index'] ) ? '0' : '1';

		$indexing = \OpenRag\Settings::group( 'indexing' );
		$indexing['post_types'] = $post_types;
		$indexing['auto_index'] = $auto;
		\OpenRag\Settings::save_group( 'indexing', $indexing );

		add_settings_error( 'openrag', 'saved', __( 'Indexing settings saved.', 'openrag-ai-chatbot' ), 'updated' );
	}

	public function render() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'documents'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'documents' => __( 'Documents & Files', 'openrag-ai-chatbot' ),
			'links'     => __( 'URLs', 'openrag-ai-chatbot' ),
			'wp'        => __( 'WordPress Content', 'openrag-ai-chatbot' ),
		);

		$base_url = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-kb' );
		?>
		<div class="wrap openrag-admin-wrap">
			<h1><?php esc_html_e( 'Knowledge Base', 'openrag-ai-chatbot' ); ?></h1>
			<?php settings_errors( 'openrag' ); ?>

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
		$rows   = $wpdb->get_results( "SELECT * FROM `" . $schema->table( 'documents' ) . "` WHERE type IN ('pdf','docx','txt') ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		$upload = wp_upload_dir();
		?>
		<div class="openrag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Add Document', 'openrag-ai-chatbot' ); ?></h2>
				<form id="openrag-add-doc" class="openrag-form">
					<p>
						<label><?php esc_html_e( 'Title (optional)', 'openrag-ai-chatbot' ); ?></label>
						<input type="text" name="title" class="regular-text" />
					</p>
					<p>
						<label><?php esc_html_e( 'File URL (PDF/DOCX/TXT/MD)', 'openrag-ai-chatbot' ); ?></label>
						<input type="url" name="source_url" class="regular-text" placeholder="https://example.com/doc.pdf" />
					</p>
					<p class="openrag-or">— <?php esc_html_e( 'or', 'openrag-ai-chatbot' ); ?> —</p>
					<p>
						<label><?php esc_html_e( 'Upload File', 'openrag-ai-chatbot' ); ?></label>
						<button type="button" class="button" id="openrag-upload-btn"><?php esc_html_e( 'Choose file', 'openrag-ai-chatbot' ); ?></button>
						<input type="hidden" name="file_path" id="openrag-file-path" />
						<input type="hidden" name="mime_type" id="openrag-file-mime" />
						<span id="openrag-file-name"></span>
					</p>
					<p>
						<label><input type="checkbox" name="queue" value="1" checked /> <?php esc_html_e( 'Process immediately in the background', 'openrag-ai-chatbot' ); ?></label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add & Process', 'openrag-ai-chatbot' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<h2><?php esc_html_e( 'Documents', 'openrag-ai-chatbot' ); ?></h2>
				<table class="widefat striped" id="openrag-doc-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Type', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Chunks', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Status', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'openrag-ai-chatbot' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No documents yet.', 'openrag-ai-chatbot' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr data-id="<?php echo esc_attr( $row->id ); ?>">
								<td><?php echo esc_html( $row->title ?: basename( $row->source_url ?: $row->file_path ) ); ?></td>
								<td><?php echo esc_html( strtoupper( $row->type ) ); ?></td>
								<td><?php echo esc_html( $row->chunk_count ); ?></td>
								<td><?php echo '<span class="openrag-status openrag-status-' . esc_attr( $row->status ) . '">' . esc_html( $row->status ) . '</span>'; ?></td>
								<td>
									<button class="button button-small openrag-reindex" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Reindex', 'openrag-ai-chatbot' ); ?></button>
									<button class="button button-small openrag-view-chunks" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Chunks', 'openrag-ai-chatbot' ); ?></button>
									<button class="button button-small button-link-delete openrag-delete" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Delete', 'openrag-ai-chatbot' ); ?></button>
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
		$rows   = $wpdb->get_results( "SELECT * FROM `" . $schema->table( 'documents' ) . "` WHERE type = 'url' ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		?>
		<div class="openrag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Add URLs', 'openrag-ai-chatbot' ); ?></h2>
				<form id="openrag-add-urls" class="openrag-form">
					<p>
						<label><?php esc_html_e( 'One URL per line (or CSV: title,url)', 'openrag-ai-chatbot' ); ?></label>
						<textarea name="urls" rows="8" class="large-text code" placeholder="https://example.com/page-1&#10;My Page,https://example.com/page-2"></textarea>
					</p>
					<p>
						<label><input type="checkbox" name="queue" value="1" checked /> <?php esc_html_e( 'Process in background', 'openrag-ai-chatbot' ); ?></label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add URLs', 'openrag-ai-chatbot' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<h2><?php esc_html_e( 'Indexed URLs', 'openrag-ai-chatbot' ); ?></h2>
				<table class="widefat striped" id="openrag-link-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'URL', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Chunks', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Status', 'openrag-ai-chatbot' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'openrag-ai-chatbot' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No URLs yet.', 'openrag-ai-chatbot' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr data-id="<?php echo esc_attr( $row->id ); ?>">
								<td><a href="<?php echo esc_url( $row->source_url ); ?>" target="_blank"><?php echo esc_html( $row->title ?: $row->source_url ); ?></a></td>
								<td><?php echo esc_html( $row->chunk_count ); ?></td>
								<td><?php echo '<span class="openrag-status openrag-status-' . esc_attr( $row->status ) . '">' . esc_html( $row->status ) . '</span>'; ?></td>
								<td>
									<button class="button button-small openrag-reindex" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Reindex', 'openrag-ai-chatbot' ); ?></button>
									<button class="button button-small button-link-delete openrag-delete" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'Delete', 'openrag-ai-chatbot' ); ?></button>
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
		<div class="openrag-kb-grid">
			<div>
				<h2><?php esc_html_e( 'Index WordPress Content', 'openrag-ai-chatbot' ); ?></h2>
				<p><?php esc_html_e( 'Choose which content types to make available to the chatbot. Indexed posts become retrievable knowledge and their permalinks are used as citation sources.', 'openrag-ai-chatbot' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'openrag_save_wp_index' ); ?>
					<input type="hidden" name="openrag_action" value="save_wp_index" />
					<fieldset>
						<legend><?php esc_html_e( 'Post types to index', 'openrag-ai-chatbot' ); ?></legend>
						<?php foreach ( $types as $slug => $obj ) : ?>
							<label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $post_types, true ) ); ?> /> <?php echo esc_html( $obj->labels->name ); ?></label><br/>
						<?php endforeach; ?>
					</fieldset>
					<p>
						<label><input type="checkbox" name="auto_index" value="1" <?php checked( ! empty( $indexing['auto_index'] ) ); ?> /> <?php esc_html_e( 'Auto-index on publish/update', 'openrag-ai-chatbot' ); ?></label>
					</p>
					<p><button class="button button-primary"><?php esc_html_e( 'Save', 'openrag-ai-chatbot' ); ?></button></p>
				</form>

				<h3><?php esc_html_e( 'Bulk index now', 'openrag-ai-chatbot' ); ?></h3>
				<button class="button" id="openrag-index-now"><?php esc_html_e( 'Queue all published content for indexing', 'openrag-ai-chatbot' ); ?></button>
				<span id="openrag-index-now-msg"></span>
			</div>

			<div>
				<h2><?php esc_html_e( 'How indexing works', 'openrag-ai-chatbot' ); ?></h2>
				<ul class="openrag-help">
					<li><?php esc_html_e( 'Posts/pages are read, their rendered content is extracted, and split into overlapping chunks.', 'openrag-ai-chatbot' ); ?></li>
					<li><?php esc_html_e( 'Each chunk is embedded by your active embedding provider and stored in the vector store.', 'openrag-ai-chatbot' ); ?></li>
					<li><?php esc_html_e( 'Background jobs are processed by Action Scheduler — one post per job to avoid timeouts.', 'openrag-ai-chatbot' ); ?></li>
					<li><?php esc_html_e( 'When a post is trashed, its chunks are removed automatically.', 'openrag-ai-chatbot' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
