<?php
/**
 * Cloudflare Workers AI chat provider.
 *
 * Uses the AI Gateway / AI Run endpoint. Workers AI models return OpenAI-shaped
 * chat completions, so this reuses a slim OpenAI-compatible adapter.
 *
 * Endpoint:
 *   POST https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run/{model}
 *   Body: { messages: [...], stream: true|false }
 *
 * @package WPOpenRag\LLM
 */

namespace WPOpenRag\LLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_LLM implements LLM_Provider {

	public function __construct( protected array $settings ) {
	}

	public function id() {
		return 'cloudflare';
	}

	public function label() {
		return __( 'Cloudflare Workers AI', 'wp-openrag' );
	}

	public function is_configured() {
		return ! empty( $this->settings['cloudflare_account'] )
			&& ! empty( $this->settings['cloudflare_token'] )
			&& ! empty( $this->settings['cloudflare_llm_model'] );
	}

	public function supports_streaming() {
		return true;
	}

	public function supports_tools() {
		// Workers AI supports tools on select models; expose conservative false here.
		return false;
	}

	public function supports_reasoning() {
		return false;
	}

	protected function account() {
		return (string) ( $this->settings['cloudflare_account'] ?? '' );
	}

	protected function token() {
		return (string) ( $this->settings['cloudflare_token'] ?? '' );
	}

	protected function model( $opts = array() ) {
		return (string) ( $opts['model'] ?? $this->settings['cloudflare_llm_model'] ?? '@cf/meta/llama-3.1-8b-instruct' );
	}

	protected function run_url( $model ) {
		return sprintf(
			'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
			$this->account(),
			$model
		);
	}

	protected function headers( $stream = false ) {
		$h = array(
			'Authorization' => 'Bearer ' . $this->token(),
			'Content-Type'  => 'application/json',
		);
		if ( $stream ) {
			$h['Accept'] = 'text/event-stream';
		}
		return $h;
	}

	public function list_models() {
		// CF doesn't expose a per-account model list endpoint; return known defaults.
		return array(
			'@cf/meta/llama-3.3-70b-instruct-fp8-fast',
			'@cf/meta/llama-3.1-8b-instruct',
			'@cf/meta/llama-3.1-70b-instruct',
			'@cf/meta/llama-3-8b-instruct',
			'@cf/qwen/qwen1.5-14b-chat-awq',
			'@hf/nousresearch/hermes-2-pro-mistral-7b',
		);
	}

	public function chat( array $messages, array $opts = array() ) {
		$model  = $this->model( $opts );
		$url    = $this->run_url( $model );
		$payload = array(
			'messages'    => $messages,
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.3,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
		);

		$response = wp_remote_post(
			$url,
			array( 'timeout' => 120, 'headers' => $this->headers(), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Cloudflare LLM request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $json['success'] ) ) {
			$errs = $json['errors'] ?? array();
			$msg  = is_array( $errs ) && ! empty( $errs[0]['message'] ) ? $errs[0]['message'] : wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Cloudflare LLM error (' . $code . '): ' . $msg );
		}

		// CF returns OpenAI-shaped "result" sometimes, plain "response" other times.
		$result = $json['result'] ?? $json;
		$content = '';
		if ( isset( $result['response'] ) ) {
			$content = (string) $result['response'];
		} elseif ( isset( $result['choices'][0]['message']['content'] ) ) {
			$content = (string) $result['choices'][0]['message']['content'];
		}

		return array(
			'content'           => $content,
			'reasoning'         => '',
			'tool_calls'        => array(),
			'prompt_tokens'     => (int) ( $result['usage']['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $result['usage']['completion_tokens'] ?? 0 ),
			'model'             => $model,
		);
	}

	public function stream( array $messages, array $opts = array() ) {
		$model   = $this->model( $opts );
		$url     = $this->run_url( $model );
		$payload = array(
			'messages'    => $messages,
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.3,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			'stream'      => true,
		);

		$response = wp_remote_post(
			$url,
			array( 'timeout' => 300, 'headers' => $this->headers( true ), 'body' => wp_json_encode( $payload ) )
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Cloudflare LLM stream failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'Cloudflare LLM stream error (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
		}

		$body  = wp_remote_retrieve_body( $response );
		$lines = preg_split( '/\r\n|\n|\r/', $body );
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
			if ( ! is_array( $json ) ) {
				continue;
			}
			$delta = $json['choices'][0]['delta'] ?? ( $json['delta'] ?? array() );
			if ( isset( $delta['content'] ) && '' !== $delta['content'] ) {
				yield array( 'type' => 'delta', 'content' => (string) $delta['content'] );
			} elseif ( isset( $json['response'] ) && '' !== $json['response'] ) {
				// CF's plain response stream shape.
				yield array( 'type' => 'delta', 'content' => (string) $json['response'] );
			}
		}

		yield array( 'type' => 'done', 'usage' => array(), 'model' => $model );
	}
}
