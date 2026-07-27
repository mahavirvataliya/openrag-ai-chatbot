<?php
/**
 * Cloudflare Workers AI embedding provider.
 *
 * Endpoint:
 *   POST https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run/{model}
 *   Body: { "text": "..." } (single) or { "text": ["...","..."] } (batch)
 *
 * @package WPOpenRag\Embeddings
 */

namespace WPOpenRag\Embeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_Embedding implements Embedding_Provider {

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
			&& ! empty( $this->settings['cloudflare_model'] );
	}

	protected function account() {
		return (string) ( $this->settings['cloudflare_account'] ?? '' );
	}

	protected function model() {
		return (string) ( $this->settings['cloudflare_model'] ?? '@cf/baai/bge-base-en-v1.5' );
	}

	public function dimensions() {
		$known = array(
			'@cf/baai/bge-base-en-v1.5'   => 768,
			'@cf/baai/bge-small-en-v1.5'  => 384,
			'@cf/baai/bge-large-en-v1.5'  => 1024,
			'@cf/baai/bge-m3'             => 1024,
		);
		$m = $this->model();
		return $known[ $m ] ?? (int) ( $this->settings['dimensions'] ?? 0 );
	}

	public function embed( $inputs ) {
		$single = ! is_array( $inputs );
		$list   = $single ? array( $inputs ) : $inputs;

		$url = sprintf(
			'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
			$this->account(),
			$this->model()
		);

		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->settings['cloudflare_token'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'text' => array_map( 'strval', $list ) ) ),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Cloudflare embedding request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $json['success'] ) ) {
			$errs = $json['errors'] ?? array();
			$msg  = is_array( $errs ) && ! empty( $errs[0]['message'] ) ? $errs[0]['message'] : wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Cloudflare embedding API error (' . $code . '): ' . $msg );
		}

		$vectors = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			// CF returns either ["shape":[d],"data":[...]] for single input,
			// or list of vectors for batch. Normalize both.
			$first = reset( $json['data'] );
			if ( is_array( $first ) ) {
				foreach ( $json['data'] as $vec ) {
					$vectors[] = array_map( 'floatval', (array) $vec );
				}
			} else {
				$vectors[] = array_map( 'floatval', (array) $json['data'] );
			}
		}

		return $single ? array( $vectors[0] ?? array() ) : $vectors;
	}
}
