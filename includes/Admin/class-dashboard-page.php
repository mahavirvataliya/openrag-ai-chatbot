<?php
/**
 * Admin Dashboard page — overview cards + recent activity.
 *
 * @package OpenRag\Admin
 */

namespace OpenRag\Admin;

use OpenRag\Database\Schema;
use OpenRag\Plugin;

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

		$documents = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'documents' ) . '`' ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$chunks    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'chunks' ) . '`' ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$chats     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'chats' ) . "` WHERE role = 'assistant'" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$thumbs_up   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . $schema->table( 'chats' ) . "` WHERE feedback = 'up'" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$thumbs_down = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . $schema->table( 'chats' ) . "` WHERE feedback = 'down'" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB

		// Token usage totals (assistant turns only).
		$prompt_tokens     = (int) $wpdb->get_var( "SELECT COALESCE(SUM(prompt_tokens),0) FROM `" . $schema->table( 'chats' ) . "` WHERE role = 'assistant'" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$completion_tokens = (int) $wpdb->get_var( "SELECT COALESCE(SUM(completion_tokens),0) FROM `" . $schema->table( 'chats' ) . "` WHERE role = 'assistant'" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$total_tokens      = $prompt_tokens + $completion_tokens;

		$recent = $wpdb->get_results( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			'SELECT id, role, content, created_at FROM `' . $schema->table( 'chats' ) . '` ORDER BY id DESC LIMIT 10' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Provider + vector store status.
		$llm_label    = $this->plugin->llm()->provider()->label();
		$llm_ok       = $this->plugin->llm()->provider()->is_configured();
		$emb_label    = $this->plugin->embeddings()->provider()->label();
		$emb_ok       = $this->plugin->embeddings()->provider()->is_configured();
		$store        = $this->plugin->vector_store()->store();
		$mysql_native = $this->plugin->vector_store()->is_mysql_native();

		?>
		<div class="wrap openrag-admin-wrap">
			<h1><?php esc_html_e( 'OpenRag AI Chatbot — Dashboard', 'openrag-ai-chatbot' ); ?></h1>

			<div class="openrag-cards">
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $documents ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Documents', 'openrag-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $chunks ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Chunks', 'openrag-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $chats ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Chats', 'openrag-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value">👍 <?php echo esc_html( number_format_i18n( $thumbs_up ) ); ?> / 👎 <?php echo esc_html( number_format_i18n( $thumbs_down ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Feedback', 'openrag-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></div>
					<div class="openrag-card-label">
						<?php esc_html_e( 'Tokens Used', 'openrag-ai-chatbot' ); ?><br>
						<small><?php echo esc_html( sprintf( /* translators: 1: prompt tokens, 2: completion tokens */ __( '%1$s prompt · %2$s completion', 'openrag-ai-chatbot' ), number_format_i18n( $prompt_tokens ), number_format_i18n( $completion_tokens ) ) ); ?></small>
					</div>
				</div>
			</div>

			<div class="openrag-admin-grid">
				<div class="openrag-admin-col">
					<h2><?php esc_html_e( 'System Status', 'openrag-ai-chatbot' ); ?></h2>
					<table class="widefat striped">
						<tbody>
						<tr>
							<th><?php esc_html_e( 'LLM Provider', 'openrag-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $llm_label ); ?>
								<span class="openrag-badge <?php echo $llm_ok ? 'ok' : 'err'; ?>"><?php echo $llm_ok ? esc_html__( 'Configured', 'openrag-ai-chatbot' ) : esc_html__( 'Not configured', 'openrag-ai-chatbot' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Embedding Provider', 'openrag-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $emb_label ); ?>
								<span class="openrag-badge <?php echo $emb_ok ? 'ok' : 'err'; ?>"><?php echo $emb_ok ? esc_html__( 'Configured', 'openrag-ai-chatbot' ) : esc_html__( 'Not configured', 'openrag-ai-chatbot' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Vector Store', 'openrag-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $store->label() ); ?>
								<?php if ( $store instanceof \OpenRag\VectorStores\MySQL_Store ) : ?>
									<?php if ( $mysql_native ) : ?>
										<span class="openrag-badge ok"><?php esc_html_e( 'MySQL 9 native VECTOR', 'openrag-ai-chatbot' ); ?></span>
									<?php else : ?>
										<span class="openrag-badge warn"><?php esc_html_e( 'MySQL (JSON fallback)', 'openrag-ai-chatbot' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						</tbody>
					</table>
				</div>

				<div class="openrag-admin-col">
					<h2><?php esc_html_e( 'Recent Activity', 'openrag-ai-chatbot' ); ?></h2>
					<table class="widefat striped">
						<thead>
						<tr><th><?php esc_html_e( 'Role', 'openrag-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Excerpt', 'openrag-ai-chatbot' ); ?></th><th><?php esc_html_e( 'When', 'openrag-ai-chatbot' ); ?></th></tr>
						</thead>
						<tbody>
						<?php if ( empty( $recent ) ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'No activity yet.', 'openrag-ai-chatbot' ); ?></td></tr>
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
