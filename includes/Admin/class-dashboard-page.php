<?php
/**
 * Admin Dashboard page — overview cards + recent activity.
 *
 * @package WPOpenRag\Admin
 */

namespace WPOpenRag\Admin;

use WPOpenRag\Database\Schema;
use WPOpenRag\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard_Page {

	/**
	 * @var Plugin
	 */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function render() {
		$schema = new Schema();
		global $wpdb;

		$documents = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'documents' ) . '`' ); // phpcs:ignore WordPress.DB
		$chunks    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'chunks' ) . '`' ); // phpcs:ignore WordPress.DB
		$chats     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'chats' ) . "` WHERE role = 'assistant'" ); // phpcs:ignore WordPress.DB
		$thumbs_up   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . $schema->table( 'chats' ) . "` WHERE feedback = 'up'" ); // phpcs:ignore WordPress.DB
		$thumbs_down = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . $schema->table( 'chats' ) . "` WHERE feedback = 'down'" ); // phpcs:ignore WordPress.DB

		$recent = $wpdb->get_results( // phpcs:ignore WordPress.DB
			'SELECT id, role, content, created_at FROM `' . $schema->table( 'chats' ) . '` ORDER BY id DESC LIMIT 10'
		);

		// Provider + vector store status.
		$llm_label    = $this->plugin->llm()->provider()->label();
		$llm_ok       = $this->plugin->llm()->provider()->is_configured();
		$emb_label    = $this->plugin->embeddings()->provider()->label();
		$emb_ok       = $this->plugin->embeddings()->provider()->is_configured();
		$store        = $this->plugin->vector_store()->store();
		$mysql_native = $this->plugin->vector_store()->is_mysql_native();

		?>
		<div class="wrap wporag-admin-wrap">
			<h1><?php esc_html_e( 'WP OpenRag — Dashboard', 'wp-openrag' ); ?></h1>

			<div class="wporag-cards">
				<div class="wporag-card">
					<div class="wporag-card-value"><?php echo esc_html( number_format_i18n( $documents ) ); ?></div>
					<div class="wporag-card-label"><?php esc_html_e( 'Documents', 'wp-openrag' ); ?></div>
				</div>
				<div class="wporag-card">
					<div class="wporag-card-value"><?php echo esc_html( number_format_i18n( $chunks ) ); ?></div>
					<div class="wporag-card-label"><?php esc_html_e( 'Chunks', 'wp-openrag' ); ?></div>
				</div>
				<div class="wporag-card">
					<div class="wporag-card-value"><?php echo esc_html( number_format_i18n( $chats ) ); ?></div>
					<div class="wporag-card-label"><?php esc_html_e( 'Chats', 'wp-openrag' ); ?></div>
				</div>
				<div class="wporag-card">
					<div class="wporag-card-value">👍 <?php echo esc_html( number_format_i18n( $thumbs_up ) ); ?> / 👎 <?php echo esc_html( number_format_i18n( $thumbs_down ) ); ?></div>
					<div class="wporag-card-label"><?php esc_html_e( 'Feedback', 'wp-openrag' ); ?></div>
				</div>
			</div>

			<div class="wporag-admin-grid">
				<div class="wporag-admin-col">
					<h2><?php esc_html_e( 'System Status', 'wp-openrag' ); ?></h2>
					<table class="widefat striped">
						<tbody>
						<tr>
							<th><?php esc_html_e( 'LLM Provider', 'wp-openrag' ); ?></th>
							<td>
								<?php echo esc_html( $llm_label ); ?>
								<span class="wporag-badge <?php echo $llm_ok ? 'ok' : 'err'; ?>"><?php echo $llm_ok ? esc_html__( 'Configured', 'wp-openrag' ) : esc_html__( 'Not configured', 'wp-openrag' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Embedding Provider', 'wp-openrag' ); ?></th>
							<td>
								<?php echo esc_html( $emb_label ); ?>
								<span class="wporag-badge <?php echo $emb_ok ? 'ok' : 'err'; ?>"><?php echo $emb_ok ? esc_html__( 'Configured', 'wp-openrag' ) : esc_html__( 'Not configured', 'wp-openrag' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Vector Store', 'wp-openrag' ); ?></th>
							<td>
								<?php echo esc_html( $store->label() ); ?>
								<?php if ( $store instanceof \WPOpenRag\VectorStores\MySQL_Store ) : ?>
									<?php if ( $mysql_native ) : ?>
										<span class="wporag-badge ok"><?php esc_html_e( 'MySQL 9 native VECTOR', 'wp-openrag' ); ?></span>
									<?php else : ?>
										<span class="wporag-badge warn"><?php esc_html_e( 'MySQL (JSON fallback)', 'wp-openrag' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						</tbody>
					</table>
				</div>

				<div class="wporag-admin-col">
					<h2><?php esc_html_e( 'Recent Activity', 'wp-openrag' ); ?></h2>
					<table class="widefat striped">
						<thead>
						<tr><th><?php esc_html_e( 'Role', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'Excerpt', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'When', 'wp-openrag' ); ?></th></tr>
						</thead>
						<tbody>
						<?php if ( empty( $recent ) ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'No activity yet.', 'wp-openrag' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $recent as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->role ); ?></td>
									<td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $row->content ), 12 ) ); ?></td>
									<td><?php echo esc_html( $row->created_at ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}
