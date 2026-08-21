<?php
/**
 * Admin Dashboard page — overview cards + recent activity.
 *
 * @package ItihRag\Admin
 */

namespace ItihRag\Admin;

use ItihRag\Database\Schema;
use ItihRag\Plugin;

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

		// One aggregate pass over chats (60s transient) instead of five full scans.
		$stats = get_transient( 'itih_dash_stats' );
		if ( ! is_array( $stats ) ) {
			// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
			$row = $wpdb->get_row( "SELECT COUNT(*) AS replies, COALESCE(SUM(feedback = 'up'),0) AS thumbs_up, COALESCE(SUM(feedback = 'down'),0) AS thumbs_down, COALESCE(SUM(CASE WHEN role = 'assistant' THEN prompt_tokens END),0) AS prompt_tokens, COALESCE(SUM(CASE WHEN role = 'assistant' THEN completion_tokens END),0) AS completion_tokens FROM `" . $schema->table( 'chats' ) . '`' );
			$stats = array(
				'replies'           => (int) ( $row->replies ?? 0 ),
				'thumbs_up'         => (int) ( $row->thumbs_up ?? 0 ),
				'thumbs_down'       => (int) ( $row->thumbs_down ?? 0 ),
				'prompt_tokens'     => (int) ( $row->prompt_tokens ?? 0 ),
				'completion_tokens' => (int) ( $row->completion_tokens ?? 0 ),
			);
			set_transient( 'itih_dash_stats', $stats, 60 );
		}
		$documents = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'documents' ) . '`' ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$chunks    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'chunks' ) . '`' ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$chats       = $stats['replies'];
		$thumbs_up   = $stats['thumbs_up'];
		$thumbs_down = $stats['thumbs_down'];

		// Token usage totals (assistant turns only).
		$prompt_tokens     = $stats['prompt_tokens'];
		$completion_tokens = $stats['completion_tokens'];
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
			<h1><?php esc_html_e( 'ItihRag AI Chatbot — Dashboard', 'itih-ai-chatbot' ); ?></h1>

			<div class="openrag-cards">
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $documents ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Documents', 'itih-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $chunks ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Chunks', 'itih-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $chats ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Chats', 'itih-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value">👍 <?php echo esc_html( number_format_i18n( $thumbs_up ) ); ?> / 👎 <?php echo esc_html( number_format_i18n( $thumbs_down ) ); ?></div>
					<div class="openrag-card-label"><?php esc_html_e( 'Feedback', 'itih-ai-chatbot' ); ?></div>
				</div>
				<div class="openrag-card">
					<div class="openrag-card-value"><?php echo esc_html( number_format_i18n( $total_tokens ) ); ?></div>
					<div class="openrag-card-label">
						<?php esc_html_e( 'Tokens Used', 'itih-ai-chatbot' ); ?><br>
						<small><?php echo esc_html( sprintf( /* translators: 1: prompt tokens, 2: completion tokens */ __( '%1$s prompt · %2$s completion', 'itih-ai-chatbot' ), number_format_i18n( $prompt_tokens ), number_format_i18n( $completion_tokens ) ) ); ?></small>
					</div>
				</div>
			</div>

			<div class="openrag-admin-grid">
				<div class="openrag-admin-col">
					<h2><?php esc_html_e( 'System Status', 'itih-ai-chatbot' ); ?></h2>
					<table class="widefat striped">
						<tbody>
						<tr>
							<th><?php esc_html_e( 'LLM Provider', 'itih-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $llm_label ); ?>
								<span class="openrag-badge <?php echo $llm_ok ? 'ok' : 'err'; ?>"><?php echo $llm_ok ? esc_html__( 'Configured', 'itih-ai-chatbot' ) : esc_html__( 'Not configured', 'itih-ai-chatbot' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Embedding Provider', 'itih-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $emb_label ); ?>
								<span class="openrag-badge <?php echo $emb_ok ? 'ok' : 'err'; ?>"><?php echo $emb_ok ? esc_html__( 'Configured', 'itih-ai-chatbot' ) : esc_html__( 'Not configured', 'itih-ai-chatbot' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Vector Store', 'itih-ai-chatbot' ); ?></th>
							<td>
								<?php echo esc_html( $store->label() ); ?>
								<?php if ( $store instanceof \ItihRag\VectorStores\MySQL_Store ) : ?>
									<?php if ( $mysql_native ) : ?>
										<span class="openrag-badge ok"><?php esc_html_e( 'MySQL 9 native VECTOR', 'itih-ai-chatbot' ); ?></span>
									<?php else : ?>
										<span class="openrag-badge warn"><?php esc_html_e( 'MySQL (JSON fallback)', 'itih-ai-chatbot' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						</tbody>
					</table>
				</div>

				<div class="openrag-admin-col">
					<h2><?php esc_html_e( 'Recent Activity', 'itih-ai-chatbot' ); ?></h2>
					<table class="widefat striped">
						<thead>
						<tr><th><?php esc_html_e( 'Role', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Excerpt', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'When', 'itih-ai-chatbot' ); ?></th></tr>
						</thead>
						<tbody>
						<?php if ( empty( $recent ) ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'No activity yet.', 'itih-ai-chatbot' ); ?></td></tr>
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
