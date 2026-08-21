<?php
/**
 * Chats admin page — list all conversations, feedback, detail modal, CSV export.
 *
 * @package ItihRag\Admin
 */

namespace ItihRag\Admin;

use ItihRag\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chats_Page {

	public function render() {
		global $wpdb;
		$schema = new Schema();

		// CSV export.
		if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->export_csv();
			return;
		}

		$per_page = 25;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$offset   = ( $paged - 1 ) * $per_page;

		$where  = " WHERE role = 'user' ";
		$params = array();

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $search ) {
			$where .= ' AND (content LIKE %s OR session_id IN (SELECT session_id FROM `' . $schema->table( 'chats' ) . '` WHERE content LIKE %s)) ';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $from ) {
			$where .= ' AND created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( '' !== $to ) {
			$where .= ' AND created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		// Count.
		$count_sql = 'SELECT COUNT(*) FROM `' . $schema->table( 'chats' ) . '` ' . $where;
		// phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		// Rows (user turns + their assistant replies joined). The correlated
		// MIN(a2.id) subquery picks exactly the NEXT assistant turn — the old
		// open-ended self-join materialized every later reply per user turn.
		$chats = $schema->table( 'chats' );
		$sql = 'SELECT u.id, u.session_id, u.content AS user_message, u.created_at, u.device, u.user_ip,
				a.id AS assistant_id, a.content AS reply, a.citations, a.reasoning, a.model, a.feedback, a.feedback_comment, a.response_time_ms, a.prompt_tokens, a.completion_tokens
				FROM `' . $chats . '` u
				LEFT JOIN `' . $chats . '` a ON a.id = (
					SELECT MIN( a2.id ) FROM `' . $chats . '` a2
					WHERE a2.session_id = u.session_id AND a2.role = "assistant" AND a2.id > u.id
				)
				' . $where . '
				ORDER BY u.id DESC
				LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$pages      = max( 1, (int) ceil( $total / $per_page ) );
		$base_url   = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-chats' );
		?>
		<div class="wrap openrag-admin-wrap">
			<h1><?php esc_html_e( 'Chats', 'itih-ai-chatbot' ); ?> <a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'export', '1', $base_url ) ); ?>"><?php esc_html_e( 'Export CSV', 'itih-ai-chatbot' ); ?></a></h1>

			<form method="get" class="openrag-filter-row">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::SLUG ); ?>-chats" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search messages…', 'itih-ai-chatbot' ); ?>" />
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" />
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" />
				<button class="button"><?php esc_html_e( 'Filter', 'itih-ai-chatbot' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
				<tr>
					<th style="width:90px"><?php esc_html_e( 'When', 'itih-ai-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Message', 'itih-ai-chatbot' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Device', 'itih-ai-chatbot' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Tokens', 'itih-ai-chatbot' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Feedback', 'itih-ai-chatbot' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Actions', 'itih-ai-chatbot' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No chats found.', 'itih-ai-chatbot' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><strong><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $row->user_message ), 14 ) ); ?></strong></td>
							<td><?php echo esc_html( $row->device ); ?></td>
							<td>
								<?php
								$row_pt = (int) ( $row->prompt_tokens ?? 0 );
								$row_ct = (int) ( $row->completion_tokens ?? 0 );
								echo esc_html( ( $row_pt + $row_ct ) > 0 ? number_format_i18n( $row_pt + $row_ct ) : '—' );
								?>
							</td>
							<td>
								<?php if ( 'up' === $row->feedback ) : ?>👍<?php elseif ( 'down' === $row->feedback ) : ?>👎<?php else : ?>—<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small openrag-view-chat"
									data-id="<?php echo esc_attr( $row->id ); ?>"
									data-created-at="<?php echo esc_attr( $row->created_at ); ?>"
									data-content="<?php echo esc_attr( $row->user_message ); ?>"
									data-reply="<?php echo esc_attr( $row->reply ?? '' ); ?>"
									data-reasoning="<?php echo esc_attr( $row->reasoning ?? '' ); ?>"
									data-citations="<?php echo esc_attr( $row->citations ?? '' ); ?>"
									data-model="<?php echo esc_attr( $row->model ?? '' ); ?>"
									data-feedback="<?php echo esc_attr( $row->feedback ?? '' ); ?>"
									data-tokens="<?php echo esc_attr( (int) ( $row->prompt_tokens ?? 0 ) + (int) ( $row->completion_tokens ?? 0 ) ); ?>"
									data-response-ms="<?php echo esc_attr( $row->response_time_ms ?? '' ); ?>"
								><?php esc_html_e( 'View', 'itih-ai-chatbot' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
				array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			);
			echo '</div></div>';
			?>
		</div>

		<!-- Detail modal -->
		<div id="openrag-chat-modal" class="openrag-modal" style="display:none;">
			<div class="openrag-modal-content">
				<button type="button" class="openrag-modal-close" aria-label="Close">&times;</button>
				<div id="openrag-chat-detail"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Stream a CSV export of all user turns + their assistant replies.
	 *
	 * @return void
	 */
	protected function export_csv() {
		global $wpdb;
		$schema = new Schema();
		$chats  = $schema->table( 'chats' );

		// Keyset-paginated batches keep memory flat on large chat tables.
		$base_sql = 'SELECT u.id, u.session_id, u.content AS user_message, u.created_at, u.device, u.user_ip,
				a.content AS reply, a.citations, a.model, a.feedback, a.feedback_comment, a.response_time_ms, a.prompt_tokens, a.completion_tokens
				FROM `' . $chats . '` u
				LEFT JOIN `' . $chats . '` a ON a.id = (
					SELECT MIN( a2.id ) FROM `' . $chats . '` a2
					WHERE a2.session_id = u.session_id AND a2.role = "assistant" AND a2.id > u.id
				)
				WHERE u.role = "user" AND u.id > %d
				ORDER BY u.id ASC
				LIMIT 1000';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="itih-chats.csv"' );

		$out    = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'created_at', 'session_id', 'ip', 'device', 'user_message', 'reply', 'model', 'response_ms', 'prompt_tokens', 'completion_tokens', 'feedback', 'feedback_comment' ) );

		$last_id = 0;
		while ( true ) {
			// phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$rows = $wpdb->get_results( $wpdb->prepare( $base_sql, $last_id ) );
			if ( empty( $rows ) ) {
				break;
			}
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array(
						$row->id,
						$row->created_at,
						$row->session_id,
						$row->user_ip,
						$row->device,
						$row->user_message,
						$row->reply,
						$row->model,
						$row->response_time_ms,
						$row->prompt_tokens,
						$row->completion_tokens,
						$row->feedback,
						$row->feedback_comment,
					)
				);
			}
			$last_id = (int) end( $rows )->id;
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output streams must be closed with fclose(); WP_Filesystem cannot stream to the browser.
		exit;
	}
}
