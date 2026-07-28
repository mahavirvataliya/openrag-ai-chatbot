<?php
/**
 * Embedding provider contract.
 *
 * @package OpenRag\Embeddings
 */

namespace OpenRag\Embeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Embedding_Provider {

	/**
	 * Unique provider key.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Embed one or more input strings.
	 *
	 * @param string|array $inputs Single text or array of texts.
	 * @return array{0: array<int,float>}|array<int,array<int,float>>
	 *     Returns a single vector when input was scalar, or a list when input was array.
	 */
	public function embed( $inputs );

	/**
	 * Dimensionality of the active model.
	 *
	 * @return int
	 */
	public function dimensions();

	/**
	 * Whether the provider is fully configured (credentials present).
	 *
	 * @return bool
	 */
	public function is_configured();
}
