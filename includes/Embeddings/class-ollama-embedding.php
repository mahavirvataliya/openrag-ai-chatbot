<?php
/**
 * Ollama local embedding provider.
 *
 * Endpoint: POST {base_url}/api/embed  Body: { "model": "...", "input": [...] }
 *
 * @package OpenRag\Embeddings
 */

namespace OpenRag\Embeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollama_Embedding implements Embedding_Provider {

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

	protected function base_url() {
		return rtrim( (string) ( $this->settings['ollama_base_url'] ?? 'http://localhost:11434' ), '/' );
	}

	protected function model() {
		return (string) ( $this->settings['ollama_model'] ?? 'nomic-embed-text' );
	}

	public function dimensions() {
		$known = array(
			'nomic-embed-text'  => 768,
			'mxbai-embed-large' => 1024,
			'all-minilm'        => 384,
			'bge-m3'            => 1024,
		);
		$m = $this->model();
		return $known[ $m ] ?? (int) ( $this->settings['dimensions'] ?? 0 );
	}

	public function embed( $inputs ) {
		$single = ! is_array( $inputs );
		$list   = $single ? array( $inputs ) : $inputs;

		$url  = $this->base_url() . '/api/embed';
		$args = array(
			'timeout' => 60,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'model' => $this->model(),
					'input' => array_map( 'strval', $list ),
				)
			),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Ollama embedding request failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error'] ?? wp_remote_retrieve_body( $response );
            throw new \RuntimeException( 'Ollama embedding API error (' . $code . '): ' . $err ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$vectors = array();
		if ( isset( $json['embeddings'] ) && is_array( $json['embeddings'] ) ) {
			foreach ( $json['embeddings'] as $vec ) {
				$vectors[] = array_map( 'floatval', (array) $vec );
			}
		}

		return $single ? array( $vectors[0] ?? array() ) : $vectors;
	}
}
