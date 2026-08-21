<?php
/**
 * Embedding manager — factory + cached active provider.
 *
 * @package ItihRag\Embeddings
 */

namespace ItihRag\Embeddings;

use ItihRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Embedding_Manager {

	/**
	 * @var Embedding_Provider|null
	 */
	private $provider = null;

	/**
	 * Resolved active provider.
	 *
	 * @return Embedding_Provider
	 */
	public function provider() {
		if ( null !== $this->provider ) {
			return $this->provider;
		}
		$settings = Settings::group( 'embeddings' );
		$id       = $settings['embedding_provider'] ?? 'openai';

		switch ( $id ) {
			case 'openai-compatible':
				$this->provider = new Compatible_Embedding( $settings );
				break;
			case 'cloudflare':
				$this->provider = new Cloudflare_Embedding( $settings );
				break;
			case 'ollama':
				$this->provider = new Ollama_Embedding( $settings );
				break;
			case 'openai':
			default:
				$this->provider = new OpenAI_Embedding( $settings );
				break;
		}
		return $this->provider;
	}

	/**
	 * Available provider classes (for admin dropdowns).
	 *
	 * @return array<string,class-string>
	 */
	public function providers() {
		return array(
			'openai'             => OpenAI_Embedding::class,
			'openai-compatible'  => Compatible_Embedding::class,
			'cloudflare'         => Cloudflare_Embedding::class,
			'ollama'             => Ollama_Embedding::class,
		);
	}

	/**
	 * Embed helper that returns a list of vectors.
	 *
	 * @param string|array $inputs Input(s).
	 * @return array<int,array<int,float>>
	 */
	public function embed( $inputs ) {
		return $this->provider()->embed( $inputs );
	}

	/**
	 * Embed a single string and return one vector.
	 *
	 * @param string $text Text.
	 * @return array<int,float>
	 */
	public function embed_one( $text ) {
		$out = $this->embed( (string) $text );
		return is_array( $out ) && isset( $out[0] ) ? $out[0] : array();
	}

	/**
	 * Active dimensionality (auto-detect by probing if needed).
	 *
	 * @return int
	 */
	public function dimensions() {
		$dim = $this->provider()->dimensions();
		if ( $dim > 0 ) {
			return $dim;
		}

		// Probe with a short string to learn the dimension.
		$cached = get_transient( 'itih_embedding_dim' );
		if ( false !== $cached && (int) $cached > 0 ) {
			return (int) $cached;
		}

		try {
			$vectors = $this->embed( 'dimension probe' );
			$dim     = isset( $vectors[0] ) ? count( $vectors[0] ) : 0;
			if ( $dim > 0 ) {
				set_transient( 'itih_embedding_dim', $dim, DAY_IN_SECONDS );
			}
		} catch ( \Throwable $e ) {
			$dim = 0;
		}
		return (int) $dim;
	}
}
