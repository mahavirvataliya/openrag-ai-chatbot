<?php
/**
 * OpenAI / OpenAI-compatible / Groq chat completions client.
 *
 * All three share the same wire format; only base URL and credentials differ.
 *
 * @package OpenRag\LLM
 */

namespace OpenRag\LLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAI_LLM implements LLM_Provider {

	/**
	 * Provider id (openai, groq, openai-compatible).
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Provider label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $id       Provider key.
	 * @param string $label    Display label.
	 * @param array  $settings Provider settings (api_key, base_url, model).
	 */
	public function __construct( $id, $label, protected array $settings ) {
		$this->id    = $id;
		$this->label = $label;
	}

	public function id() {
		return $this->id;
	}

	public function label() {
		return $this->label;
	}

	public function supports_streaming() {
		return true;
	}

	public function supports_tools() {
		return true;
	}

	public function supports_reasoning() {
		return in_array( $this->id, array( 'openai', 'openai-compatible' ), true );
	}

	public function is_configured() {
		return ! empty( $this->settings['base_url'] ) && ! empty( $this->settings['model'] )
			&& ( 'openai-compatible' === $this->id || ! empty( $this->settings['api_key'] ) );
	}

	protected function base_url() {
		return rtrim( (string) ( $this->settings['base_url'] ?? '' ), '/' );
	}

	protected function model( $opts = array() ) {
		return (string) ( $opts['model'] ?? $this->settings['model'] ?? '' );
	}

	public function list_models() {
		$args = array(
			'timeout' => 30,
			'headers' => $this->auth_headers(),
		);
		$response = wp_remote_get( $this->base_url() . '/models', $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'List models failed (' . $code . ')' );
		}
		$ids = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			foreach ( $json['data'] as $m ) {
				if ( ! empty( $m['id'] ) ) {
					$ids[] = (string) $m['id'];
				}
			}
		}
		return $ids;
	}

	protected function auth_headers() {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $this->settings['api_key'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->settings['api_key'];
		}
		return $headers;
	}

	/**
	 * Build the chat completions payload (shared by chat + stream).
	 *
	 * @param array $messages Messages.
	 * @param array $opts     Options.
	 * @param bool  $stream   Whether streaming.
	 * @return array
	 */
	protected function build_payload( array $messages, array $opts, $stream ) {
		$payload = array(
			'model'       => $this->model( $opts ),
			'messages'    => $messages,
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.3,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			'stream'      => $stream,
		);

		if ( ! empty( $opts['tools'] ) ) {
			$payload['tools'] = $opts['tools'];
		}
		if ( isset( $opts['reasoning_effort'] ) && $this->supports_reasoning() ) {
			$payload['reasoning_effort'] = $opts['reasoning_effort'];
		}
		return $payload;
	}

	public function chat( array $messages, array $opts = array() ) {
		$payload  = $this->build_payload( $messages, $opts, false );
		$response = wp_remote_post(
			$this->base_url() . '/chat/completions',
			array(
				'timeout' => 120,
				'headers' => $this->auth_headers(),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'LLM request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error']['message'] ?? wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'LLM API error (' . $code . '): ' . $err );
		}

		$choice = $json['choices'][0] ?? array();
		$msg    = $choice['message'] ?? array();

		return array(
			'content'            => (string) ( $msg['content'] ?? '' ),
			'reasoning'          => (string) ( $msg['reasoning_content'] ?? $msg['reasoning'] ?? '' ),
			'tool_calls'         => isset( $msg['tool_calls'] ) && is_array( $msg['tool_calls'] ) ? $msg['tool_calls'] : array(),
			'prompt_tokens'      => (int) ( $json['usage']['prompt_tokens'] ?? 0 ),
			'completion_tokens'  => (int) ( $json['usage']['completion_tokens'] ?? 0 ),
			'model'              => (string) ( $json['model'] ?? $payload['model'] ),
		);
	}

	public function stream( array $messages, array $opts = array() ) {
		$payload = $this->build_payload( $messages, $opts, true );

		$response = wp_remote_post(
			$this->base_url() . '/chat/completions',
			array(
				'timeout' => 300,
				'headers' => $this->auth_headers(),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'LLM stream failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'LLM stream error (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
		}

		$body = wp_remote_retrieve_body( $response );
		yield from $this->parse_sse( $body, $payload['model'] );
	}

	/**
	 * Parse an SSE buffer into normalized events.
	 *
	 * @param string $body  Raw response body.
	 * @param string $model Fallback model name.
	 * @return \Generator
	 */
	protected function parse_sse( $body, $model ) {
		$lines           = preg_split( '/\r\n|\n|\r/', $body );
		$usage           = array( 'prompt_tokens' => 0, 'completion_tokens' => 0 );
		$pending_tools   = array();
		$tool_index_map  = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
				continue;
			}
			$data = trim( substr( $line, 5 ) );
			if ( '[DONE]' === $data ) {
				break;
			}
			$json = json_decode( $data, true );
			if ( ! is_array( $json ) || empty( $json['choices'] ) ) {
				continue;
			}
			$choice = $json['choices'][0];
			$delta  = $choice['delta'] ?? array();

			// Reasoning content (DeepSeek / OpenAI reasoning models).
			if ( isset( $delta['reasoning_content'] ) && '' !== $delta['reasoning_content'] ) {
				yield array( 'type' => 'reasoning', 'content' => (string) $delta['reasoning_content'] );
			}

			if ( isset( $delta['content'] ) && '' !== $delta['content'] ) {
				yield array( 'type' => 'delta', 'content' => (string) $delta['content'] );
			}

			// Streaming tool calls (accumulate by index).
			if ( ! empty( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
				foreach ( $delta['tool_calls'] as $tc ) {
					$idx = $tc['index'] ?? 0;
					if ( ! isset( $pending_tools[ $idx ] ) ) {
						$pending_tools[ $idx ] = array(
							'id'        => $tc['id'] ?? '',
							'type'      => 'function',
							'function'  => array(
								'name'      => '',
								'arguments' => '',
							),
						);
						$tool_index_map[ $idx ] = true;
					}
					if ( ! empty( $tc['id'] ) ) {
						$pending_tools[ $idx ]['id'] = $tc['id'];
					}
					if ( isset( $tc['function']['name'] ) ) {
						$pending_tools[ $idx ]['function']['name'] .= $tc['function']['name'];
					}
					if ( isset( $tc['function']['arguments'] ) ) {
						$pending_tools[ $idx ]['function']['arguments'] .= $tc['function']['arguments'];
					}
				}
			}

			if ( isset( $json['usage'] ) && is_array( $json['usage'] ) ) {
				$usage = array_merge( $usage, $json['usage'] );
			}
		}

		foreach ( $pending_tools as $tc ) {
			yield array( 'type' => 'tool_call', 'tool_call' => $tc );
		}

		yield array(
			'type'  => 'done',
			'usage' => $usage,
			'model' => $model,
		);
	}
}
