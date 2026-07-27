<?php
/**
 * RAG engine — retrieval-augmented generation with optional MCP tools.
 *
 * Responsibilities:
 *   - Embed the query and retrieve relevant chunks from the vector store.
 *   - Build a numbered context block.
 *   - Optionally attach MCP tools and run a tool-call loop.
 *   - Return content + reasoning + citations + usage.
 *
 * @package WPOpenRag\Chat
 */

namespace WPOpenRag\Chat;

use WPOpenRag\Embeddings\Embedding_Manager;
use WPOpenRag\LLM\LLM_Manager;
use WPOpenRag\MCP\MCP_Manager;
use WPOpenRag\Settings;
use WPOpenRag\VectorStores\Vector_Store_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rag_Engine {

	/**
	 * @var Embedding_Manager
	 */
	private $embeddings;

	/**
	 * @var Vector_Store_Manager
	 */
	private $vectors;

	/**
	 * @var LLM_Manager
	 */
	private $llm;

	/**
	 * @var MCP_Manager
	 */
	private $mcp;

	public function __construct(
		Embedding_Manager $embeddings,
		Vector_Store_Manager $vectors,
		LLM_Manager $llm,
		MCP_Manager $mcp
	) {
		$this->embeddings = $embeddings;
		$this->vectors    = $vectors;
		$this->llm        = $llm;
		$this->mcp        = $mcp;
	}

	/**
	 * Retrieve relevant chunks for a query.
	 *
	 * @param string $query User query.
	 * @param int    $top_k Result count.
	 * @param float  $score Minimum similarity.
	 * @return array<int,array{chunk_id:int, content:string, source_url:string, source_title:string, score:float}>
	 */
	public function retrieve( $query, $top_k = null, $score = null ) {
		$settings = Settings::group( 'chat' );
		$top_k    = $top_k ?? (int) ( $settings['top_k'] ?? 5 );
		$score    = null !== $score ? (float) $score : (float) ( $settings['min_similarity'] ?? 0.35 );

		$query_vec = $this->embed_query( $query );
		if ( empty( $query_vec ) ) {
			return array();
		}
		return $this->vectors->store()->query( $query_vec, $top_k, $score );
	}

	/**
	 * Embed a query string with caching.
	 *
	 * @param string $query Query.
	 * @return array<int,float>
	 */
	public function embed_query( $query ) {
		$key = 'wporag_q_' . md5( $query );
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		try {
			$vec = $this->embeddings->embed_one( $query );
		} catch ( \Throwable $e ) {
			$vec = array();
		}
		if ( ! empty( $vec ) ) {
			set_transient( $key, $vec, HOUR_IN_SECONDS );
		}
		return $vec;
	}

	/**
	 * Build a numbered context string and a citations list from retrieval hits.
	 *
	 * @param array $hits Retrieval results.
	 * @return array{context:string, citations:array<int,array{title:string, url:string}>}
	 */
	public function build_context( array $hits ) {
		$context   = '';
		$citations = array();
		$seen      = array();

		foreach ( $hits as $i => $hit ) {
			$idx = $i + 1;
			$content = trim( (string) ( $hit['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}
			$context .= sprintf( "[%d] %s\n\n", $idx, $content );

			$url   = (string) ( $hit['source_url'] ?? '' );
			$title = (string) ( $hit['source_title'] ?? '' );
			// Deduplicate citations by URL.
			$ckey = $url ?: $title;
			if ( isset( $seen[ $ckey ] ) ) {
				continue;
			}
			$seen[ $ckey ] = true;
			$citations[]   = array(
				'title' => '' !== $title ? $title : ( $url ? wp_basename( $url ) : __( 'Source', 'wp-openrag' ) ),
				'url'   => $url,
			);
		}

		return array(
			'context'   => trim( $context ),
			'citations' => $citations,
		);
	}

	/**
	 * Build the message list for the LLM, including system prompt + context.
	 *
	 * @param string $query      User query.
	 * @param string $context    Numbered context.
	 * @param array  $history    Prior turns (role/content).
	 * @param bool   $citations  Whether to instruct citation formatting.
	 * @return array<int,array{role:string, content:string}>
	 */
	public function build_messages( $query, $context, array $history, $citations ) {
		$settings = Settings::group( 'chat' );
		$system   = (string) ( $settings['system_prompt'] ?? '' );
		if ( '' !== $context ) {
			$citation_instruction = $citations
				? __( 'Cite sources using bracketed numbers like [1], [2] that correspond to the context blocks above.', 'wp-openrag' )
				: '';
			$system .= "\n\n" . __( 'Use the following knowledge base context when relevant:', 'wp-openrag' )
				. "\n\n" . $context
				. ( '' !== $citation_instruction ? "\n\n" . $citation_instruction : '' );
		}

		$messages   = array();
		$messages[] = array( 'role' => 'system', 'content' => $system );

		// Trim history to configured turns.
		$turns = max( 0, (int) ( $settings['history_turns'] ?? 6 ) );
		if ( $turns > 0 && ! empty( $history ) ) {
			$history = array_slice( $history, -$turns );
			foreach ( $history as $turn ) {
				if ( ! isset( $turn['role'], $turn['content'] ) ) {
					continue;
				}
				$messages[] = array(
					'role'    => in_array( $turn['role'], array( 'user', 'assistant' ), true ) ? $turn['role'] : 'user',
					'content' => (string) $turn['content'],
				);
			}
		}

		$messages[] = array( 'role' => 'user', 'content' => (string) $query );
		return $messages;
	}

	/**
	 * Collect MCP tools from enabled servers (if MCP is on).
	 *
	 * @return array
	 */
	public function collect_tools() {
		$mcp_settings = Settings::group( 'mcp' );
		if ( empty( $mcp_settings['enabled'] ) ) {
			return array();
		}
		try {
			return $this->mcp->collect_tools();
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Execute a tool call via the MCP manager.
	 *
	 * @param array $tool_call Normalized tool call {function:{name, arguments}}.
	 * @return string Tool result text.
	 */
	public function execute_tool( array $tool_call ) {
		$name = (string) ( $tool_call['function']['name'] ?? '' );
		$args = (string) ( $tool_call['function']['arguments'] ?? '{}' );
		try {
			$result = $this->mcp->call_tool( $name, $args );
			return is_string( $result ) ? $result : wp_json_encode( $result );
		} catch ( \Throwable $e ) {
			return '[Tool error] ' . $e->getMessage();
		}
	}

	/**
	 * Non-streaming answer (used by /chat/sync).
	 *
	 * @param string $query   User query.
	 * @param array  $history Prior turns.
	 * @return array{content:string, reasoning:string, citations:array, tool_calls:array, usage:array, model:string}
	 */
	public function answer( $query, array $history = array() ) {
		$settings  = Settings::group( 'chat' );
		$citations_enabled = ! empty( $settings['citations'] );
		$reasoning_enabled = ! empty( $settings['reasoning'] );

		$hits    = $this->retrieve( $query );
		$ctx     = $this->build_context( $hits );
		$messages= $this->build_messages( $query, $ctx['context'], $history, $citations_enabled );

		$opts = $this->llm_opts( $reasoning_enabled );

		$result = $this->llm->chat( $messages, $opts );

		// Tool-call loop (non-streaming).
		$iterations = 0;
		$tool_log   = array();
		while ( ! empty( $result['tool_calls'] ) && $iterations < 5 ) {
			$iterations++;
			$messages[] = array(
				'role'       => 'assistant',
				'content'    => $result['content'] ?: '',
				'tool_calls' => $result['tool_calls'],
			);
			foreach ( $result['tool_calls'] as $tc ) {
				$tool_result = $this->execute_tool( $tc );
				$tool_log[]  = array(
					'name'   => $tc['function']['name'] ?? '',
					'args'   => $tc['function']['arguments'] ?? '{}',
					'result' => $tool_result,
				);
				$messages[] = array(
					'role'    => 'tool',
					'name'    => $tc['function']['name'] ?? '',
					'content' => $tool_result,
				);
			}
			$result = $this->llm->chat( $messages, $opts );
		}

		return array(
			'content'   => (string) $result['content'],
			'reasoning' => (string) ( $result['reasoning'] ?? '' ),
			'citations' => $citations_enabled ? $ctx['citations'] : array(),
			'tool_calls'=> $tool_log,
			'usage'     => array(
				'prompt_tokens'     => (int) ( $result['prompt_tokens'] ?? 0 ),
				'completion_tokens' => (int) ( $result['completion_tokens'] ?? 0 ),
			),
			'model'     => (string) ( $result['model'] ?? '' ),
		);
	}

	/**
	 * Build LLM options from chat settings + tool availability.
	 *
	 * @param bool $reasoning Whether reasoning is enabled.
	 * @return array
	 */
	public function llm_opts( $reasoning ) {
		$settings = Settings::group( 'chat' );
		$opts = array(
			'temperature' => (float) ( $settings['temperature'] ?? 0.3 ),
			'max_tokens'  => (int) ( $settings['max_tokens'] ?? 800 ),
		);
		if ( $reasoning ) {
			$opts['reasoning']        = true;
			$opts['reasoning_effort'] = (string) ( $settings['reasoning_effort'] ?? 'medium' );
		}
		return $opts;
	}

	/**
	 * Stream a chat response, yielding normalized events.
	 *
	 * @param array  $messages    Prepared messages.
	 * @param array  $opts        LLM options.
	 * @return \Generator
	 */
	public function stream( array $messages, array $opts ) {
		return $this->llm->stream( $messages, $opts );
	}
}
