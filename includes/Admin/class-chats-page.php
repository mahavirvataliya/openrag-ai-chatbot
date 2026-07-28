<?php
/**
 * Chats admin page — list all conversations, feedback, detail modal, CSV export.
 *
 * @package OpenRag\Admin
 */

namespace OpenRag\Admin;

use OpenRag\Database\Schema;

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
		// phpcs:ignore WordPress.DB.PreparedSQL
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		// Rows (user turns + their assistant replies joined).
		$sql = 'SELECT u.id, u.session_id, u.content AS user_message, u.created_at, u.device, u.user_ip,
				a.id AS assistant_id, a.content AS reply, a.citations, a.reasoning, a.model, a.feedback, a.feedback_comment, a.response_time_ms
				FROM `' . $schema->table( 'chats' ) . '` u
				LEFT JOIN `' . $schema->table( 'chats' ) . '` a
				  ON a.session_id = u.session_id AND a.role = "assistant" AND a.id > u.id
				' . $where . '
				GROUP BY u.id
				ORDER BY u.id DESC
				LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$pages      = max( 1, (int) ceil( $total / $per_page ) );
		$base_url   = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-chats' );
		?>
		<div class="wrap openrag-admin-wrap">
			<h1><?php esc_html_e( 'Chats', 'openrag-ai-chatbot' ); ?> <a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'export', '1', $base_url ) ); ?>"><?php esc_html_e( 'Export CSV', 'openrag-ai-chatbot' ); ?></a></h1>

			<form method="get" class="openrag-filter-row">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::SLUG ); ?>-chats" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search messages…', 'openrag-ai-chatbot' ); ?>" />
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" />
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" />
				<button class="button"><?php esc_html_e( 'Filter', 'openrag-ai-chatbot' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
				<tr>
					<th style="width:90px"><?php esc_html_e( 'When', 'openrag-ai-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Message', 'openrag-ai-chatbot' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Device', 'openrag-ai-chatbot' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Feedback', 'openrag-ai-chatbot' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Actions', 'openrag-ai-chatbot' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No chats found.', 'openrag-ai-chatbot' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><strong><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $row->user_message ), 14 ) ); ?></strong></td>
							<td><?php echo esc_html( $row->device ); ?></td>
							<td>
								<?php if ( 'up' === $row->feedback ) : ?>👍<?php elseif ( 'down' === $row->feedback ) : ?>👎<?php else : ?>—<?php endif; ?>
							</td>
							<td>
								<button class="button button-small openrag-view-chat" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'View', 'openrag-ai-chatbot' ); ?></button>
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

		$sql = 'SELECT u.id, u.session_id, u.content AS user_message, u.created_at, u.device, u.user_ip,
				a.content AS reply, a.citations, a.model, a.feedback, a.feedback_comment, a.response_time_ms
				FROM `' . $schema->table( 'chats' ) . '` u
				LEFT JOIN `' . $schema->table( 'chats' ) . '` a
				  ON a.session_id = u.session_id AND a.role = "assistant" AND a.id > u.id
				WHERE u.role = "user"
				GROUP BY u.id
				ORDER BY u.id DESC';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="openrag-chats.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'created_at', 'session_id', 'ip', 'device', 'user_message', 'reply', 'model', 'response_ms', 'feedback', 'feedback_comment' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $wpdb->get_results( $sql ) as $row ) {
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
					$row->feedback,
					$row->feedback_comment,
				)
			);
		}
		fclose( $out );
		exit;
	}
}
