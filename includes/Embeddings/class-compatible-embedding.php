<?php
/**
 * Generic OpenAI-compatible embedding provider (LM Studio, Together, vLLM, etc.).
 *
 * @package OpenRag\Embeddings
 */

namespace OpenRag\Embeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Compatible_Embedding extends OpenAI_Embedding {

	public function id() {
		return 'openai-compatible';
	}

	public function label() {
		return __( 'OpenAI-compatible', 'openrag-ai-chatbot' );
	}

	public function is_configured() {
		return ! empty( $this->settings['compatible_base_url'] ) && ! empty( $this->settings['compatible_model'] );
	}

	protected function base_url() {
		return rtrim( (string) ( $this->settings['compatible_base_url'] ?? '' ), '/' );
	}

	protected function model() {
		return (string) ( $this->settings['compatible_model'] ?? '' );
	}

	public function dimensions() {
		$d = (int) ( $this->settings['dimensions'] ?? 0 );
		return $d;
	}

	protected function request( $path, $body ) {
		$url  = $this->base_url() . $path;
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		);
		// Authorization header is optional for self-hosted providers.
		if ( ! empty( $this->settings['compatible_api_key'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->settings['compatible_api_key'];
		}

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Embedding request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error']['message'] ?? wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Embedding API error (' . $code . '): ' . $err );
		}
		return is_array( $json ) ? $json : array();
	}
}
