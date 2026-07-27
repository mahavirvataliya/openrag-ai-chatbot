<?php
/**
 * Anthropic Claude chat provider.
 *
 * Uses /v1/messages. Supports extended thinking (reasoning) and tool calls.
 *
 * @package WPOpenRag\LLM
 */

namespace WPOpenRag\LLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anthropic_LLM implements LLM_Provider {

	public function __construct( protected array $settings ) {
	}

	public function id() {
		return 'anthropic';
	}

	public function label() {
		return __( 'Anthropic Claude', 'wp-openrag' );
	}

	public function is_configured() {
		return ! empty( $this->settings['anthropic_api_key'] ) && ! empty( $this->settings['anthropic_model'] );
	}

	public function supports_streaming() {
		return true;
	}

	public function supports_tools() {
		return true;
	}

	public function supports_reasoning() {
		return true;
	}

	protected function base_url() {
		return rtrim( (string) ( $this->settings['anthropic_base_url'] ?? 'https://api.anthropic.com/v1' ), '/' );
	}

	protected function model( $opts = array() ) {
		return (string) ( $opts['model'] ?? $this->settings['anthropic_model'] ?? '' );
	}

	protected function headers( $stream = false ) {
		$h = array(
			'Content-Type'    => 'application/json',
			'x-api-key'       => (string) ( $this->settings['anthropic_api_key'] ?? '' ),
			'anthropic-version'=> '2023-06-01',
		);
		if ( $stream ) {
			$h['Accept'] = 'text/event-stream';
		}
		return $h;
	}

	public function list_models() {
		$response = wp_remote_get(
			$this->base_url() . '/models',
			array( 'timeout' => 30, 'headers' => $this->headers() )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'List models failed (' . $code . ')' );
		}
		$ids = array();
		foreach ( ( $json['data'] ?? array() ) as $m ) {
			if ( ! empty( $m['id'] ) ) {
				$ids[] = (string) $m['id'];
			}
		}
		return $ids;
	}

	/**
	 * Convert OpenAI-style messages into Anthropic's request shape.
	 *
	 * @param array $messages Normalized messages.
	 * @return array{0: string, 1: array}
	 *     [system_prompt, anthropic_messages]
	 */
	protected function convert_messages( array $messages ) {
		$system_parts = array();
		$anth         = array();
		$role_map     = array( 'assistant' => 'assistant', 'user' => 'user', 'system' => 'system', 'tool' => 'user' );

		foreach ( $messages as $m ) {
			$role = $role_map[ $m['role'] ?? 'user' ] ?? 'user';
			if ( 'system' === $role ) {
				$system_parts[] = (string) ( $m['content'] ?? '' );
				continue;
			}
			$content = array(
				array(
					'type' => 'text',
					'text' => (string) ( $m['content'] ?? '' ),
				),
			);
			// Tool result passthrough as user message text.
			if ( 'tool' === ( $m['role'] ?? '' ) ) {
				$content[0]['text'] = '[Tool result] ' . $content[0]['text'];
				$role               = 'user';
			}
			// Merge consecutive same-role messages (Anthropic requires alternation).
			if ( ! empty( $anth ) && $anth[ count( $anth ) - 1 ]['role'] === $role ) {
				$anth[ count( $anth ) - 1 ]['content'] = array_merge( $anth[ count( $anth ) - 1 ]['content'], $content );
			} else {
				$anth[] = array( 'role' => $role, 'content' => $content );
			}
		}

		return array( implode( "\n\n", $system_parts ), $anth );
	}

	protected function build_payload( array $messages, array $opts, $stream ) {
		list( $system, $anth ) = $this->convert_messages( $messages );

		$payload = array(
			'model'      => $this->model( $opts ),
			'max_tokens' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 1024,
			'temperature'=> isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.3,
			'messages'   => $anth,
		);
		if ( '' !== $system ) {
			$payload['system'] = $system;
		}
		if ( ! empty( $opts['tools'] ) ) {
			$payload['tools'] = $this->convert_tools( $opts['tools'] );
		}

		// Extended thinking (reasoning). Only enable on Claude 3.5+ models that support it.
		if ( ! empty( $opts['reasoning'] ) && $this->supports_reasoning() ) {
			$budget = isset( $opts['reasoning_effort'] ) ? $opts['reasoning_effort'] : 'medium';
			$map    = array( 'low' => 1024, 'medium' => 4096, 'high' => 10000, 'max' => 20000 );
			$tokens = $map[ $budget ] ?? 4096;
			$payload['thinking'] = array(
				'type'          => 'enabled',
				'budget_tokens' => $tokens,
			);
			// Anthropic requires temperature=1 when thinking is enabled.
			$payload['temperature'] = 1;
		}

		if ( $stream ) {
			$payload['stream'] = true;
		}
		return $payload;
	}

	protected function convert_tools( array $tools ) {
		$out = array();
		foreach ( $tools as $t ) {
			$out[] = array(
				'name'        => $t['function']['name'] ?? $t['name'] ?? '',
				'description' => $t['function']['description'] ?? $t['description'] ?? '',
				'input_schema'=> $t['function']['parameters'] ?? $t['parameters'] ?? (object) array(),
			);
		}
		return $out;
	}

	public function chat( array $messages, array $opts = array() ) {
		$payload  = $this->build_payload( $messages, $opts, false );
		$response = wp_remote_post(
			$this->base_url() . '/messages',
			array( 'timeout' => 120, 'headers' => $this->headers(), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Anthropic request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error']['message'] ?? wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Anthropic API error (' . $code . '): ' . $err );
		}

		$content   = '';
		$reasoning = '';
		$tools     = array();
		foreach ( ( $json['content'] ?? array() ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$content .= (string) ( $block['text'] ?? '' );
			} elseif ( 'thinking' === ( $block['type'] ?? '' ) ) {
				$reasoning .= (string) ( $block['thinking'] ?? '' );
			} elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
				$tools[] = array(
					'id'       => $block['id'] ?? '',
					'type'     => 'function',
					'function' => array(
						'name'      => $block['name'] ?? '',
						'arguments' => wp_json_encode( $block['input'] ?? new \stdClass() ),
					),
				);
			}
		}

		return array(
			'content'           => $content,
			'reasoning'         => $reasoning,
			'tool_calls'        => $tools,
			'prompt_tokens'     => (int) ( $json['usage']['input_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $json['usage']['output_tokens'] ?? 0 ),
			'model'             => (string) ( $json['model'] ?? $payload['model'] ),
		);
	}

	public function stream( array $messages, array $opts = array() ) {
		$payload  = $this->build_payload( $messages, $opts, true );
		$response = wp_remote_post(
			$this->base_url() . '/messages',
			array( 'timeout' => 300, 'headers' => $this->headers( true ), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Anthropic stream failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'Anthropic stream error (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
		}

		yield from $this->parse_sse( wp_remote_retrieve_body( $response ), $payload['model'] );
	}

	protected function parse_sse( $body, $model ) {
		$lines         = preg_split( '/\r\n|\n|\r/', $body );
		$usage         = array( 'prompt_tokens' => 0, 'completion_tokens' => 0 );
		$current_tool  = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
				continue;
			}
			$data = trim( substr( $line, 5 ) );
			$json = json_decode( $data, true );
			if ( ! is_array( $json ) ) {
				continue;
			}

			$type = $json['type'] ?? '';

			switch ( $type ) {
				case 'content_block_start':
					$block = $json['content_block'] ?? array();
					if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
						$current_tool = array(
							'id'       => $block['id'] ?? '',
							'type'     => 'function',
							'function' => array(
								'name'      => $block['name'] ?? '',
								'arguments' => '',
							),
						);
					}
					break;

				case 'content_block_delta':
					$delta = $json['delta'] ?? array();
					if ( 'text_delta' === ( $delta['type'] ?? '' ) ) {
						yield array( 'type' => 'delta', 'content' => (string) ( $delta['text'] ?? '' ) );
					} elseif ( 'thinking_delta' === ( $delta['type'] ?? '' ) ) {
						yield array( 'type' => 'reasoning', 'content' => (string) ( $delta['thinking'] ?? '' ) );
					} elseif ( 'input_json_delta' === ( $delta['type'] ?? '' ) && $current_tool ) {
						$current_tool['function']['arguments'] .= (string) ( $delta['partial_json'] ?? '' );
					}
					break;

				case 'content_block_stop':
					if ( $current_tool ) {
						yield array( 'type' => 'tool_call', 'tool_call' => $current_tool );
						$current_tool = null;
					}
					break;

				case 'message_delta':
					if ( isset( $json['usage']['output_tokens'] ) ) {
						$usage['completion_tokens'] = (int) $json['usage']['output_tokens'];
					}
					break;

				case 'message_start':
					if ( isset( $json['message']['usage']['input_tokens'] ) ) {
						$usage['prompt_tokens'] = (int) $json['message']['usage']['input_tokens'];
					}
					break;
			}
		}

		yield array( 'type' => 'done', 'usage' => $usage, 'model' => $model );
	}
}
