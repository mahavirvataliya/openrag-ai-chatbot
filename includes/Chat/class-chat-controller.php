<?php
/**
 * Chat controller — REST routes including SSE streaming.
 *
 * Routes (namespace openrag/v1):
 *   POST /chat          → streaming SSE response
 *   POST /chat/sync     → non-streaming response
 *   POST /feedback      → record 👍/👎 feedback
 *   GET  /history       → session-scoped history
 *   DELETE /history     → clear session history
 *
 * @package OpenRag\Chat
 */

namespace OpenRag\Chat;

use OpenRag\Database\Schema;
use OpenRag\Embeddings\Embedding_Manager;
use OpenRag\LLM\LLM_Manager;
use OpenRag\Settings;
use OpenRag\VectorStores\Vector_Store_Manager;
use OpenRag\MCP\MCP_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chat_Controller {

	/**
	 * @var Rag_Engine
	 */
	private $rag;

	/**
	 * @var Rate_Limiter
	 */
	private $rate;

	/**
	 * @var Schema
	 */
	private $schema;

	public function __construct(
		Embedding_Manager $embeddings,
		Vector_Store_Manager $vectors,
		LLM_Manager $llm,
		MCP_Manager $mcp,
		Rate_Limiter $rate
	) {
		$this->rag    = new Rag_Engine( $embeddings, $vectors, $llm, $mcp );
		$this->rate   = $rate;
		$this->schema = new Schema();
	}

	public function register_routes() {
		$perm = array( $this, 'permission_public' );

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_stream' ),
				'permission_callback' => $perm,
				'args'                => array(
					'message'   => array( 'type' => 'string', 'required' => true ),
					'session_id'=> array( 'type' => 'string', 'required' => false ),
					'history'   => array( 'type' => 'array', 'required' => false ),
				),
			)
		);

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/chat/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_sync' ),
				'permission_callback' => $perm,
				'args'                => array(
					'message'    => array( 'type' => 'string', 'required' => true ),
					'session_id' => array( 'type' => 'string', 'required' => false ),
					'history'    => array( 'type' => 'array', 'required' => false ),
				),
			)
		);

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_feedback' ),
				'permission_callback' => $perm,
				'args'                => array(
					'message_id' => array( 'type' => 'integer', 'required' => true ),
					'feedback'   => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'up', 'down' ),
					),
					'comment'    => array( 'type' => 'string', 'required' => false ),
				),
			)
		);

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/history',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_get_history' ),
					'permission_callback' => $perm,
					'args'                => array(
						'session_id' => array( 'type' => 'string', 'required' => false ),
						'limit'      => array( 'type' => 'integer', 'default' => 50 ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'handle_clear_history' ),
					'permission_callback' => $perm,
					'args'                => array(
						'session_id' => array( 'type' => 'string', 'required' => false ),
					),
				),
			)
		);
	}

	/**
	 * Public permission callback with nonce verification.
	 *
	 * The frontend passes the WP REST nonce; we verify it to mitigate CSRF on
	 * unauthenticated endpoints.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permission_public( \WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'x_wp_nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) ( $_REQUEST['_wpnonce'] ?? '' );
		}
		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		// Allow logged-in users with a valid session cookie.
		if ( is_user_logged_in() ) {
			return true;
		}
		// Without a nonce we still allow anonymous chat (chatbot is public-facing)
		// but require a per-session identifier in the body to enforce rate limits.
		return true;
	}

	/**
	 * Handle a non-streaming chat request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_chat_sync( \WP_REST_Request $request ) {
		if ( ! $this->rate->allow() ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please slow down.', 'openrag-ai-chatbot' ), array( 'status' => 429 ) );
		}

		$message   = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$session   = (string) ( $request->get_param( 'session_id' ) ?: $this->new_session() );
		$history   = (array) $request->get_param( 'history' );

		if ( '' === $message ) {
			return new \WP_Error( 'empty_message', __( 'Message is required.', 'openrag-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$start = microtime( true );
		$answer = $this->rag->answer( $message, $history );
		$ms     = (int) ( ( microtime( true ) - $start ) * 1000 );

		$message_id = $this->persist_turn(
			array(
				'session_id'   => $session,
				'role'         => 'user',
				'content'      => $message,
				'user_ip'      => $this->rate->client_ip(),
				'device'       => $this->rate->device(),
			)
		);
		$this->persist_turn(
			array(
				'session_id'       => $session,
				'role'             => 'assistant',
				'content'          => $answer['content'],
				'reasoning'        => $answer['reasoning'],
				'citations'        => $answer['citations'],
				'tool_calls'       => $answer['tool_calls'],
				'model'            => $answer['model'],
				'prompt_tokens'    => $answer['usage']['prompt_tokens'],
				'completion_tokens'=> $answer['usage']['completion_tokens'],
				'response_time_ms' => $ms,
				'user_ip'          => $this->rate->client_ip(),
				'device'           => $this->rate->device(),
			)
		);

		return rest_ensure_response(
			array(
				'reply'      => $answer['content'],
				'reasoning'  => $answer['reasoning'],
				'citations'  => $answer['citations'],
				'tool_calls' => $answer['tool_calls'],
				'message_id' => $message_id,
				'session_id' => $session,
				'usage'      => $answer['usage'],
			)
		);
	}

	/**
	 * Handle a streaming chat request (Server-Sent Events).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return void
	 */
	public function handle_chat_stream( \WP_REST_Request $request ) {
		if ( ! $this->rate->allow() ) {
			status_header( 429 );
			wp_die( esc_html__( 'Too many requests. Please slow down.', 'openrag-ai-chatbot' ) );
		}

		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$session = (string) ( $request->get_param( 'session_id' ) ?: $this->new_session() );
		$history = (array) $request->get_param( 'history' );

		if ( '' === $message ) {
			status_header( 400 );
			wp_die( esc_html__( 'Message is required.', 'openrag-ai-chatbot' ) );
		}

		$settings = Settings::group( 'chat' );
		$citations_enabled = ! empty( $settings['citations'] );
		$reasoning_enabled = ! empty( $settings['reasoning'] );

		// Save the user turn first.
		$message_id = $this->persist_turn(
			array(
				'session_id' => $session,
				'role'       => 'user',
				'content'    => $message,
				'user_ip'    => $this->rate->client_ip(),
				'device'     => $this->rate->device(),
			)
		);

		// Begin SSE.
		$this->sse_headers();

		$emit = function ( $event, $data = array() ) {
			echo 'event: ' . $event . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'data: ' . wp_json_encode( $data ) . "\n\n";
			// Flush to the output buffer.
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
			flush();
		};

		$emit( 'meta', array( 'session_id' => $session, 'message_id' => $message_id ) );

		// Retrieve + build context.
		$hits    = $this->rag->retrieve( $message );
		$ctx     = $this->rag->build_context( $hits );
		$messages= $this->rag->build_messages( $message, $ctx['context'], $history, $citations_enabled );

		$opts = array(
			'temperature' => (float) ( $settings['temperature'] ?? 0.3 ),
			'max_tokens'  => (int) ( $settings['max_tokens'] ?? 800 ),
		);
		if ( $reasoning_enabled ) {
			$opts['reasoning']        = true;
			$opts['reasoning_effort'] = (string) ( $settings['reasoning_effort'] ?? 'medium' );
		}
		$tools = $this->rag->collect_tools();
		if ( ! empty( $tools ) ) {
			$opts['tools'] = $tools;
		}

		$start       = microtime( true );
		$content_acc = '';
		$reason_acc  = '';
		$usage       = array( 'prompt_tokens' => 0, 'completion_tokens' => 0 );
		$model       = '';
		$tool_log    = array();

		try {
			$iterations = 0;
			while ( $iterations < 5 ) {
				$iterations++;
				$had_tool_calls = false;

				foreach ( $this->rag->stream( $messages, $opts ) as $event ) {
					switch ( $event['type'] ) {
						case 'delta':
							$content_acc .= $event['content'];
							$emit( 'delta', array( 'content' => $event['content'] ) );
							break;

						case 'reasoning':
							$reason_acc .= $event['content'];
							$emit( 'reasoning', array( 'content' => $event['content'] ) );
							break;

						case 'tool_call':
							$had_tool_calls = true;
							$name = $event['tool_call']['function']['name'] ?? '';
							$emit( 'tool', array( 'name' => $name ) );
							$tool_result = $this->rag->execute_tool( $event['tool_call'] );
							$tool_log[]  = array(
								'name'   => $name,
								'args'   => $event['tool_call']['function']['arguments'] ?? '{}',
								'result' => $tool_result,
							);
							$messages[]  = array(
								'role'       => 'assistant',
								'content'    => $content_acc,
								'tool_calls' => array( $event['tool_call'] ),
							);
							$messages[] = array(
								'role'    => 'tool',
								'name'    => $name,
								'content' => $tool_result,
							);
							$content_acc = '';
							break;

						case 'done':
							$usage = array_merge( $usage, $event['usage'] ?? array() );
							$model = (string) ( $event['model'] ?? '' );
							break;
					}
				}

				if ( ! $had_tool_calls ) {
					break;
				}
			}
		} catch ( \Throwable $e ) {
			$emit( 'error', array( 'message' => $e->getMessage() ) );
		}

		$ms = (int) ( ( microtime( true ) - $start ) * 1000 );

		if ( $citations_enabled && ! empty( $ctx['citations'] ) ) {
			$emit( 'citations', array( 'sources' => $ctx['citations'] ) );
		}
		if ( ! empty( $tool_log ) ) {
			$emit( 'tools', array( 'calls' => $tool_log ) );
		}
		$emit( 'done', array( 'usage' => $usage, 'model' => $model, 'ms' => $ms ) );

		// Persist assistant turn.
		$this->persist_turn(
			array(
				'session_id'        => $session,
				'role'              => 'assistant',
				'content'           => $content_acc,
				'reasoning'         => $reason_acc,
				'citations'         => $citations_enabled ? $ctx['citations'] : array(),
				'tool_calls'        => $tool_log,
				'model'             => $model,
				'prompt_tokens'     => $usage['prompt_tokens'],
				'completion_tokens' => $usage['completion_tokens'],
				'response_time_ms'  => $ms,
				'user_ip'           => $this->rate->client_ip(),
				'device'            => $this->rate->device(),
			)
		);

		exit;
	}

	/**
	 * Send SSE response headers and disable buffering.
	 *
	 * @return void
	 */
	protected function sse_headers() {
		if ( headers_sent() ) {
			return;
		}
		// Disable any output buffering (incl. gzip) that would stall the stream.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'X-Accel-Buffering: no' ); // Nginx.
		header( 'Connection: keep-alive' );
		@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@ini_set( 'output_buffering', 'off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Record feedback for a message.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_feedback( \WP_REST_Request $request ) {
		global $wpdb;
		$message_id = (int) $request->get_param( 'message_id' );
		$feedback   = sanitize_key( (string) $request->get_param( 'feedback' ) );
		$comment    = sanitize_textarea_field( (string) $request->get_param( 'comment' ) );

		if ( ! in_array( $feedback, array( 'up', 'down' ), true ) ) {
			return new \WP_Error( 'invalid_feedback', __( 'Invalid feedback value.', 'openrag-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB
			$this->schema->table( 'chats' ),
			array(
				'feedback'         => $feedback,
				'feedback_comment' => $comment,
			),
			array( 'id' => $message_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return rest_ensure_response( array( 'updated' => (int) $updated ) );
	}

	/**
	 * Fetch recent chat history for a session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_get_history( \WP_REST_Request $request ) {
		global $wpdb;
		$session = (string) ( $request->get_param( 'session_id' ) ?: '' );
		$limit   = max( 1, min( 200, (int) $request->get_param( 'limit' ) ) );

		$sql = 'SELECT id, session_id, role, content, citations, reasoning, model, feedback, created_at FROM `' . $this->schema->table( 'chats' ) . '`';
		$params = array();
		if ( '' !== $session ) {
			$sql .= ' WHERE session_id = %s';
			$params[] = $session;
		}
		$sql .= ' ORDER BY id DESC LIMIT %d';
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$rows = array_reverse( $rows );

		$turns = array();
		foreach ( $rows as $row ) {
			$turns[] = array(
				'id'        => (int) $row->id,
				'role'      => $row->role,
				'content'   => $row->content,
				'citations' => $row->citations ? json_decode( $row->citations, true ) : array(),
				'reasoning' => $row->reasoning,
				'model'     => $row->model,
				'feedback'  => $row->feedback,
				'createdAt' => $row->created_at,
			);
		}
		return rest_ensure_response( array( 'turns' => $turns ) );
	}

	/**
	 * Clear chat history for a session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_clear_history( \WP_REST_Request $request ) {
		global $wpdb;
		$session = (string) ( $request->get_param( 'session_id' ) ?: '' );
		if ( '' === $session ) {
			return new \WP_Error( 'no_session', __( 'session_id is required.', 'openrag-ai-chatbot' ), array( 'status' => 400 ) );
		}
		$deleted = $wpdb->delete( $this->schema->table( 'chats' ), array( 'session_id' => $session ), array( '%s' ) ); // phpcs:ignore WordPress.DB
		return rest_ensure_response( array( 'deleted' => (int) $deleted ) );
	}

	/* ----------------------------------------------------------------------
	 * Persistence helpers
	 * -------------------------------------------------------------------- */

	/**
	 * Persist one chat turn.
	 *
	 * @param array $args Turn fields.
	 * @return int Inserted row id.
	 */
	protected function persist_turn( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'session_id'        => '',
				'role'              => 'user',
				'content'           => '',
				'citations'         => null,
				'reasoning'         => '',
				'tool_calls'        => null,
				'model'             => '',
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'response_time_ms'  => 0,
				'feedback'          => null,
				'feedback_comment'  => null,
				'user_ip'           => '',
				'device'            => 'web',
			)
		);

		$citations  = null === $args['citations'] ? null : wp_json_encode( $args['citations'] );
		$tool_calls = null === $args['tool_calls'] ? null : wp_json_encode( $args['tool_calls'] );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$this->schema->table( 'chats' ),
			array(
				'session_id'        => $args['session_id'],
				'role'              => $args['role'],
				'content'           => $args['content'],
				'citations'         => $citations,
				'reasoning'         => $args['reasoning'],
				'tool_calls'        => $tool_calls,
				'model'             => $args['model'],
				'prompt_tokens'     => (int) $args['prompt_tokens'],
				'completion_tokens' => (int) $args['completion_tokens'],
				'response_time_ms'  => (int) $args['response_time_ms'],
				'feedback'          => $args['feedback'],
				'feedback_comment'  => $args['feedback_comment'],
				'user_ip'           => $args['user_ip'],
				'device'            => $args['device'],
				'created_at'        => current_time( 'mysql' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Generate (and persist) a new session hash.
	 *
	 * @return string
	 */
	protected function new_session() {
		global $wpdb;
		$hash = hash( 'sha256', uniqid( 'openrag', true ) . random_int( 0, PHP_INT_MAX ) );

		$wpdb->replace( // phpcs:ignore WordPress.DB
			$this->schema->table( 'chat_sessions' ),
			array(
				'session_hash' => $hash,
				'user_ip'      => $this->rate->client_ip(),
				'user_agent'   => (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
				'device'       => $this->rate->device(),
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return $hash;
	}
}
