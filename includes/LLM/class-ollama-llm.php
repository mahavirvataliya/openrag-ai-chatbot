<?php
/**
 * Ollama local chat provider.
 *
 * Endpoint: POST {base_url}/api/chat  Body: { model, messages, stream, tools }
 *
 * @package OpenRag\LLM
 */

namespace OpenRag\LLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollama_LLM implements LLM_Provider {

	public function __construct( protected array $settings ) {
	}

	public function id() {
		return 'ollama';
	}

	public function label() {
		return __( 'Ollama (local)', 'openrag-ai-chatbot' );
	}

	public function is_configured() {
		return ! empty( $this->settings['ollama_base_url'] ) && ! empty( $this->settings['ollama_model'] );
	}

	public function supports_streaming() {
		return true;
	}

	public function supports_tools() {
		return true;
	}

	public function supports_reasoning() {
		return true; // some models (deepseek-r1) return <think> tags.
	}

	protected function base_url() {
		return rtrim( (string) ( $this->settings['ollama_base_url'] ?? 'http://localhost:11434' ), '/' );
	}

	protected function model( $opts = array() ) {
		return (string) ( $opts['model'] ?? $this->settings['ollama_model'] ?? 'llama3.1' );
	}

	public function list_models() {
		$response = wp_remote_get(
			$this->base_url() . '/api/tags',
			array( 'timeout' => 30 )
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
		foreach ( ( $json['models'] ?? array() ) as $m ) {
			if ( ! empty( $m['name'] ) ) {
				$ids[] = (string) $m['name'];
			}
		}
		return $ids;
	}

	protected function build_payload( array $messages, array $opts, $stream ) {
		$payload = array(
			'model'       => $this->model( $opts ),
			'messages'    => $messages,
			'stream'      => $stream,
			'options'     => array(
				'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.3,
				'num_predict' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			),
		);
		if ( ! empty( $opts['tools'] ) ) {
			$payload['tools'] = $this->convert_tools( $opts['tools'] );
		}
		return $payload;
	}

	protected function convert_tools( array $tools ) {
		$out = array();
		foreach ( $tools as $t ) {
			$out[] = array(
				'type'       => 'function',
				'function'   => array(
					'name'        => $t['function']['name'] ?? $t['name'] ?? '',
					'description' => $t['function']['description'] ?? $t['description'] ?? '',
					'parameters'  => $t['function']['parameters'] ?? $t['parameters'] ?? (object) array(),
				),
			);
		}
		return $out;
	}

	public function chat( array $messages, array $opts = array() ) {
		$payload  = $this->build_payload( $messages, $opts, false );
		$response = wp_remote_post(
			$this->base_url() . '/api/chat',
			array( 'timeout' => 120, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Ollama request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error'] ?? wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Ollama API error (' . $code . '): ' . $err );
		}

		$msg        = $json['message'] ?? array();
		$content    = (string) ( $msg['content'] ?? '' );
		$reasoning  = '';
		$tool_calls = isset( $msg['tool_calls'] ) && is_array( $msg['tool_calls'] ) ? $msg['tool_calls'] : array();

		// Extract <think>...</think> blocks (deepseek-r1 / qwq models).
		if ( preg_match_all( '/<think>(.*?)<\/think>/s', $content, $m ) ) {
			$reasoning = implode( "\n", $m[1] );
			$content   = trim( preg_replace( '/<think>.*?<\/think>/s', '', $content ) );
		}

		return array(
			'content'           => $content,
			'reasoning'         => $reasoning,
			'tool_calls'        => array_map(
				function ( $tc ) {
					return array(
						'id'       => $tc['id'] ?? '',
						'type'     => 'function',
						'function' => array(
							'name'      => $tc['function']['name'] ?? '',
							'arguments' => isset( $tc['function']['arguments'] ) ? ( is_string( $tc['function']['arguments'] ) ? $tc['function']['arguments'] : wp_json_encode( $tc['function']['arguments'] ) ) : '{}',
						),
					);
				},
				$tool_calls
			),
			'prompt_tokens'     => (int) ( $json['prompt_eval_count'] ?? 0 ),
			'completion_tokens' => (int) ( $json['eval_count'] ?? 0 ),
			'model'             => (string) ( $json['model'] ?? $payload['model'] ),
		);
	}

	public function stream( array $messages, array $opts = array() ) {
		$payload  = $this->build_payload( $messages, $opts, true );
		$response = wp_remote_post(
			$this->base_url() . '/api/chat',
			array( 'timeout' => 300, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Ollama stream failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'Ollama stream error (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
		}

		$body  = wp_remote_retrieve_body( $response );
		$lines = preg_split( '/\r\n|\n|\r/', $body );
		$usage = array( 'prompt_tokens' => 0, 'completion_tokens' => 0 );
		$pending_tool = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$json = json_decode( $line, true );
			if ( ! is_array( $json ) ) {
				continue;
			}

			if ( isset( $json['message']['tool_calls'] ) ) {
				foreach ( $json['message']['tool_calls'] as $tc ) {
					$raw_args = $tc['function']['arguments'] ?? '{}';
					$args_str = is_string( $raw_args ) ? $raw_args : wp_json_encode( $raw_args );
					yield array(
						'type'      => 'tool_call',
						'tool_call' => array(
							'id'       => $tc['id'] ?? '',
							'type'     => 'function',
							'function' => array(
								'name'      => $tc['function']['name'] ?? '',
								'arguments' => $args_str,
							),
						),
					);
				}
			}

			$chunk = (string) ( $json['message']['content'] ?? '' );
			if ( '' !== $chunk ) {
				yield array( 'type' => 'delta', 'content' => $chunk );
			}

			if ( isset( $json['prompt_eval_count'] ) ) {
				$usage['prompt_tokens'] = (int) $json['prompt_eval_count'];
			}
			if ( isset( $json['eval_count'] ) ) {
				$usage['completion_tokens'] = (int) $json['eval_count'];
			}
			if ( ! empty( $json['done'] ) ) {
				break;
			}
		}

		yield array( 'type' => 'done', 'usage' => $usage, 'model' => $payload['model'] );
	}
}
