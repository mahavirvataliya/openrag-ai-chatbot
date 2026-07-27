<?php
/**
 * OpenAI / OpenAI-compatible embedding provider.
 *
 * Works against any endpoint that mirrors OpenAI's /v1/embeddings shape.
 *
 * @package WPOpenRag\Embeddings
 */

namespace WPOpenRag\Embeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAI_Embedding implements Embedding_Provider {

	/**
	 * Constructor.
	 *
	 * @param array $settings Embedding settings group.
	 */
	public function __construct( protected array $settings ) {
	}

	public function id() {
		return 'openai';
	}

	public function label() {
		return __( 'OpenAI', 'wp-openrag' );
	}

	public function is_configured() {
		return ! empty( $this->settings['openai_api_key'] ) && ! empty( $this->settings['openai_model'] );
	}

	protected function base_url() {
		$url = ! empty( $this->settings['openai_base_url'] ) ? $this->settings['openai_base_url'] : 'https://api.openai.com/v1';
		return rtrim( (string) $url, '/' );
	}

	protected function model() {
		return (string) ( $this->settings['openai_model'] ?? 'text-embedding-3-small' );
	}

	public function dimensions() {
		// Known defaults; otherwise 0 means caller auto-detects.
		$known = array(
			'text-embedding-3-small'  => 1536,
			'text-embedding-3-large'  => 3072,
			'text-embedding-ada-002'  => 1536,
		);
		$m = $this->model();
		return $known[ $m ] ?? (int) ( $this->settings['dimensions'] ?? 0 );
	}

	public function embed( $inputs ) {
		$single = ! is_array( $inputs );
		$list   = $single ? array( $inputs ) : $inputs;

		$body = array(
			'model' => $this->model(),
			'input' => array_map( 'strval', $list ),
		);

		$resp = $this->request( '/embeddings', $body );
		$vectors = array();
		if ( isset( $resp['data'] ) && is_array( $resp['data'] ) ) {
			foreach ( $resp['data'] as $item ) {
				$vectors[] = array_map( 'floatval', (array) ( $item['embedding'] ?? array() ) );
			}
		}
		return $single ? array( $vectors[0] ?? array() ) : $vectors;
	}

	/**
	 * Perform an authenticated POST.
	 *
	 * @param string $path Path under base URL.
	 * @param array  $body Payload.
	 * @return array Decoded JSON.
	 * @throws \RuntimeException On transport or API error.
	 */
	protected function request( $path, $body ) {
		$url  = $this->base_url() . $path;
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->settings['openai_api_key'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'OpenAI embedding request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$err = $json['error']['message'] ?? wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'OpenAI embedding API error (' . $code . '): ' . $err );
		}
		return is_array( $json ) ? $json : array();
	}
}
